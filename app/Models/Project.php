<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Contracts\Summarizable;
use App\Enums\BillingMethod;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A piece of work for a customer, with tasks, time, materials and a budget.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $number
 * @property string $title
 * @property string|null $description
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property int|null $deal_id
 * @property int|null $quotation_id
 * @property int|null $responsible_user_id
 * @property ProjectStatus $status
 * @property BillingMethod $billing_method
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $due_date
 * @property string|null $budget_amount
 * @property string|null $budget_hours
 * @property string|null $hourly_rate
 * @property string $currency
 * @property CarbonImmutable|null $completed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read Deal|null $deal
 * @property-read Quotation|null $quotation
 * @property-read User|null $responsibleUser
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Milestone> $milestones
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable([
    'title', 'description', 'company_id', 'contact_id', 'deal_id', 'quotation_id', 'responsible_user_id', 'status',
    'billing_method', 'start_date', 'due_date', 'budget_amount', 'budget_hours', 'hourly_rate', 'currency', 'created_by',
])]
class Project extends Model implements Attachable, Summarizable
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToTeam, HasAttachments, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'billing_method' => 'time_and_materials',
        'currency' => 'PKR',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * The people working on the project.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @return HasMany<ProjectMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the customer's name.
     */
    public function customerName(): ?string
    {
        return $this->company->name ?? $this->contact?->full_name;
    }

    /**
     * Determine if the open project has passed its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date !== null && $this->due_date->endOfDay()->isPast();
    }

    /**
     * The hourly rate for time without a work type rate.
     */
    public function defaultRate(): string
    {
        return $this->hourly_rate ?? '0';
    }

    /**
     * Calculate the figures shown on the project page: time, money and progress.
     *
     * @return array<string, int|float|string|null>
     */
    public function metrics(): array
    {
        $time = $this->timeEntries()
            ->selectRaw('COALESCE(SUM(duration_minutes), 0) as minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END), 0) as billable_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN duration_minutes * hourly_rate / 60 ELSE 0 END), 0) as value')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 AND invoice_id IS NULL THEN duration_minutes ELSE 0 END), 0) as open_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 AND invoice_id IS NULL THEN duration_minutes * hourly_rate / 60 ELSE 0 END), 0) as open_value')
            ->first();

        $materials = $this->materials()
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 THEN quantity * unit_price ELSE 0 END), 0) as value')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billable = 1 AND invoice_id IS NULL THEN quantity * unit_price ELSE 0 END), 0) as open_value')
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) as cost')
            ->first();

        $tasks = $this->tasks()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as done', [TaskStatus::Done->value])
            ->selectRaw('COALESCE(SUM(estimated_minutes), 0) as estimated')
            ->first();

        $invoiced = (float) $this->invoices()->where('status', '!=', InvoiceStatus::Draft)->sum('total');
        $expenses = (float) $this->expenses()->where('status', ExpenseStatus::Approved)->sum('net_amount');

        $minutes = (int) $time?->getAttribute('minutes');
        $work = round((float) $time?->getAttribute('value') + (float) $materials?->getAttribute('value'), 2);
        $budget = $this->budget_amount !== null ? (float) $this->budget_amount : null;
        $taskCount = (int) $tasks?->getAttribute('total');
        $doneCount = (int) $tasks?->getAttribute('done');

        return [
            'logged_minutes' => $minutes,
            'billable_minutes' => (int) $time?->getAttribute('billable_minutes'),
            'estimated_minutes' => (int) $tasks?->getAttribute('estimated'),
            'budget_minutes' => $this->budget_hours !== null ? (int) round((float) $this->budget_hours * 60) : null,
            'time_value' => round((float) $time?->getAttribute('value'), 2),
            'materials_value' => round((float) $materials?->getAttribute('value'), 2),
            'work_value' => $work,
            'unbilled_minutes' => (int) $time?->getAttribute('open_minutes'),
            'unbilled_value' => round((float) $time?->getAttribute('open_value') + (float) $materials?->getAttribute('open_value'), 2),
            'materials_cost' => round((float) $materials?->getAttribute('cost'), 2),
            'expenses' => round($expenses, 2),
            'invoiced' => round($invoiced, 2),
            'budget_used_percent' => $budget ? (int) round($work / $budget * 100) : null,
            'tasks_total' => $taskCount,
            'tasks_done' => $doneCount,
            'progress_percent' => $taskCount > 0 ? (int) round($doneCount / $taskCount * 100) : null,
        ];
    }

    /**
     * Get the compact representation used in lists of related records.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => 'project',
            'number' => $this->number,
            'title' => $this->title,
            'date' => ($this->start_date ?? $this->created_at ?? CarbonImmutable::now())->toDateString(),
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'total' => $this->budget_amount ?? '0',
            'currency' => $this->currency,
            'customer' => $this->customerName(),
            'url' => route('projects.show', ['current_team' => $this->teamSlug(), 'project' => $this->id]),
        ];
    }

    /**
     * Scope a query to projects matching a search term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->where('number', 'like', $like)
            ->orWhere('title', 'like', $like)
            ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', $like)));
    }

    /**
     * Scope a query to projects that are not done or cancelled.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [ProjectStatus::Planned, ProjectStatus::Active, ProjectStatus::OnHold]);
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'completed_at', 'number'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'billing_method' => BillingMethod::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'budget_amount' => 'decimal:2',
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }
}
