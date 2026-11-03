<?php

namespace App\Http\Controllers\Time;

use App\Enums\BillingMethod;
use App\Enums\TeamRole;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Time\TimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkType;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TimeEntryController extends Controller
{
    /**
     * Show the timesheet of a week, or a filtered list of entries.
     */
    public function index(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();
        $manager = $this->isManager($request->user(), $team);
        $filters = $this->filters($request, $manager);
        $week = $this->weekStart($request->string('week')->toString());

        $query = $this->filteredQuery($filters, $request->user());

        if ($filters['view'] === 'week') {
            DateRange::apply($query, 'date', [$week, $week->addDays(6)]);
        }

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(duration_minutes), 0) as minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END), 0) as billable_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN duration_minutes * hourly_rate / 60 ELSE 0 END), 0) as value')
            ->first();

        $query->with(['user:id,name', 'project:id,number,title', 'task:id,title', 'company:id,name', 'workType:id,name', 'invoice:id,number'])
            ->latest('date')
            ->latest('id');

        return Inertia::render('time/Index', [
            'entries' => $filters['view'] === 'week'
                ? TimeEntryResource::collection($query->get())
                : TimeEntryResource::collection($query->paginate(50)->withQueryString()),
            'filters' => $filters,
            'week' => [
                'start' => $week->toDateString(),
                'previous' => $week->subWeek()->toDateString(),
                'next' => $week->addWeek()->toDateString(),
            ],
            'totals' => [
                'minutes' => (int) $totals?->getAttribute('minutes'),
                'billable_minutes' => (int) $totals?->getAttribute('billable_minutes'),
                'value' => number_format((float) $totals?->getAttribute('value'), 2, '.', ''),
            ],
            'isManager' => $manager,
            'periods' => DateRange::options(),
            'users' => $manager ? TeamOptions::users() : [],
            'projects' => Project::query()->open()->orderBy('title')->get(['id', 'number', 'title', 'company_id', 'billing_method'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => trim(($project->number ?? '').' '.$project->title),
                    'company_id' => $project->company_id,
                    'billable' => $project->billing_method !== BillingMethod::NonBillable,
                ])->values(),
            'workTypes' => TeamOptions::workTypes(),
        ]);
    }

    /**
     * Log time.
     */
    public function store(TimeEntryRequest $request): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $this->ensureWorkOrderOpen($request->integer('work_order_id') ?: null);

        $entry = new TimeEntry($request->safe()->except(['user_id', 'hourly_rate']));
        $entry->team_id = $team->id;
        $entry->user_id = $this->isManager($request->user(), $team) && $request->filled('user_id')
            ? $request->integer('user_id')
            : $request->user()->id;
        $this->prepare($entry, $request->validated('hourly_rate'));
        $entry->save();

        return back();
    }

    /**
     * Change an entry (not after it was invoiced).
     */
    public function update(TimeEntryRequest $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->ensureEditable($request, $timeEntry);
        $this->ensureWorkOrderOpen($request->integer('work_order_id') ?: null);

        $timeEntry->fill($request->safe()->except(['user_id', 'hourly_rate']));
        $this->prepare($timeEntry, $request->validated('hourly_rate'));
        $timeEntry->save();

        return back();
    }

    /**
     * Delete an entry (not after it was invoiced).
     */
    public function destroy(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->ensureEditable($request, $timeEntry);

        $timeEntry->delete();

        return back();
    }

    /**
     * Start the timer (a running timer of the user is stopped first).
     */
    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'task_id' => ['nullable', 'integer', TeamRule::exists('tasks', softDeletes: true)],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'work_type_id' => ['nullable', 'integer', TeamRule::exists('work_types')],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $this->stopRunning($request->user());

            $entry = new TimeEntry($validated);
            $entry->team_id = app(CurrentTeam::class)->team()->id;
            $entry->user_id = $request->user()->id;
            $entry->date = CarbonImmutable::today();
            $entry->started_at = CarbonImmutable::now();
            $entry->duration_minutes = 0;
            $this->prepare($entry, null);
            $entry->save();
        });

        return back();
    }

    /**
     * Stop the running timer.
     */
    public function stop(Request $request): RedirectResponse
    {
        $this->stopRunning($request->user());

        return back();
    }

    /**
     * Download the (filtered) time entries as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $team = app(CurrentTeam::class)->team();
        $filters = $this->filters($request, $this->isManager($request->user(), $team));
        $query = $this->filteredQuery($filters, $request->user())
            ->with(['user:id,name', 'project:id,number,title', 'task:id,title', 'company:id,name', 'workType:id,name', 'invoice:id,number'])
            ->orderBy('date')
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'User', 'Customer', 'Project', 'Task', 'Work type', 'Description', 'Hours', 'Billable', 'Rate', 'Value', 'Invoice'], ',', '"', '');

            $query->chunk(500, function (Collection $entries) use ($out) {
                foreach ($entries as $entry) {
                    /** @var TimeEntry $entry */
                    fputcsv($out, [
                        $entry->date->toDateString(),
                        $entry->user?->name,
                        $entry->company?->name,
                        $entry->project ? trim(($entry->project->number ?? '').' '.$entry->project->title) : '',
                        $entry->task?->title,
                        $entry->workType?->name,
                        $entry->description,
                        number_format($entry->duration_minutes / 60, 2, '.', ''),
                        $entry->is_billable ? 'Yes' : 'No',
                        $entry->hourly_rate,
                        $entry->is_billable ? $entry->value() : '0.00',
                        $entry->invoice?->number,
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'time-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Read the filters (members only see their own time).
     *
     * @return array{view: string, user: string, project: int|null, billable: string|null, invoiced: string|null, period: string|null, from: string|null, to: string|null}
     */
    protected function filters(Request $request, bool $manager): array
    {
        $user = $request->string('user')->toString();
        $billable = $request->string('billable')->toString();
        $invoiced = $request->string('invoiced')->toString();

        return [
            'view' => $request->string('view')->toString() === 'list' ? 'list' : 'week',
            'user' => $manager && ($user === 'all' || ctype_digit($user)) ? $user : 'me',
            'project' => $request->integer('project') ?: null,
            'billable' => in_array($billable, ['yes', 'no'], true) ? $billable : null,
            'invoiced' => in_array($invoiced, ['yes', 'no'], true) ? $invoiced : null,
            ...DateRange::filters($request),
        ];
    }

    /**
     * Get the entries matching the filters.
     *
     * @param  array{view: string, user: string, project: int|null, billable: string|null, invoiced: string|null, period: string|null, from: string|null, to: string|null}  $filters
     * @return Builder<TimeEntry>
     */
    protected function filteredQuery(array $filters, User $user): Builder
    {
        $query = TimeEntry::query()
            ->when($filters['user'] === 'me', fn (Builder $query) => $query->where('user_id', $user->id))
            ->when(ctype_digit($filters['user']), fn (Builder $query) => $query->where('user_id', (int) $filters['user']))
            ->when($filters['project'], fn (Builder $query, int $project) => $query->where('project_id', $project))
            ->when($filters['billable'], fn (Builder $query, string $billable) => $query->where('is_billable', $billable === 'yes'))
            ->when($filters['invoiced'] === 'yes', fn (Builder $query) => $query->whereNotNull('invoice_id'))
            ->when($filters['invoiced'] === 'no', fn (Builder $query) => $query->whereNull('invoice_id'));

        if ($filters['view'] === 'list') {
            DateRange::apply($query, 'date', DateRange::resolve($filters));
        }

        return $query;
    }

    /**
     * Link the entry to the customer of its project and work out the hourly rate.
     */
    protected function prepare(TimeEntry $entry, mixed $rate): void
    {
        if ($entry->task_id !== null && $entry->project_id === null) {
            $entry->project_id = Task::query()->whereKey($entry->task_id)->value('project_id');
        }

        // Support time belongs to the ticket's customer (and project, if any).
        if ($entry->ticket_id !== null) {
            $ticket = Ticket::query()->withTrashed()->find($entry->ticket_id);
            $entry->project_id ??= $ticket?->project_id;
            $entry->company_id = $ticket?->company_id;
        }

        // Hours of a work order belong to its project and customer.
        $workOrder = $entry->work_order_id !== null ? WorkOrder::query()->withTrashed()->find($entry->work_order_id) : null;

        if ($workOrder !== null) {
            $entry->project_id = $workOrder->project_id;
            $entry->task_id = null;
        }

        $project = $entry->project_id !== null ? Project::query()->withTrashed()->find($entry->project_id) : null;

        if ($project !== null) {
            $entry->company_id = $project->company_id;

            if ($project->billing_method === BillingMethod::NonBillable) {
                $entry->is_billable = false;
            }
        }

        if ($workOrder?->company_id !== null) {
            $entry->company_id = $workOrder->company_id;
        }

        $workTypeRate = $entry->work_type_id !== null ? WorkType::query()->whereKey($entry->work_type_id)->value('hourly_rate') : null;

        $entry->hourly_rate = match (true) {
            $rate !== null && $rate !== '' => number_format((float) $rate, 2, '.', ''),
            $workTypeRate !== null => (string) $workTypeRate,
            $project?->hourly_rate !== null => $project->hourly_rate,
            default => '0',
        };
    }

    /**
     * Stop the user's running timers.
     */
    protected function stopRunning(User $user): void
    {
        TimeEntry::query()->where('user_id', $user->id)->running()->get()->each(fn (TimeEntry $entry) => $entry->stop());
    }

    /**
     * Only the owner of an entry (or an admin) may change it, and invoiced time is locked
     * (as is the time of a completed work order).
     */
    protected function ensureEditable(Request $request, TimeEntry $entry): void
    {
        abort_unless(
            $entry->user_id === $request->user()->id || $this->isManager($request->user(), app(CurrentTeam::class)->team()),
            403,
        );

        if ($entry->invoice_id !== null) {
            throw ValidationException::withMessages(['entry' => 'This time is already on an invoice.']);
        }

        $this->ensureWorkOrderOpen($entry->work_order_id);
    }

    /**
     * The hours of a completed (or signed) work order no longer change.
     */
    protected function ensureWorkOrderOpen(?int $workOrderId): void
    {
        if ($workOrderId !== null
            && WorkOrder::query()->withTrashed()->whereKey($workOrderId)->where('status', '!=', WorkOrderStatus::Open)->exists()) {
            throw ValidationException::withMessages(['entry' => 'This work order is completed. Reopen it to change its hours.']);
        }
    }

    /**
     * Owners and admins may see and change everybody's time.
     */
    protected function isManager(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * The Monday of the requested week (this week by default).
     */
    protected function weekStart(string $date): CarbonImmutable
    {
        try {
            $day = $date !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $date) : null;
        } catch (Throwable) {
            $day = null;
        }

        return ($day ?: CarbonImmutable::today())->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }
}
