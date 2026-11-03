<?php

namespace App\Support;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\CalendarEvent;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TeamNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tells team members what happened, through the notification bell in the header.
 * Nobody is told about what they did themselves, and only members who may see the
 * module a notification is about receive it.
 */
final class Notifier
{
    /**
     * A ticket was given to someone.
     */
    public static function ticketAssigned(Ticket $ticket, ?User $by): void
    {
        $team = self::team($ticket->team_id);

        self::send(
            $team,
            Module::Tickets,
            [$ticket->assignee()->first()],
            'ticket.assigned',
            "Ticket {$ticket->number} was assigned to you",
            $ticket->subject,
            route('tickets.show', ['current_team' => $team->slug, 'ticket' => $ticket->id], false),
            $by,
        );
    }

    /**
     * The customer answered on a ticket: tell who handles it, or everybody who handles tickets.
     */
    public static function ticketReplied(Ticket $ticket, TicketMessage $message): void
    {
        $team = self::team($ticket->team_id);
        $assignee = $ticket->assignee()->first();

        self::send(
            $team,
            Module::Tickets,
            $assignee !== null ? [$assignee] : self::members($team, Module::Tickets),
            'ticket.replied',
            ($message->author_name ?: 'The customer')." replied to ticket {$ticket->number}",
            Str::limit($message->body, 140),
            route('tickets.show', ['current_team' => $team->slug, 'ticket' => $ticket->id], false),
        );
    }

    /**
     * A customer asked a question through the public support form.
     */
    public static function supportTicket(Ticket $ticket): void
    {
        $team = self::team($ticket->team_id);

        self::send(
            $team,
            Module::Tickets,
            self::members($team, Module::Tickets),
            'ticket.created',
            "New ticket {$ticket->number} from the support form",
            $ticket->subject.' · '.$ticket->customerLabel(),
            route('tickets.show', ['current_team' => $team->slug, 'ticket' => $ticket->id], false),
        );
    }

    /**
     * A task was given to someone.
     */
    public static function taskAssigned(Task $task, ?User $by): void
    {
        $team = self::team($task->team_id);

        self::send(
            $team,
            Module::Projects,
            [$task->assignee()->first()],
            'task.assigned',
            "Task for you: {$task->title}",
            $task->due_date !== null ? 'Due '.$task->due_date->format('d M Y') : null,
            $task->project_id !== null
                ? route('projects.show', ['current_team' => $team->slug, 'project' => $task->project_id], false)
                : route('tasks.index', ['current_team' => $team->slug], false),
            $by,
        );
    }

    /**
     * A work order was given to someone.
     */
    public static function workOrderAssigned(WorkOrder $workOrder, ?User $by): void
    {
        $team = self::team($workOrder->team_id);

        self::send(
            $team,
            Module::WorkOrders,
            [$workOrder->assignee()->first()],
            'work_order.assigned',
            "Work order {$workOrder->number} is yours",
            $workOrder->title.' · '.$workOrder->date->format('d M Y'),
            route('work-orders.show', ['current_team' => $team->slug, 'work_order' => $workOrder->id], false),
            $by,
        );
    }

    /**
     * The customer signed a work order through the link.
     */
    public static function workOrderSigned(WorkOrder $workOrder): void
    {
        $team = self::team($workOrder->team_id);

        self::send(
            $team,
            Module::WorkOrders,
            [$workOrder->assignee()->first(), $workOrder->creator()->first()],
            'work_order.signed',
            "Work order {$workOrder->number} was signed",
            "Signed online by {$workOrder->signed_by_name}",
            route('work-orders.show', ['current_team' => $team->slug, 'work_order' => $workOrder->id], false),
        );
    }

    /**
     * The customer accepted or declined a quotation online.
     */
    public static function quotationAnswered(Quotation $quotation, bool $accepted): void
    {
        $team = self::team($quotation->team_id);

        self::send(
            $team,
            Module::Quotations,
            [$quotation->creator()->first(), $quotation->deal?->responsibleUser()->first()],
            $accepted ? 'quotation.accepted' : 'quotation.declined',
            "Quotation {$quotation->number} was ".($accepted ? 'accepted' : 'declined'),
            $accepted
                ? trim($quotation->customerLabel().($quotation->accepted_by_name ? " · signed by {$quotation->accepted_by_name}" : ''))
                : $quotation->customerLabel().($quotation->decline_reason ? ' · '.Str::limit($quotation->decline_reason, 120) : ''),
            route('quotations.show', ['current_team' => $team->slug, 'quotation' => $quotation->id], false),
        );
    }

    /**
     * A calendar event is about to start: remind the organizer and the attendees.
     */
    public static function eventReminder(CalendarEvent $event): void
    {
        $team = self::team($event->team_id);

        self::send(
            $team,
            Module::Calendar,
            [$event->organizer()->first(), ...$event->attendees()->get()->all()],
            'event.reminder',
            "Reminder: {$event->title}",
            trim(($event->all_day ? 'All day, '.$event->starts_at->format('d M') : 'At '.$event->starts_at->format('H:i')).($event->location ? ' · '.$event->location : '')),
            route('calendar.index', ['current_team' => $team->slug, 'date' => $event->starts_at->toDateString()], false),
        );
    }

    /**
     * Send the notification to the users who may see the module (except the one who caused it).
     *
     * @param  iterable<User|null>  $users
     */
    private static function send(Team $team, Module $module, iterable $users, string $kind, string $title, ?string $body, string $url, ?User $except = null): void
    {
        $recipients = collect($users)
            ->filter(fn ($user) => $user instanceof User && $user->id !== $except?->id)
            ->unique('id')
            ->filter(fn (User $user) => $user->canAccessModule($team, $module, AccessLevel::Read))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TeamNotification($team->id, $kind, $title, $body, $url));
    }

    /**
     * The members who work in a module (with full access to it).
     *
     * @return Collection<int, User>
     */
    private static function members(Team $team, Module $module): Collection
    {
        return $team->members()->get()->filter(fn (User $user) => $user->canAccessModule($team, $module, AccessLevel::Write))->values();
    }

    /**
     * The team of a record (without a query when it is the current team).
     */
    private static function team(int $teamId): Team
    {
        $current = app(CurrentTeam::class)->get();

        return $current !== null && $current->id === $teamId ? $current : Team::query()->findOrFail($teamId);
    }
}
