<?php

namespace App\Http\Controllers\Projects;

use App\Actions\Projects\InvoiceProject;
use App\Enums\BillingMethod;
use App\Enums\DocumentType;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\ProjectMaterialResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TimeEntryResource;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\Quotation;
use App\Models\TimeEntry;
use App\Support\CurrentTeam;
use App\Support\DocumentNumberer;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of the projects.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($status, ['all', ...array_column(ProjectStatus::cases(), 'value')], true) ? $status : 'open',
            'company' => $request->integer('company') ?: null,
            'responsible' => $request->integer('responsible') ?: null,
        ];

        $projects = Project::query()
            ->with(['company:id,name', 'contact:id,first_name,last_name', 'responsibleUser:id,name'])
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done)])
            ->withSum('timeEntries', 'duration_minutes')
            ->search($filters['search'])
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(! in_array($filters['status'], ['open', 'all'], true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['company'], fn (Builder $query, int $company) => $query->where('company_id', $company))
            ->when($filters['responsible'], fn (Builder $query, int $user) => $query->where('responsible_user_id', $user))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'filters' => $filters,
            'statuses' => ProjectStatus::options(),
            'users' => TeamOptions::users(),
            'summary' => [
                'open_count' => Project::query()->open()->count(),
                'overdue_count' => Project::query()->open()->whereDate('due_date', '<', today())->count(),
                'unbilled_value' => $this->unbilledValue(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new project (optionally from a deal or an accepted quotation).
     */
    public function create(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();
        $quotation = $request->integer('quotation') ? Quotation::query()->with(['company:id,name', 'contact'])->find($request->integer('quotation')) : null;
        $deal = $request->integer('deal') ? Deal::query()->with(['company:id,name', 'contact'])->find($request->integer('deal')) : null;
        $company = $quotation->company ?? $deal->company ?? ($request->integer('company') ? Company::query()->find($request->integer('company')) : null);
        $contact = $quotation->contact ?? $deal?->contact;

        return Inertia::render('projects/Create', [
            'prefill' => [
                'title' => $quotation->title ?? $deal?->title,
                'company' => $company?->only(['id', 'name']),
                'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
                'deal' => ($deal ?? $quotation?->deal)?->only(['id', 'title']),
                'quotation' => $quotation?->only(['id', 'number']),
                'billing_method' => $quotation ? BillingMethod::FixedPrice->value : BillingMethod::TimeAndMaterials->value,
                'budget_amount' => $quotation ? number_format((float) $quotation->subtotal - (float) $quotation->discount_amount, 2, '.', '') : null,
                'currency' => $quotation->currency ?? $company->currency ?? $team->teamSettings()->currency(),
                'start_date' => CarbonImmutable::today()->toDateString(),
                'responsible_user_id' => $request->user()->id,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(ProjectRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $project = DB::transaction(function () use ($request, $numberer, $team) {
            $project = new Project([...$request->project(), 'created_by' => $request->user()->id]);
            $project->team_id = $team->id;
            $project->number = $numberer->next($team, DocumentType::Project, CarbonImmutable::today());
            $project->completed_at = $project->status === ProjectStatus::Done ? now() : null;
            $project->save();

            $project->members()->sync($this->memberIds($request));

            return $project;
        });

        return to_route('projects.show', $project);
    }

    /**
     * Display the project with its tasks, time, materials and money.
     */
    public function show(Project $project): Response
    {
        $project->load(['company:id,name,payment_term_id,sales_tax_registered', 'contact', 'deal:id,title', 'quotation:id,number', 'responsibleUser:id,name', 'members:id,name']);

        return Inertia::render('projects/Show', [
            'project' => ProjectResource::make($project),
            'metrics' => $project->metrics(),
            'milestones' => $project->milestones()->get(['id', 'name', 'due_date', 'sort'])
                ->map(fn (Milestone $milestone) => [
                    'id' => $milestone->id,
                    'name' => $milestone->name,
                    'due_date' => $milestone->due_date?->toDateString(),
                ])->values(),
            'tasks' => TaskResource::collection(
                $project->tasks()->with('assignee:id,name')->withSum('timeEntries', 'duration_minutes')->orderBy('sort')->orderBy('id')->get(),
            ),
            'timeEntries' => TimeEntryResource::collection(
                $project->timeEntries()->with(['user:id,name', 'task:id,title', 'workType:id,name', 'invoice:id,number'])->latest('date')->latest('id')->limit(100)->get(),
            ),
            'materials' => ProjectMaterialResource::collection(
                $project->materials()->with('invoice:id,number')->latest('date')->latest('id')->get(),
            ),
            'invoices' => $project->invoices()->latest('date')->latest('id')->get()->map(fn (Invoice $invoice) => $invoice->toSummary())->values(),
            'expenses' => $project->expenses()->latest('date')->latest('id')->get()->map(fn (Expense $expense) => $expense->toSummary())->values(),
            'timeline' => Timeline::for($project),
            'attachments' => AttachmentResource::collection($project->attachments()->with('user:id,name')->get()),
            'users' => TeamOptions::users(),
            'workTypes' => TeamOptions::workTypes(),
            'taxRates' => TeamOptions::taxRates(),
            'statuses' => ProjectStatus::options(),
            'taskStatuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
        ]);
    }

    /**
     * Show the form for editing the project.
     */
    public function edit(Project $project): Response
    {
        $project->load(['company:id,name', 'contact', 'deal:id,title', 'quotation:id,number', 'members:id,name']);

        return Inertia::render('projects/Edit', [
            'project' => ProjectResource::make($project),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the project.
     */
    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            $project->fill($request->project());
            $this->trackCompletion($project);
            $project->save();

            $project->members()->sync($this->memberIds($request));
        });

        return to_route('projects.show', $project);
    }

    /**
     * Change only the status (e.g. mark the project as done).
     */
    public function status(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(ProjectStatus::class)]]);

        $project->status = ProjectStatus::from($validated['status']);
        $this->trackCompletion($project);
        $project->save();

        return back();
    }

    /**
     * Delete the project (its invoices stay).
     */
    public function destroy(Project $project): RedirectResponse
    {
        DB::transaction(function () use ($project) {
            $project->tasks()->delete();
            $project->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }

    /**
     * Create a draft invoice for the project's unbilled work or for part of its fixed price.
     */
    public function invoice(Request $request, Project $project, InvoiceProject $invoicer): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['time_materials', 'fixed'])],
            'until' => ['nullable', 'date'],
            'amount' => ['required_if:mode,fixed', 'nullable', 'numeric', 'gt:0', 'max:99999999999'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = $validated['mode'] === 'fixed'
            ? $invoicer->fixedPrice($project, $request->user(), number_format((float) $validated['amount'], 2, '.', ''), $validated['description'] ?? null)
            : $invoicer->timeAndMaterials($project, $request->user(), isset($validated['until']) ? CarbonImmutable::parse($validated['until']) : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft invoice created. Check it and issue it when ready.')]);

        return to_route('invoices.edit', $invoice);
    }

    /**
     * Set or clear the completion time when the status changes.
     */
    protected function trackCompletion(Project $project): void
    {
        if ($project->isDirty('status')) {
            $project->completed_at = $project->status === ProjectStatus::Done ? now() : null;
        }
    }

    /**
     * The members to keep on the project (the responsible user always takes part).
     *
     * @return array<int, int>
     */
    protected function memberIds(ProjectRequest $request): array
    {
        $ids = array_map('intval', (array) $request->validated('member_ids', []));

        if ($request->validated('responsible_user_id')) {
            $ids[] = (int) $request->validated('responsible_user_id');
        }

        return array_values(array_unique($ids));
    }

    /**
     * The value of all billable time and materials that is not invoiced yet.
     */
    protected function unbilledValue(): string
    {
        $time = (float) TimeEntry::query()->unbilled()->whereNotNull('project_id')->selectRaw('SUM(duration_minutes * hourly_rate / 60) as value')->value('value');
        $materials = (float) ProjectMaterial::query()->where('is_billable', true)->whereNull('invoice_id')->selectRaw('SUM(quantity * unit_price) as value')->value('value');

        return number_format($time + $materials, 2, '.', '');
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'users' => TeamOptions::users(),
            'statuses' => ProjectStatus::options(),
            'billingMethods' => BillingMethod::options(),
            'currencies' => TeamOptions::currencies(),
        ];
    }
}
