<?php

use App\Enums\TeamRole;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Note;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a note can be added, edited and deleted by its author', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('notes.store', ['current_team' => $team->slug]), [
            'notable_type' => 'company',
            'notable_id' => $company->id,
            'body' => 'Called about the new order.',
        ])
        ->assertRedirect();

    $note = Note::withoutGlobalScopes()->firstOrFail();

    expect($note->team_id)->toBe($team->id)
        ->and($note->notable_type)->toBe('company')
        ->and($note->user_id)->toBe($user->id);

    $this->patch(route('notes.update', ['current_team' => $team->slug, 'note' => $note->id]), [
        'body' => 'Updated text',
        'is_pinned' => true,
    ])->assertRedirect();

    expect($note->fresh()->body)->toBe('Updated text')
        ->and($note->fresh()->is_pinned)->toBeTrue();

    $this->delete(route('notes.destroy', ['current_team' => $team->slug, 'note' => $note->id]))
        ->assertRedirect();

    expect(Note::withoutGlobalScopes()->count())->toBe(0);
});

test('a member cannot edit someone else\'s note', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team);
    $company = Company::factory()->for($team)->create();

    $this->actingAs($owner)->post(route('notes.store', ['current_team' => $team->slug]), [
        'notable_type' => 'company',
        'notable_id' => $company->id,
        'body' => 'Owner note',
    ]);

    $note = Note::withoutGlobalScopes()->firstOrFail();

    $this->actingAs($member)
        ->patch(route('notes.update', ['current_team' => $team->slug, 'note' => $note->id]), ['body' => 'Changed'])
        ->assertForbidden();
});

test('notes cannot be added to records of another team', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $foreign = Company::factory()->for($otherTeam)->create();

    $this->actingAs($user)
        ->post(route('notes.store', ['current_team' => $team->slug]), [
            'notable_type' => 'company',
            'notable_id' => $foreign->id,
            'body' => 'Should fail',
        ])
        ->assertNotFound();
});

test('view-only members cannot add notes', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['crm' => 'read']);
    $company = Company::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('notes.store', ['current_team' => $team->slug]), [
            'notable_type' => 'company',
            'notable_id' => $company->id,
            'body' => 'Nope',
        ])
        ->assertForbidden();
});

test('files can be uploaded, downloaded and deleted', function () {
    Storage::fake('local');
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $this->actingAs($user)
        ->post(route('attachments.store', ['current_team' => $team->slug]), [
            'attachable_type' => 'company',
            'attachable_id' => $company->id,
            'files' => [UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf')],
        ])
        ->assertRedirect();

    $attachment = Attachment::withoutGlobalScopes()->firstOrFail();

    expect($attachment->name)->toBe('contract.pdf')
        ->and($attachment->team_id)->toBe($team->id);
    Storage::disk('local')->assertExists($attachment->path);

    $this->get(route('attachments.show', ['current_team' => $team->slug, 'attachment' => $attachment->id]))
        ->assertOk()
        ->assertDownload('contract.pdf');

    $this->delete(route('attachments.destroy', ['current_team' => $team->slug, 'attachment' => $attachment->id]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($attachment->path);
});

test('files of another team cannot be downloaded', function () {
    Storage::fake('local');
    [$owner, $team] = teamOwner();
    [$intruder, $otherTeam] = teamOwner();
    $company = Company::factory()->for($team)->create();

    $this->actingAs($owner)->post(route('attachments.store', ['current_team' => $team->slug]), [
        'attachable_type' => 'company',
        'attachable_id' => $company->id,
        'files' => [UploadedFile::fake()->create('secret.pdf', 10)],
    ]);

    $attachment = Attachment::withoutGlobalScopes()->firstOrFail();

    $this->actingAs($intruder)
        ->get(route('attachments.show', ['current_team' => $otherTeam->slug, 'attachment' => $attachment->id]))
        ->assertNotFound();
});
