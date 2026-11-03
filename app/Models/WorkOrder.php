<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Contracts\Summarizable;
use App\Enums\WorkOrderStatus;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A report of work done for a customer, usually on site: what was done, the hours and
 * materials used and the customer's signature for approval.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $department_id
 * @property string|null $number
 * @property string $title
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property int|null $project_id
 * @property int|null $ticket_id
 * @property int|null $assignee_id
 * @property CarbonImmutable $date
 * @property string|null $location
 * @property string|null $description
 * @property string|null $internal_notes
 * @property WorkOrderStatus $status
 * @property bool $show_prices
 * @property CarbonImmutable|null $completed_at
 * @property string|null $signed_by_name
 * @property string|null $signature
 * @property CarbonImmutable|null $signed_at
 * @property string|null $signed_ip
 * @property string|null $customer_remarks
 * @property int|null $invoice_id
 * @property CarbonImmutable|null $invoiced_at
 * @property string $public_token
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Department|null $department
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read Project|null $project
 * @property-read Ticket|null $ticket
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, TimeEntry> $timeEntries
 * @property-read Collection<int, ProjectMaterial> $materials
 */
#[Fillable([
    'title', 'department_id', 'company_id', 'contact_id', 'project_id', 'ticket_id', 'assignee_id', 'date',
    'location', 'description', 'internal_notes', 'show_prices', 'created_by',
])]
class WorkOrder extends Model implements Attachable, Summarizable
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToTeam, HasAttachments, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'show_prices' => false,
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (WorkOrder $workOrder) {
            if (empty($workOrder->public_token)) {
                $workOrder->public_token = Str::random(48);
            }
        });
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    /**
     * The person in charge of the work (e.g. the technician).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * The hours worked for this work order.
     *
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The materials used for this work order.
     *
     * @return HasMany<ProjectMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    /**
     * Determine if the work order can still be changed.
     */
    public function isOpen(): bool
    {
        return $this->status === WorkOrderStatus::Open;
    }

    /**
     * Determine if the customer signed the work order.
     */
    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * Determine if the work order was put on an invoice.
     */
    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * Determine if the customer can still sign (not signed or invoiced yet).
     */
    public function canBeSigned(): bool
    {
        return ! $this->isSigned() && ! $this->isInvoiced();
    }

    /**
     * Get the status shown in lists: open, completed, signed or invoiced.
     *
     * @return array{key: string, label: string, color: string}
     */
    public function displayStatus(): array
    {
        return match (true) {
            $this->isInvoiced() => ['key' => 'invoiced', 'label' => 'Invoiced', 'color' => 'purple'],
            $this->isOpen() => ['key' => 'open', 'label' => 'Open', 'color' => 'blue'],
            $this->isSigned() => ['key' => 'signed', 'label' => 'Signed', 'color' => 'green'],
            default => ['key' => 'completed', 'label' => 'Completed', 'color' => 'teal'],
        };
    }

    /**
     * Get the link the customer uses to see, download and sign the work order.
     */
    public function publicUrl(): string
    {
        return route('public.work-orders.show', ['token' => $this->public_token]);
    }

    /**
     * Get the customer's name as shown on the work order.
     */
    public function customerLabel(): string
    {
        return $this->company->name ?? $this->contact->full_name ?? '—';
    }

    /**
     * Get the address the work order is e-mailed to.
     */
    public function customerEmail(): ?string
    {
        return $this->contact->email ?? $this->company?->email;
    }

    /**
     * Get the hours and materials on the work order and their value (billable only).
     *
     * @return array{minutes: int, billable_minutes: int, time_value: string, materials_value: string, total_value: string}
     */
    public function totals(): array
    {
        $billableTime = $this->timeEntries->where('is_billable', true);

        $timeValue = $billableTime->reduce(
            fn (BigDecimal $sum, TimeEntry $entry) => $sum->plus($entry->value()),
            BigDecimal::zero(),
        );

        $materialsValue = $this->materials->where('is_billable', true)->reduce(
            fn (BigDecimal $sum, ProjectMaterial $material) => $sum->plus($material->value()),
            BigDecimal::zero(),
        );

        return [
            'minutes' => (int) $this->timeEntries->sum('duration_minutes'),
            'billable_minutes' => (int) $billableTime->sum('duration_minutes'),
            'time_value' => (string) $timeValue->toScale(2),
            'materials_value' => (string) $materialsValue->toScale(2),
            'total_value' => (string) $timeValue->plus($materialsValue)->toScale(2),
        ];
    }

    /**
     * Get the compact representation used in lists of related records.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'type' => 'work_order',
            'number' => $this->number,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'total' => '0',
            'currency' => '',
            'customer' => $this->customerLabel(),
            'url' => route('work-orders.show', ['current_team' => $this->teamSlug(), 'work_order' => $this->id]),
        ];
    }

    /**
     * Scope a query to work orders matching a search term.
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
            ->orWhere('location', 'like', $like)
            ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', $like))
            ->orWhereHas('contact', fn (Builder $query) => $query
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)));
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [
            'created_at', 'updated_at', 'deleted_at', 'public_token', 'number', 'signature', 'signed_ip',
            'signed_at', 'signed_by_name', 'customer_remarks', 'completed_at', 'invoice_id', 'invoiced_at',
        ];
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
            'status' => WorkOrderStatus::class,
            'show_prices' => 'boolean',
            'completed_at' => 'datetime',
            'signed_at' => 'datetime',
            'invoiced_at' => 'datetime',
        ];
    }
}
