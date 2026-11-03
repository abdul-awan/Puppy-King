<?php

use App\Enums\TeamRole;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot see companies', function () {
    $team = Team::factory()->create();

    $this->get(route('companies.index', ['current_team' => $team->slug]))
        ->assertRedirect(route('login'));
});

test('the companies index lists only the team\'s companies', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();

    Company::factory()->for($team)->create(['name' => 'Alpha Textiles']);
    Company::factory()->for($otherTeam)->create(['name' => 'Foreign Co']);

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('crm/companies/Index')
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Alpha Textiles'));
});

test('companies can be searched', function () {
    [$user, $team] = teamOwner();

    Company::factory()->for($team)->create(['name' => 'Karachi Steel']);
    Company::factory()->for($team)->create(['name' => 'Lahore Foods']);

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $team->slug, 'search' => 'steel']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Karachi Steel'));
});

test('a company can be created with addresses and tags', function () {
    [$user, $team] = teamOwner();
    $tag = withinTeam($team, fn () => Tag::create(['name' => 'VIP', 'color' => 'amber']));

    $response = $this->actingAs($user)->post(route('companies.store', ['current_team' => $team->slug]), [
        'name' => 'Indus Motors',
        'relationship' => 'customer',
        'business_type' => 'private_limited',
        'ntn' => '1234567-8',
        'strn' => '1234567890123',
        'cnic' => '35202-1234567-1',
        'sales_tax_registered' => true,
        'email' => 'accounts@indus.pk',
        'website' => 'indus.pk',
        'tag_ids' => [$tag->id],
        'addresses' => [
            ['type' => 'primary', 'line_1' => '12 Main Boulevard', 'city' => 'Lahore', 'province' => 'punjab', 'country' => 'Pakistan'],
        ],
    ]);

    $company = Company::withoutGlobalScopes()->where('name', 'Indus Motors')->firstOrFail();

    $response->assertRedirect(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]));

    expect($company->team_id)->toBe($team->id)
        ->and($company->website)->toBe('https://indus.pk')
        ->and($company->sales_tax_registered)->toBeTrue()
        ->and($company->addresses()->count())->toBe(1)
        ->and($company->tags()->pluck('name')->all())->toBe(['VIP']);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => 'company',
        'subject_id' => $company->id,
        'event' => 'created',
        'causer_id' => $user->id,
    ]);
});

test('an invalid cnic is rejected', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('companies.store', ['current_team' => $team->slug]), [
            'name' => 'Bad CNIC',
            'relationship' => 'customer',
            'cnic' => '123',
        ])
        ->assertSessionHasErrors('cnic');
});

test('tags of another team cannot be assigned', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $foreignTag = withinTeam($otherTeam, fn () => Tag::create(['name' => 'Other', 'color' => 'red']));

    $this->actingAs($user)
        ->post(route('companies.store', ['current_team' => $team->slug]), [
            'name' => 'Sneaky',
            'relationship' => 'customer',
            'tag_ids' => [$foreignTag->id],
        ])
        ->assertSessionHasErrors('tag_ids.0');
});

test('a company can be viewed, updated, archived and deleted', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Old Name']);
    $params = ['current_team' => $team->slug, 'company' => $company->id];

    $this->actingAs($user)
        ->get(route('companies.show', $params))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('crm/companies/Show')
            ->where('company.name', 'Old Name')
            ->has('timeline'));

    $this->put(route('companies.update', $params), [
        'name' => 'New Name',
        'relationship' => 'prospect',
    ])->assertRedirect(route('companies.show', $params));

    expect($company->fresh()->name)->toBe('New Name');

    $update = ActivityLog::withoutGlobalScopes()
        ->where('subject_id', $company->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($update->properties['old']['name'])->toBe('Old Name')
        ->and($update->properties['new']['name'])->toBe('New Name');

    $this->patch(route('companies.archive', $params))->assertRedirect();
    expect($company->fresh()->archived_at)->not->toBeNull();

    $this->delete(route('companies.destroy', $params))
        ->assertRedirect(route('companies.index', ['current_team' => $team->slug]));

    expect(Company::withoutGlobalScopes()->withTrashed()->find($company->id)->trashed())->toBeTrue();
});

test('a company of another team cannot be opened through your own team url', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    $foreign = Company::factory()->for($otherTeam)->create();

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->put(route('companies.update', ['current_team' => $team->slug, 'company' => $foreign->id]), [
            'name' => 'Hijacked',
            'relationship' => 'customer',
        ])
        ->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Hijacked');
});

test('the urls of a team you do not belong to are forbidden', function () {
    [$user] = teamOwner();
    [, $otherTeam] = teamOwner();

    $this->actingAs($user)
        ->get(route('companies.index', ['current_team' => $otherTeam->slug]))
        ->assertForbidden();
});

test('members with view-only access cannot change companies', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['crm' => 'read']);
    $company = Company::factory()->for($team)->create();

    $this->actingAs($member)
        ->get(route('companies.index', ['current_team' => $team->slug]))
        ->assertOk();

    $this->actingAs($member)
        ->get(route('companies.create', ['current_team' => $team->slug]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('companies.store', ['current_team' => $team->slug]), ['name' => 'X', 'relationship' => 'customer'])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('companies.destroy', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertForbidden();
});

test('members without access cannot see companies at all', function () {
    [, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['crm' => 'none']);

    $this->actingAs($member)
        ->get(route('companies.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('the lookup endpoint only returns companies of the current team', function () {
    [$user, $team] = teamOwner();
    [, $otherTeam] = teamOwner();
    Company::factory()->for($team)->create(['name' => 'Shared Name Ours']);
    Company::factory()->for($otherTeam)->create(['name' => 'Shared Name Theirs']);

    $this->actingAs($user)
        ->getJson(route('lookup', ['current_team' => $team->slug, 'type' => 'companies', 'q' => 'shared']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', 'Shared Name Ours');
});

test('companies can be exported to csv', function () {
    [$user, $team] = teamOwner();
    Company::factory()->for($team)->create(['name' => 'Export Me Ltd']);

    $response = $this->actingAs($user)
        ->get(route('companies.export', ['current_team' => $team->slug]));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Export Me Ltd');
});

test('companies can be imported from csv', function () {
    [$user, $team] = teamOwner();
    Company::factory()->for($team)->create(['name' => 'Existing Co']);

    $csv = "Company,Type,Email,City,Province,Tags\n"
        ."New Traders,prospect,info@new.pk,Karachi,Sindh,\"VIP, Wholesale\"\n"
        ."Existing Co,customer,,,,\n"
        .",customer,,,,\n";

    $this->actingAs($user)
        ->post(route('crm.import.store', ['current_team' => $team->slug]), [
            'type' => 'companies',
            'file' => UploadedFile::fake()->createWithContent('companies.csv', $csv),
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('crm/Import')
            ->where('result.created', 1)
            ->where('result.skipped', 2));

    $imported = Company::withoutGlobalScopes()->where('name', 'New Traders')->firstOrFail();

    expect($imported->team_id)->toBe($team->id)
        ->and($imported->relationship->value)->toBe('prospect')
        ->and($imported->addresses()->first()?->province?->value)->toBe('sindh')
        ->and($imported->tags()->pluck('name')->sort()->values()->all())->toBe(['VIP', 'Wholesale']);
});
