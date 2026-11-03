<?php

use App\Enums\TeamRole;
use App\Models\Company;
use App\Models\Project;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A project of the team with an hourly rate of 5,000.
 *
 * @param  array<string, mixed>  $attributes
 */
function timeProject(Team $team, array $attributes = []): Project
{
    return Project::factory()->create(['team_id' => $team->id, 'hourly_rate' => '5000.00', ...$attributes]);
}

/**
 * Log time through the form and return the new entry.
 *
 * @param  array<string, mixed>  $data
 */
function logThroughForm($test, User $user, Team $team, array $data): TimeEntry
{
    $test->actingAs($user)
        ->post(route('time.store', ['current_team' => $team->slug]), array_merge([
            'date' => '2026-09-30',
            'duration_minutes' => 90,
            'is_billable' => true,
        ], $data))
        ->assertSessionHasNoErrors();

    return TimeEntry::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

test('the hourly rate comes from the work type, then the project', function () {
    [$user, $team] = teamOwner();
    $project = timeProject($team);
    $development = WorkType::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Development')->firstOrFail();
    $development->update(['hourly_rate' => '3000']);

    $withType = logThroughForm($this, $user, $team, ['project_id' => $project->id, 'work_type_id' => $development->id]);
    $withoutType = logThroughForm($this, $user, $team, ['project_id' => $project->id]);
    $overridden = logThroughForm($this, $user, $team, ['project_id' => $project->id, 'hourly_rate' => '9000']);

    expect($withType->hourly_rate)->toBe('3000.00')
        ->and($withType->value())->toBe('4500.00')
        ->and($withoutType->hourly_rate)->toBe('5000.00')
        ->and($overridden->hourly_rate)->toBe('9000.00')
        ->and($withType->user_id)->toBe($user->id)
        ->and($withType->company_id)->toBe($project->company_id);
});

test('time on a non-billable project is never billable', function () {
    [$user, $team] = teamOwner();
    $project = timeProject($team, ['billing_method' => 'non_billable']);

    $entry = logThroughForm($this, $user, $team, ['project_id' => $project->id, 'is_billable' => true]);

    expect($entry->is_billable)->toBeFalse();
});

test('time cannot be logged in the future or for too long', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('time.store', ['current_team' => $team->slug]), ['date' => '2026-10-01', 'duration_minutes' => 60])
        ->assertSessionHasErrors('date');

    $this->actingAs($user)
        ->post(route('time.store', ['current_team' => $team->slug]), ['date' => '2026-09-30', 'duration_minutes' => 1500])
        ->assertSessionHasErrors('duration_minutes');
});

test('the timer runs until it is stopped', function () {
    [$user, $team] = teamOwner();
    $project = timeProject($team);

    $this->actingAs($user)
        ->post(route('timer.start', ['current_team' => $team->slug]), ['project_id' => $project->id, 'description' => 'Fixing the checkout'])
        ->assertRedirect();

    $entry = TimeEntry::withoutGlobalScopes()->where('user_id', $user->id)->sole();
    expect($entry->isRunning())->toBeTrue();

    $this->actingAs($user)
        ->get(route('time.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timer.id', $entry->id)
            ->where('timer.description', 'Fixing the checkout')
            ->where('timer.project', $project->title));

    $this->travel(95)->minutes();

    $this->actingAs($user)->post(route('timer.stop', ['current_team' => $team->slug]));

    $entry->refresh();

    expect($entry->isRunning())->toBeFalse()
        ->and($entry->duration_minutes)->toBe(95)
        ->and($entry->hourly_rate)->toBe('5000.00');
});

test('starting a new timer stops the running one', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)->post(route('timer.start', ['current_team' => $team->slug]), ['description' => 'First']);
    $this->travel(30)->minutes();
    $this->actingAs($user)->post(route('timer.start', ['current_team' => $team->slug]), ['description' => 'Second']);

    $entries = TimeEntry::withoutGlobalScopes()->where('user_id', $user->id)->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->isRunning())->toBeFalse()
        ->and($entries[0]->duration_minutes)->toBe(30)
        ->and($entries[1]->isRunning())->toBeTrue();
});

test('members change only their own time, admins anyone\'s', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['time_tracking' => 'write']);
    $admin = teamMember($team, TeamRole::Admin);
    $ownersEntry = logThroughForm($this, $owner, $team, []);

    $this->actingAs($member)
        ->delete(route('time.destroy', ['current_team' => $team->slug, 'time_entry' => $ownersEntry->id]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('time.destroy', ['current_team' => $team->slug, 'time_entry' => $ownersEntry->id]))
        ->assertRedirect();

    expect(TimeEntry::withoutGlobalScopes()->whereKey($ownersEntry->id)->exists())->toBeFalse();
});

test('an admin can log time for a colleague but a member cannot', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['time_tracking' => 'write']);

    $forMember = logThroughForm($this, $owner, $team, ['user_id' => $member->id]);
    $sneaky = logThroughForm($this, $member, $team, ['user_id' => $owner->id]);

    expect($forMember->user_id)->toBe($member->id)
        ->and($sneaky->user_id)->toBe($member->id);
});

test('invoiced time is locked', function () {
    [$user, $team] = teamOwner();
    $entry = logThroughForm($this, $user, $team, []);
    $invoice = issuedInvoice($this, $user, $team, Company::factory()->for($team)->create());
    $entry->forceFill(['invoice_id' => $invoice->id])->save();

    $this->actingAs($user)
        ->put(route('time.update', ['current_team' => $team->slug, 'time_entry' => $entry->id]), ['date' => '2026-09-30', 'duration_minutes' => 10])
        ->assertSessionHasErrors('entry');
});

test('the timesheet shows one week and members see only their own time', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team, TeamRole::Member, ['time_tracking' => 'write']);
    logThroughForm($this, $owner, $team, ['date' => '2026-09-28', 'duration_minutes' => 60]);
    logThroughForm($this, $owner, $team, ['date' => '2026-09-21', 'duration_minutes' => 30]);
    logThroughForm($this, $member, $team, ['date' => '2026-09-29', 'duration_minutes' => 45]);

    $this->actingAs($owner)
        ->get(route('time.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('time/Index')
            ->where('week.start', '2026-09-28')
            ->has('entries', 1)
            ->where('totals.minutes', 60)
            ->where('isManager', true));

    $this->actingAs($owner)
        ->get(route('time.index', ['current_team' => $team->slug, 'user' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('entries', 2)->where('totals.minutes', 105));

    $this->actingAs($member)
        ->get(route('time.index', ['current_team' => $team->slug, 'user' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.user', 'me')
            ->has('entries', 1)
            ->where('totals.minutes', 45)
            ->where('isManager', false));

    $this->actingAs($owner)
        ->get(route('time.index', ['current_team' => $team->slug, 'week' => '2026-09-22']))
        ->assertInertia(fn (Assert $page) => $page->where('week.start', '2026-09-21')->where('totals.minutes', 30));
});

test('time can be exported', function () {
    [$user, $team] = teamOwner();
    $project = timeProject($team, ['title' => 'Website']);
    logThroughForm($this, $user, $team, ['project_id' => $project->id, 'description' => 'Homepage design']);

    $csv = $this->actingAs($user)
        ->get(route('time.export', ['current_team' => $team->slug, 'view' => 'list']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Homepage design')
        ->toContain('Website')
        ->toContain('1.50');
});
