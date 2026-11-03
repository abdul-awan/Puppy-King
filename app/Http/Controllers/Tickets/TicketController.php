<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\TicketConversation;
use App\Enums\DocumentType;
use App\Enums\TaskPriority;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\TicketRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TimeEntryResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\CurrentTeam;
use App\Support\DocumentNumberer;
use App\Support\Notifier;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Display the tickets: by default the open ones.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $assignee = $request->string('assignee')->toString();

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($status, ['all', ...array_column(TicketStatus::cases(), 'value')], true) ? $status : 'open',
            'assignee' => in_array($assignee, ['me', 'unassigned'], true) || ctype_digit($assignee) ? $assignee : 'all',
            'priority' => TaskPriority::tryFrom($request->string('priority')->toString())?->value,
        ];

        $tickets = Ticket::query()
            ->with(['company:id,name', 'contact:id,first_name,last_name', 'assignee:id,name'])
            ->withCount('messages')
            ->search($filters['search'])
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(! in_array($filters['status'], ['open', 'all'], true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $request->user()->id))
            ->when($filters['assignee'] === 'unassigned', fn (Builder $query) => $query->whereNull('assignee_id'))
            ->when(ctype_digit($filters['assignee']), fn (Builder $query) => $query->where('assignee_id', (int) $filters['assignee']))
            ->when($filters['priority'], fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'open' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('tickets/Index', [
            'tickets' => TicketResource::collection($tickets),
            'filters' => $filters,
            'users' => TeamOptions::users(),
            'statuses' => TicketStatus::options(),
            'priorities' => TaskPriority::options(),
            'summary' => [
                'new' => Ticket::query()->where('status', TicketStatus::New)->count(),
                'open' => Ticket::query()->open()->count(),
                'waiting' => Ticket::query()->where('status', TicketStatus::Waiting)->count(),
                'mine' => Ticket::query()->open()->where('assignee_id', $request->user()->id)->count(),
            ],
        ]);
    }

    /**
     * Show the form for a new ticket (e.g. after a phone call).
     */
    public function create(Request $request): Response
    {
        $contact = $request->integer('contact') ? Contact::query()->with('companies')->find($request->integer('contact')) : null;
        $company = $request->integer('company') ? Company::query()->find($request->integer('company')) : $contact?->companies->first();

        return Inertia::render('tickets/Create', [
            'prefill' => [
                'company' => $company?->only(['id', 'name']),
                'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a new ticket with the customer's question.
     */
    public function store(TicketRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $ticket = DB::transaction(function () use ($request, $numberer, $team) {
            $ticket = new Ticket([...$request->ticket(), 'created_by' => $request->user()->id]);
            $ticket->team_id = $team->id;
            $ticket->number = $numberer->next($team, DocumentType::Ticket, CarbonImmutable::today());
            $ticket->save();

            if ($request->filled('description')) {
                $message = new TicketMessage(['author_name' => $ticket->customerLabel(), 'body' => $request->string('description')->toString()]);
                $message->team_id = $ticket->team_id;
                $message->ticket_id = $ticket->id;
                $message->save();
            }

            return $ticket;
        });

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        foreach ($files as $file) {
            $ticket->attachFile($file, $request->user());
        }

        Notifier::ticketAssigned($ticket, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket :number created.', ['number' => $ticket->number])]);

        return to_route('tickets.show', $ticket);
    }

    /**
     * Display the ticket with its conversation.
     */
    public function show(Ticket $ticket): Response
    {
        $ticket->load(['messages.user:id,name', 'company:id,name,email', 'contact', 'project:id,title', 'assignee:id,name']);

        return Inertia::render('tickets/Show', [
            'ticket' => TicketResource::make($ticket),
            'timeEntries' => TimeEntryResource::collection($ticket->timeEntries()->with(['user:id,name', 'workType:id,name'])->latest('date')->latest('id')->get()),
            'loggedMinutes' => (int) $ticket->timeEntries()->sum('duration_minutes'),
            'attachments' => AttachmentResource::collection($ticket->attachments()->with('user:id,name')->get()),
            'timeline' => Timeline::for($ticket),
            'workTypes' => TeamOptions::workTypes(),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Change the ticket's details (customer, who handles it, status, priority…).
     */
    public function update(TicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update($request->ticket());

        if ($ticket->wasChanged('assignee_id')) {
            Notifier::ticketAssigned($ticket, $request->user());
        }

        return back();
    }

    /**
     * Change only the status or the person handling the ticket.
     */
    public function quickUpdate(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', TeamRule::member()],
        ]);

        $ticket->update($validated);

        if ($ticket->wasChanged('assignee_id')) {
            Notifier::ticketAssigned($ticket, $request->user());
        }

        return back();
    }

    /**
     * Answer the customer or add an internal note.
     */
    public function reply(Request $request, Ticket $ticket, TicketConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'internal' => ['boolean'],
            'email' => ['boolean'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);

        $message = $conversation->reply(
            $ticket,
            $request->user(),
            $validated['body'],
            internal: $request->boolean('internal'),
            email: $request->boolean('email', true),
            status: isset($validated['status']) ? TicketStatus::from($validated['status']) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $message->is_internal
            ? __('Note added.')
            : ($message->emailed_at ? __('Reply sent to :to.', ['to' => $ticket->customerEmail()]) : __('Reply added. The customer sees it through the ticket link.'))]);

        return back();
    }

    /**
     * Delete the ticket.
     */
    public function destroy(Ticket $ticket): RedirectResponse
    {
        $ticket->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket deleted.')]);

        return to_route('tickets.index');
    }

    /**
     * Get the option lists for the ticket forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'users' => TeamOptions::users(),
            'statuses' => TicketStatus::options(),
            'priorities' => TaskPriority::options(),
            'channels' => TicketChannel::options(),
        ];
    }
}
