<?php

use App\Enums\TeamRole;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Mail\DocumentMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SentEmail;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * Log a ticket through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function logTicket($test, User $user, Team $team, array $overrides = []): Ticket
{
    $test->actingAs($user)
        ->post(route('tickets.store', ['current_team' => $team->slug]), array_merge([
            'subject' => 'Printer does not work',
            'customer_name' => 'Ali Raza',
            'customer_email' => 'ali@indus.test',
            'channel' => 'phone',
            'priority' => 'high',
            'description' => 'The printer on the 2nd floor shows error E-05.',
        ], $overrides))
        ->assertSessionHasNoErrors();

    return Ticket::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

test('a ticket is logged with the question of the customer', function () {
    [$user, $team] = teamOwner();

    $ticket = logTicket($this, $user, $team);

    expect($ticket->number)->toBe('TKT-0001')
        ->and($ticket->status)->toBe(TicketStatus::New)
        ->and($ticket->channel)->toBe(TicketChannel::Phone)
        ->and($ticket->customerEmail())->toBe('ali@indus.test');

    $message = $ticket->messages()->sole();

    expect($message->fromCustomer())->toBeTrue()
        ->and($message->author_name)->toBe('Ali Raza')
        ->and($message->body)->toContain('E-05');
});

test('an answer is e-mailed and the ticket waits for the customer', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $ticket = logTicket($this, $user, $team);

    $this->actingAs($user)
        ->post(route('tickets.reply', ['current_team' => $team->slug, 'ticket' => $ticket->id]), [
            'body' => 'Please switch the printer off and on again.',
            'email' => true,
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    $reply = $ticket->messages()->where('user_id', $user->id)->sole();

    expect($ticket->status)->toBe(TicketStatus::Waiting)
        ->and($ticket->assignee_id)->toBe($user->id)
        ->and($ticket->first_response_at)->not->toBeNull()
        ->and($reply->emailed_at)->not->toBeNull();

    Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('ali@indus.test')
        && str_contains($mail->subjectLine, 'TKT-0001')
        && str_contains($mail->body, 'switch the printer off')
        && str_contains($mail->body, $ticket->publicUrl()));

    expect(SentEmail::withoutGlobalScopes()->where('emailable_id', $ticket->id)->where('status', 'sent')->count())->toBe(1);
});

test('internal notes stay inside the team', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $ticket = logTicket($this, $user, $team);

    $this->actingAs($user)
        ->post(route('tickets.reply', ['current_team' => $team->slug, 'ticket' => $ticket->id]), [
            'body' => 'Probably the fuser; order a new one.',
            'internal' => true,
        ])
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);

    Auth::logout();

    $this->get(route('public.tickets.show', ['token' => $ticket->public_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Ticket')
            ->has('ticket.messages', 1)
            ->where('ticket.messages.0.from_customer', true));
});

test('the customer answers through the link and the ticket opens again', function () {
    [$user, $team] = teamOwner();
    $ticket = logTicket($this, $user, $team);
    $ticket->update(['status' => TicketStatus::Closed]);

    expect($ticket->fresh()->closed_at)->not->toBeNull();

    Auth::logout();

    $this->post(route('public.tickets.reply', ['token' => $ticket->public_token]), [
        'name' => 'Ali Raza',
        'body' => 'It happens again since this morning.',
    ])->assertRedirect();

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->last_customer_message_at)->not->toBeNull()
        ->and($ticket->messages()->count())->toBe(2);
});

test('the support form creates a ticket for a known contact', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $contact = Contact::factory()->for($team)->create(['email' => 'sara@indus.test']);
    $contact->companies()->attach($company->id, ['position' => 'Accountant']);

    $this->actingAs($user)
        ->put(route('settings.general.update', ['current_team' => $team->slug]), [
            'currency' => 'PKR',
            'fiscal_year_start_month' => 7,
            'further_tax_enabled' => true,
            'further_tax_rate' => '4',
            'quotation_validity_days' => 30,
            'reminders' => ['enabled' => false, 'days_after_due' => []],
            'daily_capacity_minutes' => 480,
            'support_enabled' => true,
        ])
        ->assertSessionHasNoErrors();

    $token = $team->fresh()->teamSettings()->get('support.token');
    expect($token)->toBeString()->not->toBeEmpty();

    Auth::logout();

    $this->get(route('public.support.show', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/SupportForm'));

    $response = $this->post(route('public.support.store', ['token' => $token]), [
        'name' => 'Sara Khan',
        'email' => 'sara@indus.test',
        'subject' => 'Invoice INV-2627-0001 has a wrong address',
        'message' => 'Our new address is 12 Mall Road, Lahore.',
    ]);

    $response->assertSessionHasNoErrors();
    $ticket = Ticket::withoutGlobalScopes()->where('team_id', $team->id)->sole();
    $response->assertRedirect($ticket->publicUrl());

    expect($ticket->channel)->toBe(TicketChannel::WebForm)
        ->and($ticket->customer_name)->toBe('Sara Khan')
        ->and($ticket->contact_id)->toBe($contact->id)
        ->and($ticket->company_id)->toBe($company->id)
        ->and($ticket->messages()->sole()->body)->toContain('12 Mall Road');
});

test('the support form is closed unless it is switched on', function () {
    [, $team] = teamOwner();
    $team->update(['settings' => ['support' => ['enabled' => false, 'token' => 'secret-token']]]);

    $this->get(route('public.support.show', ['token' => 'secret-token']))->assertNotFound();
    $this->get(route('public.support.show', ['token' => 'wrong']))->assertNotFound();
});

test('bots that fill in the hidden field are refused', function () {
    [, $team] = teamOwner();
    $team->update(['settings' => ['support' => ['enabled' => true, 'token' => 'secret-token']]]);

    $this->post(route('public.support.store', ['token' => 'secret-token']), [
        'name' => 'Bot', 'email' => 'bot@spam.test', 'subject' => 'Buy', 'message' => 'Cheap stuff', 'website' => 'http://spam.test',
    ])->assertSessionHasErrors('website');

    expect(Ticket::withoutGlobalScopes()->count())->toBe(0);
});

test('support time is logged on the ticket and its customer', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $ticket = logTicket($this, $user, $team, ['company_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('time.store', ['current_team' => $team->slug]), [
            'date' => '2026-09-30',
            'duration_minutes' => 30,
            'ticket_id' => $ticket->id,
            'is_billable' => true,
        ])
        ->assertSessionHasNoErrors();

    $entry = TimeEntry::withoutGlobalScopes()->where('ticket_id', $ticket->id)->sole();

    expect($entry->company_id)->toBe($company->id);

    $this->actingAs($user)
        ->get(route('tickets.show', ['current_team' => $team->slug, 'ticket' => $ticket->id]))
        ->assertInertia(fn (Assert $page) => $page->component('tickets/Show')->where('loggedMinutes', 30));
});

test('the ticket list shows open tickets and can show mine', function () {
    [$user, $team] = teamOwner();
    $colleague = teamMember($team);
    logTicket($this, $user, $team, ['subject' => 'Mine', 'assignee_id' => $user->id]);
    logTicket($this, $user, $team, ['subject' => 'Theirs', 'assignee_id' => $colleague->id]);
    $closed = logTicket($this, $user, $team, ['subject' => 'Closed']);
    $closed->update(['status' => TicketStatus::Closed]);

    $this->actingAs($user)
        ->get(route('tickets.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Index')
            ->has('tickets.data', 2)
            ->where('summary.open', 2)
            ->where('summary.mine', 1));

    $this->actingAs($user)
        ->get(route('tickets.index', ['current_team' => $team->slug, 'assignee' => 'me']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.subject', 'Mine'));
});

test('the tickets of a customer are listed on its page', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    logTicket($this, $user, $team, ['company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related.tickets.total', 1)
            ->where('related.tickets.items.0.number', 'TKT-0001'));
});

test('tickets are private to the team and the tickets module', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $foreign = logTicket($this, $otherUser, $otherTeam);
    $member = teamMember($team, TeamRole::Member, ['tickets' => 'none']);

    $this->actingAs($user)
        ->get(route('tickets.show', ['current_team' => $team->slug, 'ticket' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($member)
        ->get(route('tickets.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});
