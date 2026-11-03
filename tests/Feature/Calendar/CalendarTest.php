<?php

use App\Enums\EventStatus;
use App\Enums\TeamRole;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\DealPipeline;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

// Wednesday 30 September 2026, noon (Pakistan time).
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * Plan an event through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function planEvent($test, User $user, Team $team, array $overrides = []): CalendarEvent
{
    $test->actingAs($user)
        ->post(route('calendar.store', ['current_team' => $team->slug]), array_merge([
            'type' => 'meeting',
            'title' => 'Demo at Indus Traders',
            'starts_at' => '2026-10-01 10:00',
            'ends_at' => '2026-10-01 11:30',
            'location' => 'Gulberg, Lahore',
        ], $overrides))
        ->assertSessionHasNoErrors();

    return CalendarEvent::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

test('an event is planned with attendees and a customer', function () {
    [$user, $team] = teamOwner();
    $colleague = teamMember($team);
    $company = Company::factory()->for($team)->create();

    $event = planEvent($this, $user, $team, ['company_id' => $company->id, 'attendee_ids' => [$colleague->id], 'reminder_minutes' => 30]);

    expect($event->organizer_id)->toBe($user->id)
        ->and($event->company_id)->toBe($company->id)
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2026-10-01 10:00')
        ->and($event->status)->toBe(EventStatus::Planned)
        ->and($event->attendees()->pluck('users.id')->sort()->values()->all())->toBe(collect([$user->id, $colleague->id])->sort()->values()->all());
});

test('all-day events cover the whole day', function () {
    [$user, $team] = teamOwner();

    $event = planEvent($this, $user, $team, ['all_day' => true, 'starts_at' => '2026-10-05', 'ends_at' => '2026-10-06']);

    expect($event->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-05 00:00:00')
        ->and($event->ends_at->format('Y-m-d H:i:s'))->toBe('2026-10-06 23:59:59');
});

test('an event cannot end before it starts', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('calendar.store', ['current_team' => $team->slug]), [
            'type' => 'call', 'title' => 'Call', 'starts_at' => '2026-10-01 10:00', 'ends_at' => '2026-10-01 09:00',
        ])
        ->assertSessionHasErrors('ends_at');
});

test('the week shows my events and my tasks that are due', function () {
    [$user, $team] = teamOwner();
    $colleague = teamMember($team);
    planEvent($this, $user, $team, ['title' => 'This week']);
    planEvent($this, $user, $team, ['title' => 'Next week', 'starts_at' => '2026-10-06 10:00', 'ends_at' => '2026-10-06 11:00']);
    planEvent($this, $colleague, $team, ['title' => 'Not mine']);
    planEvent($this, $colleague, $team, ['title' => 'Invited', 'attendee_ids' => [$user->id]]);
    withinTeam($team, fn () => Task::factory()->create(['team_id' => $team->id, 'title' => 'Send proposal', 'assignee_id' => $user->id, 'due_date' => '2026-10-02']));

    $this->actingAs($user)
        ->get(route('calendar.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Index')
            ->where('range.start', '2026-09-28')
            ->where('range.end', '2026-10-04')
            ->has('events', 2)
            ->where('events.0.title', 'This week')
            ->where('events.1.title', 'Invited')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Send proposal'));

    $this->actingAs($user)
        ->get(route('calendar.index', ['current_team' => $team->slug, 'user' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('events', 3));

    $this->actingAs($user)
        ->get(route('calendar.index', ['current_team' => $team->slug, 'view' => 'month']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.start', '2026-08-31')
            ->where('range.end', '2026-10-04')
            ->has('events', 2));
});

test('an event is moved and then reported as done', function () {
    [$user, $team] = teamOwner();
    $event = planEvent($this, $user, $team);

    $this->actingAs($user)
        ->put(route('calendar.update', ['current_team' => $team->slug, 'event' => $event->id]), [
            'type' => 'meeting', 'title' => 'Demo at Indus Traders', 'starts_at' => '2026-10-02 15:00', 'ends_at' => '2026-10-02 16:00',
        ])
        ->assertSessionHasNoErrors();

    expect($event->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-02 15:00');

    $this->actingAs($user)
        ->post(route('calendar.complete', ['current_team' => $team->slug, 'event' => $event->id]), ['outcome' => 'They want a quotation for 20 users.'])
        ->assertRedirect();

    expect($event->fresh()->status)->toBe(EventStatus::Done)
        ->and($event->fresh()->outcome)->toBe('They want a quotation for 20 users.');
});

test('an event can be downloaded for another calendar app', function () {
    [$user, $team] = teamOwner();
    $event = planEvent($this, $user, $team, ['reminder_minutes' => 30]);

    $response = $this->actingAs($user)
        ->get(route('calendar.ics', ['current_team' => $team->slug, 'event' => $event->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    expect($response->getContent())
        ->toContain('BEGIN:VEVENT')
        ->toContain('SUMMARY:Demo at Indus Traders')
        ->toContain('LOCATION:Gulberg\, Lahore')
        ->toContain('DTSTART:20261001T050000Z')
        ->toContain('TRIGGER:-PT30M');
});

test('the company history shows its meetings', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    planEvent($this, $user, $team, ['company_id' => $company->id, 'title' => 'Kick-off']);
    $member = teamMember($team, TeamRole::Member, ['crm' => 'read', 'calendar' => 'none']);

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline', fn ($items) => collect($items)->contains(fn ($item) => $item['kind'] === 'event' && $item['title'] === 'Kick-off')));

    $this->actingAs($member)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline', fn ($items) => ! collect($items)->contains(fn ($item) => $item['kind'] === 'event')));
});

test('planning from a deal brings along its customer', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $pipeline = DealPipeline::withoutGlobalScopes()->where('team_id', $team->id)->firstOrFail();
    $phase = DealPhase::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->firstOrFail();
    $deal = Deal::factory()->for($team)->create(['company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'phase_id' => $phase->id, 'title' => 'ERP deal']);

    $this->actingAs($user)
        ->get(route('calendar.index', ['current_team' => $team->slug, 'plan' => 1, 'deal' => $deal->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('planFor.deal.title', 'ERP deal')
            ->where('planFor.company.name', 'Indus Traders'));
});

test('events are private to the team and the calendar module', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $foreign = planEvent($this, $otherUser, $otherTeam);
    $member = teamMember($team, TeamRole::Member, ['calendar' => 'none']);

    $this->actingAs($user)
        ->delete(route('calendar.destroy', ['current_team' => $team->slug, 'event' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($member)
        ->get(route('calendar.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});
