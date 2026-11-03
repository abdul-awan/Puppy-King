<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time worked by a user, logged by hand or with the timer (a running timer has no end yet).
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property int|null $ticket_id
 * @property int|null $work_order_id
 * @property int|null $company_id
 * @property int|null $work_type_id
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property int $duration_minutes
 * @property string|null $description
 * @property bool $is_billable
 * @property string $hourly_rate
 * @property int|null $invoice_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Project|null $project
 * @property-read Task|null $task
 * @property-read WorkOrder|null $workOrder
 * @property-read Company|null $company
 * @property-read WorkType|null $workType
 * @property-read Invoice|null $invoice
 */
#[Fillable([
    'project_id', 'task_id', 'ticket_id', 'work_order_id', 'company_id', 'work_type_id', 'date', 'started_at', 'ended_at',
    'duration_minutes', 'description', 'is_billable', 'hourly_rate',
])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'duration_minutes' => 0,
        'is_billable' => true,
        'hourly_rate' => '0',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WorkType, $this>
     */
    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * Determine if the timer of this entry is still running.
     */
    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    /**
     * Stop the timer and record how long it ran (at least a minute).
     */
    public function stop(?CarbonImmutable $at = null): void
    {
        $end = $at ?? CarbonImmutable::now();
        $minutes = $this->started_at !== null ? (int) floor($this->started_at->diffInSeconds($end) / 60) : 0;

        $this->ended_at = $end;
        $this->duration_minutes = $this->duration_minutes + max(1, $minutes);
        $this->save();
    }

    /**
     * Get the value of the time (billable or not): hours × rate.
     */
    public function value(): string
    {
        return (string) BigDecimal::of($this->duration_minutes)
            ->multipliedBy(BigDecimal::of($this->hourly_rate))
            ->dividedBy(60, 2, RoundingMode::HalfUp);
    }

    /**
     * Scope a query to billable time that was not invoiced yet (running timers excluded).
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnbilled(Builder $query): void
    {
        $query->where('is_billable', true)
            ->whereNull('invoice_id')
            ->where('duration_minutes', '>', 0)
            ->where(fn (Builder $query) => $query->whereNull('started_at')->orWhereNotNull('ended_at'));
    }

    /**
     * Scope a query to running timers.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->whereNotNull('started_at')->whereNull('ended_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'is_billable' => 'boolean',
            'hourly_rate' => 'decimal:2',
        ];
    }
}
