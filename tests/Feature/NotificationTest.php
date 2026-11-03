<?php

use App\Enums\TeamRole;
use App\Models\CalendarEvent;
use App\Models\Quotation;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TeamNotification;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * The kinds of the notifications a user received.
 *
 * @return list<string>
 */
function notificationKinds(User $user): array
{
    return array_values($user->notifications()->get()->map(fn ($notification) => $notification->data['kind'])->all());
}

test('assigning a ticket notifies the new assignee but not the one who assigned it', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team);

    $this->actingAs($owner)
        ->post(route('tickets.store', ['current_team' => $team->slug]), [
            'subject' => 'Printer does not work',
            'customer_name' => 'Ali Raza',
            'assignee_id' => $member->id,
        ])
        ->assertSessionHasNoErrors();

    $ticket = Ticket::withoutGlobalScopes()->where('team_id', $team->id)->sole();
    $notification = $member->notifications()->sole();

    expect($notification->data)->toMatchArray([
        'team_id' => $team->id,
        'kind' => 'ticket.assigned',
        'title' => "Ticket {$ticket->number} was assigned to you",
        'body' => 'Printer does not work',
        'url' => "/{$team->slug}/tickets/{$ticket->id}",
    ])->and($owner->notifications()->count())->toBe(0);

    // Taking a ticket yourself is not news.
    $this->actingAs($owner)
        ->patch(route('tickets.quick-update', ['current_team' => $team->slug, 'ticket' => $ticket->id]), ['assignee_id' => $owner->id])
        ->assertSessionHasNoErrors();

    expect($owner->notifications()->count())->toBe(0);
});

test('customers writing in notify the people who handle tickets', function () {
    [$owner, $team] = teamOwner();
    $helpdesk = teamMember($team);
    $sales = teamMember($team, TeamRole::Member, ['tickets' => 'none']);
    $team->update(['settings' => ['support' => ['enabled' => true, 'token' => 'secret-token']]]);

    $this->post(route('public.support.store', ['token' => 'secret-token']), [
        'name' => 'Sara Khan', 'email' => 'sara@indus.test', 'subject' => 'Wrong address', 'message' => 'Please change it.',
    ])->assertSessionHasNoErrors();

    expect(notificationKinds($owner))->toBe(['ticket.created'])
        ->and(notificationKinds($helpdesk))->toBe(['ticket.created'])
        ->and(notificationKinds($sales))->toBe([]);

    $ticket = Ticket::withoutGlobalScopes()->where('team_id', $team->id)->sole();
    $ticket->update(['assignee_id' => $helpdesk->id]);

    $this->post(route('public.tickets.reply', ['token' => $ticket->public_token]), ['name' => 'Sara Khan', 'body' => 'Any news?'])
        ->assertRedirect();

    expect(notificationKinds($helpdesk))->toContain('ticket.replied')
        ->and(notificationKinds($owner))->not->toContain('ticket.replied');
});

test('assigning a task or a work order notifies the assignee', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team);

    $this->actingAs($owner)
        ->post(route('tasks.store', ['current_team' => $team->slug]), ['title' => 'Call Indus Traders', 'assignee_id' => $member->id, 'due_date' => '2026-10-02'])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('work-orders.store', ['current_team' => $team->slug]), ['title' => 'AC service', 'date' => '2026-09-30', 'assignee_id' => $member->id])
        ->assertSessionHasNoErrors();

    expect(notificationKinds($member))->toEqualCanonicalizing(['task.assigned', 'work_order.assigned'])
        ->and($member->notifications()->get()->firstWhere('data.kind', 'task.assigned')?->data['body'])->toBe('Due 02 Oct 2026');
});

test('answers and signatures of customers notify the people involved', function () {
    [$owner, $team] = teamOwner();
    $seller = teamMember($team);
    $quotation = Quotation::factory()->for($team)->sent()->create(['created_by' => $seller->id]);
    $workOrder = WorkOrder::factory()->for($team)->create(['assignee_id' => $seller->id, 'created_by' => $owner->id]);

    $this->post(route('public.quotations.accept', ['token' => $quotation->public_token]), [
        'name' => 'Ali Raza', 'signature' => workOrderBellSignature(), 'agree' => true,
    ])->assertSessionHasNoErrors();

    $this->post(route('public.work-orders.sign', ['token' => $workOrder->public_token]), [
        'name' => 'Ali Raza', 'signature' => workOrderBellSignature(), 'agree' => true,
    ])->assertSessionHasNoErrors();

    expect(notificationKinds($seller))->toEqualCanonicalizing(['quotation.accepted', 'work_order.signed'])
        ->and(notificationKinds($owner))->toBe(['work_order.signed']);
});

test('calendar reminders are sent once when their time has come', function () {
    [$owner, $team] = teamOwner();
    $member = teamMember($team);

    $soon = CalendarEvent::factory()->for($team)->create([
        'title' => 'Visit Indus Traders',
        'organizer_id' => $owner->id,
        'starts_at' => '2026-09-30 12:10:00',
        'ends_at' => '2026-09-30 13:00:00',
        'reminder_minutes' => 15,
        'location' => 'Mall Road',
    ]);
    $soon->attendees()->attach($member->id);

    CalendarEvent::factory()->for($team)->create([
        'organizer_id' => $owner->id,
        'starts_at' => '2026-09-30 15:00:00',
        'ends_at' => '2026-09-30 16:00:00',
        'reminder_minutes' => 15,
    ]);

    $this->artisan('calendar:send-reminders')->assertSuccessful();
    $this->artisan('calendar:send-reminders')->assertSuccessful();

    expect(notificationKinds($owner))->toBe(['event.reminder'])
        ->and(notificationKinds($member))->toBe(['event.reminder'])
        ->and($owner->notifications()->sole()->data['body'])->toBe('At 12:10 · Mall Road')
        ->and($soon->fresh()->reminded_at)->not->toBeNull();
});

test('the bell shows the notifications of the current workspace and opens them', function () {
    [$owner, $team] = teamOwner();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);

    Notification::send($owner, new TeamNotification($team->id, 'task.assigned', 'Task for you: Call Ali', null, "/{$team->slug}/tasks"));
    Notification::send($owner, new TeamNotification($team->id, 'ticket.assigned', 'Ticket TKT-0001 was assigned to you', 'Printer', "/{$team->slug}/tickets"));
    Notification::send($owner, new TeamNotification($otherTeam->id, 'task.assigned', 'Elsewhere', null, "/{$otherTeam->slug}/tasks"));
    Notification::send($owner, new TeamNotification($team->id, 'task.assigned', 'Sneaky', null, 'https://evil.example'));

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unread', 3)
            ->has('notifications.items', 3));

    $ticketNotice = $owner->notifications()->get()->firstWhere('data.kind', 'ticket.assigned');

    $this->post(route('notifications.read', ['current_team' => $team->slug, 'notification' => $ticketNotice->id]))
        ->assertRedirect("/{$team->slug}/tickets");

    expect($ticketNotice->fresh()->read_at)->not->toBeNull();

    // Links to other sites are never followed.
    $sneaky = $owner->notifications()->get()->firstWhere('data.title', 'Sneaky');
    $this->post(route('notifications.read', ['current_team' => $team->slug, 'notification' => $sneaky->id]))
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));

    $this->post(route('notifications.read-all', ['current_team' => $team->slug]))->assertRedirect();

    expect($owner->unreadNotifications()->count())->toBe(1); // only the other workspace's

    // Nobody can open someone else's notification.
    $stranger = teamMember($team);
    $this->actingAs($stranger)
        ->post(route('notifications.read', ['current_team' => $team->slug, 'notification' => $ticketNotice->id]))
        ->assertNotFound();

    Auth::logout();
});

test('nobody is notified about a module they cannot see', function () {
    [$owner, $team] = teamOwner();
    $noTickets = teamMember($team, TeamRole::Member, ['tickets' => 'none']);
    $ticket = Ticket::factory()->for($team)->create(['assignee_id' => $noTickets->id]);

    Notifier::ticketAssigned($ticket, $owner);

    expect($noTickets->notifications()->count())->toBe(0);
});

/**
 * A 1×1 PNG, like a signature drawn on a canvas.
 */
function workOrderBellSignature(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
}
