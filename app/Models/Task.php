<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something to do: part of a project, or on its own (e.g. for a customer or deal).
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property int|null $milestone_id
 * @property int|null $company_id
 * @property int|null $deal_id
 * @property string $title
 * @property string|null $description
 * @property int|null $assignee_id
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $planned_date
 * @property int|null $planned_minutes
 * @property int|null $estimated_minutes
 * @property int|null $work_type_id
 * @property bool $is_billable
 * @property int $sort
 * @property CarbonImmutable|null $completed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Project|null $project
 * @property-read Milestone|null $milestone
 * @property-read Company|null $company
 * @property-read Deal|null $deal
 * @property-read User|null $assignee
 * @property-read WorkType|null $workType
 */
#[Fillable([
    'project_id', 'milestone_id', 'company_id', 'deal_id', 'title', 'description', 'assignee_id', 'status', 'priority',
    'due_date', 'planned_date', 'planned_minutes', 'estimated_minutes', 'work_type_id', 'is_billable', 'sort', 'created_by',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToTeam, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'normal',
        'is_billable' => true,
        'sort' => 0,
    ];

    /**
     * Boot the model: keep the completion time in step with the status.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
            }
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<WorkType, $this>
     */
    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The time the task takes in the planning: planned, else estimated, else one hour.
     */
    public function planningMinutes(): int
    {
        return $this->planned_minutes ?? $this->estimated_minutes ?? 60;
    }

    /**
     * Determine if the open task has passed its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->due_date !== null && $this->due_date->endOfDay()->isPast();
    }

    /**
     * Scope a query to tasks that are not done.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Done);
    }

    /**
     * Scope a query to tasks matching a search term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where('title', 'like', '%'.addcslashes(trim($term), '%_\\').'%');
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'completed_at', 'sort'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'planned_date' => 'date',
            'planned_minutes' => 'integer',
            'estimated_minutes' => 'integer',
            'is_billable' => 'boolean',
            'sort' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
