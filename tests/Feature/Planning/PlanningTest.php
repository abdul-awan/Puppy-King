<?php

use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\CalendarEvent;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

// Wednesday 30 September 2026; the week runs from Monday 28 September to Sunday 4 October.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

test('the board shows meetings and planned tasks against the working hours', function () {
    [$user, $team] = teamOwner();

    withinTeam($team, function () use ($team, $user) {
        $meeting = CalendarEvent::factory()->create([
            'team_id' => $team->id,
            'organizer_id' => $user->id,
            'title' => 'Weekly meeting',
            'starts_at' => '2026-09-30 10:00',
            'ends_at' => '2026-09-30 11:30',
        ]);
        $meeting->attendees()->attach($user->id);

        Task::factory()->create(['team_id' => $team->id, 'title' => 'Write proposal', 'assignee_id' => $user->id, 'planned_date' => '2026-09-30', 'planned_minutes' => 120]);
        Task::factory()->create(['team_id' => $team->id, 'title' => 'Estimate only', 'assignee_id' => $user->id, 'planned_date' => '2026-10-01', 'estimated_minutes' => 240]);
    });

    $this->actingAs($user)
        ->get(route('planning.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('planning/Index')
            ->has('days', 7)
            ->where('days.0.date', '2026-09-28')
            ->where('board.0.user.id', $user->id)
            ->where('board.0.days.2.date', '2026-09-30')
            ->where('board.0.days.2.minutes', 210)
            ->where('board.0.days.2.capacity', 480)
            ->has('board.0.days.2.items', 2)
            ->where('board.0.days.3.minutes', 240)
            ->where('board.0.days.6.capacity', 0)
            ->where('board.0.minutes', 450)
            ->where('board.0.capacity', 480 * 6));
});

test('a task is planned for a person and taken out again', function () {
    [$user, $team] = teamOwner();
    $colleague = teamMember($team);
    $task = withinTeam($team, fn () => Task::factory()->create(['team_id' => $team->id, 'title' => 'Install router', 'estimated_minutes' => 90]));

    $this->actingAs($user)
        ->get(route('planning.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page->has('unplanned', 1)->where('unplanned.0.title', 'Install router'));

    $this->actingAs($user)
        ->patch(route('planning.plan', ['current_team' => $team->slug, 'task' => $task->id]), [
            'assignee_id' => $colleague->id,
            'planned_date' => '2026-10-02',
            'planned_minutes' => 120,
        ])
        ->assertSessionHasNoErrors();

    $task->refresh();

    expect($task->assignee_id)->toBe($colleague->id)
        ->and($task->planned_date?->toDateString())->toBe('2026-10-02')
        ->and($task->planned_minutes)->toBe(120);

    $this->actingAs($user)
        ->get(route('planning.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page->has('unplanned', 0));

    $this->actingAs($user)
        ->patch(route('planning.plan', ['current_team' => $team->slug, 'task' => $task->id]), ['planned_date' => null])
        ->assertSessionHasNoErrors();

    expect($task->fresh()->planned_date)->toBeNull()
        ->and($task->fresh()->planned_minutes)->toBeNull()
        ->and($task->fresh()->assignee_id)->toBe($colleague->id);
});

test('planning a day needs a person', function () {
    [$user, $team] = teamOwner();
    $task = withinTeam($team, fn () => Task::factory()->create(['team_id' => $team->id]));

    $this->actingAs($user)
        ->patch(route('planning.plan', ['current_team' => $team->slug, 'task' => $task->id]), ['assignee_id' => null, 'planned_date' => '2026-10-02'])
        ->assertSessionHasErrors('assignee_id');
});

test('done tasks are not waiting to be planned', function () {
    [$user, $team] = teamOwner();
    withinTeam($team, fn () => Task::factory()->create(['team_id' => $team->id, 'status' => TaskStatus::Done]));

    $this->actingAs($user)
        ->get(route('planning.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page->has('unplanned', 0));
});

test('the working days come from the company settings', function () {
    [$user, $team] = teamOwner();
    $team->update(['settings' => ['working_days' => [1, 2, 3, 4, 5], 'daily_capacity_minutes' => 420]]);

    $this->actingAs($user)
        ->get(route('planning.index', ['current_team' => $team->slug, 'weeks' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('days', 14)
            ->where('capacity', 420)
            ->where('days.5.working', false)
            ->where('board.0.days.4.capacity', 420)
            ->where('board.0.days.5.capacity', 0)
            ->where('board.0.capacity', 420 * 10));
});

test('the working days can be changed in the settings', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->get(route('settings.general.edit', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('settings.working_days', [1, 2, 3, 4, 5, 6]));

    $this->actingAs($user)
        ->put(route('settings.general.update', ['current_team' => $team->slug]), [
            'currency' => 'PKR',
            'fiscal_year_start_month' => 7,
            'further_tax_enabled' => true,
            'further_tax_rate' => '4',
            'quotation_validity_days' => 30,
            'reminders' => ['enabled' => false, 'days_after_due' => []],
            'daily_capacity_minutes' => 480,
            'working_days' => [5, 1, 2, 3, 4],
        ])
        ->assertSessionHasNoErrors();

    expect($team->fresh()->teamSettings()->workingDays())->toBe([1, 2, 3, 4, 5]);
});

test('the planning needs access to the planning module', function () {
    [$owner, $team] = teamOwner();
    $reader = teamMember($team, TeamRole::Member, ['planning' => 'read']);
    $outsider = teamMember($team, TeamRole::Member, ['planning' => 'none']);
    $task = withinTeam($team, fn () => Task::factory()->create(['team_id' => $team->id]));

    $this->actingAs($outsider)->get(route('planning.index', ['current_team' => $team->slug]))->assertForbidden();
    $this->actingAs($reader)->get(route('planning.index', ['current_team' => $team->slug]))->assertOk();
    $this->actingAs($reader)
        ->patch(route('planning.plan', ['current_team' => $team->slug, 'task' => $task->id]), ['assignee_id' => $reader->id, 'planned_date' => '2026-10-02'])
        ->assertForbidden();
});
