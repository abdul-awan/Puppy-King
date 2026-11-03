<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\DealStatus;
use App\Enums\EventStatus;
use App\Enums\Module;
use App\Enums\QuotationStatus;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Models\CalendarEvent;
use App\Models\DailyExpense;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\FiscalYear;
use App\Support\PettyCash;
use App\Support\Reports\ReceivablesReport;
use App\Support\Reports\RevenueReport;
use App\Support\Reports\Sql;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start page: the key figures of the company and the user's work for today.
 * Every block is only filled when the user may see the module it comes from.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = app(CurrentTeam::class)->team();
        $user = $request->user();
        $today = CarbonImmutable::today();
        $can = fn (Module $module): bool => $user->canAccessModule($team, $module, AccessLevel::Read);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'currency' => $team->teamSettings()->currency(),
            'sales' => $can(Module::Invoicing) ? $this->sales($team, $today) : null,
            'pipeline' => $can(Module::Deals) ? $this->pipeline($team, $today) : null,
            'quotations' => $can(Module::Quotations) ? $this->quotations($today) : null,
            'bills' => $can(Module::Expenses) ? $this->bills() : null,
            'dailyExpenses' => $can(Module::DailyExpenses) ? $this->dailyExpenses($today) : null,
            'payroll' => $can(Module::Payroll) ? $this->payroll() : null,
            'time' => $can(Module::TimeTracking) ? $this->time($user, $today) : null,
            'tasks' => $can(Module::Projects) ? $this->tasks($user, $today) : null,
            'events' => $can(Module::Calendar) ? $this->events($user, $today) : null,
            'tickets' => $can(Module::Tickets) ? $this->tickets($user) : null,
            'workOrders' => $can(Module::WorkOrders) ? $this->workOrders($user) : null,
        ]);
    }

    /**
     * Revenue, the last twelve months and what customers owe.
     *
     * @return array<string, mixed>
     */
    protected function sales(Team $team, CarbonImmutable $today): array
    {
        $fiscalYear = FiscalYear::for($today, $team->teamSettings()->fiscalYearStartMonth());
        $lastMonth = $today->subMonthNoOverflow();
        $receivables = ReceivablesReport::aging($today);

        return [
            'this_month' => RevenueReport::totals($today->startOfMonth(), $today->endOfMonth())['net'],
            'last_month' => RevenueReport::totals($lastMonth->startOfMonth(), $lastMonth->endOfMonth())['net'],
            'fiscal_year' => RevenueReport::totals($fiscalYear->start(), $today)['net'],
            'fiscal_year_label' => $fiscalYear->label(),
            'months' => RevenueReport::perMonth($today->subMonthsNoOverflow(11)->startOfMonth(), $today->endOfMonth()),
            'outstanding' => $receivables['total'],
            'outstanding_count' => $receivables['invoices'],
            'overdue' => $receivables['overdue'],
            'overdue_count' => Invoice::query()->overdue()->count(),
            'overdue_invoices' => Invoice::query()
                ->overdue()
                ->orderBy('due_date')
                ->limit(5)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'customer' => $invoice->customerLabel(),
                    'balance' => $invoice->balanceDue(),
                    'currency' => $invoice->currency,
                    'days_overdue' => $invoice->daysOverdue(),
                ])
                ->values(),
        ];
    }

    /**
     * The open sales pipeline and the deals expected to close this month.
     *
     * @return array<string, mixed>
     */
    protected function pipeline(Team $team, CarbonImmutable $today): array
    {
        $currency = $team->teamSettings()->currency();

        $open = Deal::query()
            ->where('deals.status', DealStatus::Open)
            ->where('deals.currency', $currency)
            ->join('deal_phases', 'deal_phases.id', '=', 'deals.phase_id')
            ->toBase()
            ->selectRaw('COUNT(*) as deals')
            ->selectRaw('SUM(deals.value) as value')
            ->selectRaw('SUM(deals.value * COALESCE(deals.probability, deal_phases.probability) / 100) as weighted')
            ->first();

        $won = DateRange::apply(
            Deal::query()->where('status', DealStatus::Won)->where('currency', $currency),
            'closed_at',
            [$today->startOfMonth(), $today->endOfMonth()],
        );

        return [
            'open_count' => (int) ($open->deals ?? 0),
            'open_value' => Sql::money($open->value ?? 0),
            'weighted' => Sql::money($open->weighted ?? 0),
            'won_count' => (clone $won)->count(),
            'won_value' => Sql::money((clone $won)->sum('value')),
            'closing' => Deal::query()
                ->with('company:id,name')
                ->where('status', DealStatus::Open)
                ->whereNotNull('expected_close_date')
                ->whereDate('expected_close_date', '<=', $today->endOfMonth())
                ->orderBy('expected_close_date')
                ->limit(5)
                ->get()
                ->map(fn (Deal $deal) => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'company' => $deal->company?->name,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'expected_close_date' => $deal->expected_close_date?->toDateString(),
                ])
                ->values(),
        ];
    }

    /**
     * Quotations the customers have not answered yet, the ones expiring first on top.
     *
     * @return array<string, mixed>
     */
    protected function quotations(CarbonImmutable $today): array
    {
        $awaiting = Quotation::query()
            ->where('status', QuotationStatus::Sent)
            ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));

        return [
            'count' => (clone $awaiting)->count(),
            'value' => Sql::money((clone $awaiting)->sum(DB::raw('(subtotal - discount_amount) * exchange_rate'))),
            'items' => (clone $awaiting)
                ->orderByRaw('CASE WHEN valid_until IS NULL THEN 1 ELSE 0 END')
                ->orderBy('valid_until')
                ->limit(5)
                ->get()
                ->map(fn (Quotation $quotation) => [
                    'id' => $quotation->id,
                    'number' => $quotation->number,
                    'customer' => $quotation->customerLabel(),
                    'total' => $quotation->total,
                    'currency' => $quotation->currency,
                    'valid_until' => $quotation->valid_until?->toDateString(),
                ])
                ->values(),
        ];
    }

    /**
     * Supplier bills that still have to be paid.
     *
     * @return array<string, mixed>
     */
    protected function bills(): array
    {
        $balance = 'SUM((total - income_tax_withheld - sales_tax_withheld - amount_paid) * exchange_rate)';
        $unpaid = Expense::query()->unpaid();

        return [
            'count' => (clone $unpaid)->count(),
            'total' => Sql::money((clone $unpaid)->toBase()->selectRaw("{$balance} as balance")->value('balance')),
            'overdue_count' => (clone $unpaid)->whereNotNull('due_date')->whereDate('due_date', '<', today())->count(),
        ];
    }

    /**
     * What was spent today and this month, and the cash in the cash box.
     *
     * @return array{today: string, month: string, cash_in_hand: string|null}
     */
    protected function dailyExpenses(CarbonImmutable $today): array
    {
        return [
            'today' => Sql::money(DailyExpense::query()->whereDate('date', $today)->sum('amount')),
            'month' => Sql::money(DateRange::apply(DailyExpense::query(), 'date', [$today->startOfMonth(), $today->endOfMonth()])->sum('amount')),
            'cash_in_hand' => PettyCash::inUse() ? (string) PettyCash::balance() : null,
        ];
    }

    /**
     * The latest payroll and the number of employees.
     *
     * @return array{employees: int, latest: array{id: int, label: string, status: string, status_label: string, net: string}|null}
     */
    protected function payroll(): array
    {
        $latest = PayrollRun::query()->latest('period')->first();

        return [
            'employees' => Employee::query()->current()->count(),
            'latest' => $latest !== null ? [
                'id' => $latest->id,
                'label' => $latest->label(),
                'status' => $latest->status->value,
                'status_label' => $latest->displayStatus()['label'],
                'net' => $latest->net,
            ] : null,
        ];
    }

    /**
     * The user's hours of today and this week.
     *
     * @return array{today: int, week: int, week_billable: int}
     */
    protected function time(User $user, CarbonImmutable $today): array
    {
        $week = DateRange::apply(
            TimeEntry::query()->where('user_id', $user->id),
            'date',
            [$today->startOfWeek(CarbonInterface::MONDAY), $today->endOfWeek(CarbonInterface::SUNDAY)],
        );

        return [
            'today' => (int) DateRange::apply(TimeEntry::query()->where('user_id', $user->id), 'date', [$today, $today])->sum('duration_minutes'),
            'week' => (int) (clone $week)->sum('duration_minutes'),
            'week_billable' => (int) (clone $week)->where('is_billable', true)->sum('duration_minutes'),
        ];
    }

    /**
     * The user's open tasks that are due or planned for today (or overdue).
     *
     * @return array<string, mixed>
     */
    protected function tasks(User $user, CarbonImmutable $today): array
    {
        $tasks = Task::query()
            ->open()
            ->where('assignee_id', $user->id)
            ->where(fn (Builder $query) => $query
                ->whereDate('due_date', '<=', $today)
                ->orWhereDate('planned_date', $today));

        return [
            'count' => (clone $tasks)->count(),
            'items' => (clone $tasks)
                ->with('project:id,title')
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date')
                ->limit(8)
                ->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project' => $task->project?->only(['id', 'title']),
                    'due_date' => $task->due_date?->toDateString(),
                    'is_overdue' => $task->isOverdue(),
                    'priority' => $task->priority->value,
                    'priority_label' => $task->priority->label(),
                    'priority_color' => $task->priority->color(),
                ])
                ->values(),
        ];
    }

    /**
     * The user's planned calendar events of today.
     *
     * @return list<array<string, mixed>>
     */
    protected function events(User $user, CarbonImmutable $today): array
    {
        return array_values(CalendarEvent::query()
            ->forUser($user->id)
            ->between($today->startOfDay(), $today->endOfDay())
            ->where('status', EventStatus::Planned)
            ->orderBy('all_day', 'desc')
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type->value,
                'type_label' => $event->type->label(),
                'color' => $event->type->color(),
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'all_day' => $event->all_day,
                'location' => $event->location,
            ])
            ->all());
    }

    /**
     * The open tickets the user handles, and the new ones nobody took yet.
     *
     * @return array<string, mixed>
     */
    protected function tickets(User $user): array
    {
        $mine = Ticket::query()->open()->where('assignee_id', $user->id);

        return [
            'count' => (clone $mine)->count(),
            'unassigned' => Ticket::query()->where('status', TicketStatus::New)->whereNull('assignee_id')->count(),
            'items' => (clone $mine)
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Ticket $ticket) => [
                    'id' => $ticket->id,
                    'number' => $ticket->number,
                    'subject' => $ticket->subject,
                    'customer' => $ticket->customerLabel(),
                    'status_label' => $ticket->status->label(),
                    'status_color' => $ticket->status->color(),
                ])
                ->values(),
        ];
    }

    /**
     * The open work orders of the user.
     *
     * @return array<string, mixed>
     */
    protected function workOrders(User $user): array
    {
        $mine = WorkOrder::query()->where('status', WorkOrderStatus::Open)->where('assignee_id', $user->id);

        return [
            'count' => (clone $mine)->count(),
            'items' => (clone $mine)
                ->with(['company:id,name', 'contact:id,first_name,last_name'])
                ->orderBy('date')
                ->limit(5)
                ->get()
                ->map(fn (WorkOrder $workOrder) => [
                    'id' => $workOrder->id,
                    'number' => $workOrder->number,
                    'title' => $workOrder->title,
                    'customer' => $workOrder->customerLabel(),
                    'date' => $workOrder->date->toDateString(),
                ])
                ->values(),
        ];
    }
}
