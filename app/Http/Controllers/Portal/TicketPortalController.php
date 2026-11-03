<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Tickets\TicketConversation;
use App\Enums\DocumentType;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\OpensActiveTeams;
use App\Models\Contact;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\DocumentNumberer;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's side of the helpdesk: the conversation of a ticket (opened through its
 * secret link) and the public support form of a company.
 */
class TicketPortalController extends Controller
{
    use OpensActiveTeams;

    /**
     * Show the conversation to the customer (internal notes stay hidden).
     */
    public function show(string $token): Response
    {
        $ticket = $this->find($token);
        $team = $ticket->team()->firstOrFail();

        return Inertia::render('public/Ticket', [
            'ticket' => [
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'customer_label' => $ticket->customerLabel(),
                'created_at' => $ticket->created_at?->toIso8601String(),
                'messages' => $ticket->messages()->with('user:id,name')->where('is_internal', false)->get()
                    ->map(fn (TicketMessage $message) => [
                        'id' => $message->id,
                        'body' => $message->body,
                        'from_customer' => $message->fromCustomer(),
                        'author' => $message->user->name ?? $message->author_name ?? 'You',
                        'created_at' => $message->created_at?->toIso8601String(),
                    ])->values(),
            ],
            'company' => $this->companyName($team),
            'replyUrl' => route('public.tickets.reply', ['token' => $token]),
        ]);
    }

    /**
     * The customer adds a message.
     */
    public function reply(Request $request, string $token, TicketConversation $conversation): RedirectResponse
    {
        $ticket = $this->find($token);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $conversation->customerReply($ticket, $validated['name'] ?: $ticket->customerLabel(), $validated['body']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you, we received your message.')]);

        return back();
    }

    /**
     * Show a company's public support form.
     */
    public function supportForm(string $token): Response
    {
        $team = $this->supportTeam($token);

        return Inertia::render('public/SupportForm', [
            'company' => $this->companyName($team),
            'submitUrl' => route('public.support.store', ['token' => $token]),
        ]);
    }

    /**
     * Create a ticket from the support form and show the customer its conversation.
     */
    public function submit(Request $request, string $token, DocumentNumberer $numberer): RedirectResponse
    {
        $team = $this->supportTeam($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:20000'],
            // A field people do not see; bots fill it in.
            'website' => ['prohibited'],
        ]);

        $ticket = DB::transaction(function () use ($validated, $team, $numberer) {
            // Link the ticket to the CRM when the address is known.
            $contact = Contact::query()
                ->withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->where('email', $validated['email'])
                ->first();

            $companyName = $validated['company'] ?? null;

            $ticket = new Ticket([
                'subject' => $validated['subject'],
                'contact_id' => $contact?->id,
                'company_id' => $contact?->companies()->withoutGlobalScopes()->value('companies.id'),
                'status' => TicketStatus::New,
                'channel' => TicketChannel::WebForm,
                'customer_name' => trim($validated['name'].($companyName ? ' ('.$companyName.')' : '')),
                'customer_email' => $validated['email'],
            ]);
            $ticket->team_id = $team->id;
            $ticket->number = $numberer->next($team, DocumentType::Ticket, CarbonImmutable::today());
            $ticket->save();

            $message = new TicketMessage(['author_name' => $validated['name'], 'body' => $validated['message']]);
            $message->team_id = $team->id;
            $message->ticket_id = $ticket->id;
            $message->save();

            $ticket->forceFill(['last_customer_message_at' => now()])->save();

            return $ticket;
        });

        Notifier::supportTicket($ticket);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you! We received your question as ticket :number.', ['number' => $ticket->number])]);

        return redirect()->to($ticket->publicUrl());
    }

    /**
     * Find a ticket by its secret link and scope the request to its team.
     */
    protected function find(string $token): Ticket
    {
        $ticket = Ticket::query()
            ->withoutGlobalScopes()
            ->where('public_token', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->openTeam($ticket->team()->firstOrFail());

        return $ticket;
    }

    /**
     * Find the team whose support form this is (only when the form is switched on).
     */
    protected function supportTeam(string $token): Team
    {
        $team = Team::query()
            ->where('settings->support->token', $token)
            ->first();

        if (! $team instanceof Team || ! $team->teamSettings()->get('support.enabled') || $team->teamSettings()->get('support.token') !== $token) {
            abort(404);
        }

        $this->openTeam($team);

        return $team;
    }

    /**
     * The name the customer knows the company by.
     */
    protected function companyName(Team $team): string
    {
        return $team->defaultDepartment()?->displayName() ?? $team->name;
    }
}
