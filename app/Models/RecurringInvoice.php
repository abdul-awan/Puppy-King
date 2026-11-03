<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasDocumentLines;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Enums\RecurringFrequency;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A subscription: a template that creates an invoice every period (e.g. monthly retainers, AMC contracts).
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $department_id
 * @property string $title
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property int|null $payment_term_id
 * @property RecurringFrequency $frequency
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property CarbonImmutable|null $next_date
 * @property int $generated_count
 * @property bool $auto_issue
 * @property bool $auto_send
 * @property bool $is_active
 * @property string $currency
 * @property string $exchange_rate
 * @property string $discount_percent
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $tax_total
 * @property string|null $further_tax_rate
 * @property string $further_tax_amount
 * @property string $total
 * @property string|null $introduction
 * @property string|null $terms
 * @property string|null $notes
 * @property CarbonImmutable|null $last_generated_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read Department|null $department
 * @property-read PaymentTerm|null $paymentTerm
 * @property-read User|null $creator
 * @property-read Collection<int, DocumentLine> $lines
 * @property-read Collection<int, Invoice> $invoices
 */
#[Fillable([
    'department_id', 'title', 'company_id', 'contact_id', 'payment_term_id', 'frequency', 'start_date', 'end_date',
    'auto_issue', 'auto_send', 'is_active', 'currency', 'exchange_rate', 'discount_percent', 'introduction', 'terms',
    'notes', 'created_by',
])]
class RecurringInvoice extends Model
{
    /** @use HasFactory<RecurringInvoiceFactory> */
    use BelongsToTeam, HasDocumentLines, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * The maximum number of invoices created for one profile in a single run (catching up missed periods).
     */
    public const MAX_CATCH_UP = 12;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'generated_count' => 0,
        'auto_issue' => false,
        'auto_send' => false,
        'is_active' => true,
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
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<PaymentTerm, $this>
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the customer's name.
     */
    public function customerName(): ?string
    {
        return $this->company->name ?? $this->contact?->full_name;
    }

    /**
     * Work out the date of the next invoice from the start date and the number generated so far.
     */
    public function scheduleNext(): void
    {
        $next = $this->frequency->occurrence($this->start_date, $this->generated_count);

        $this->next_date = $this->end_date !== null && $next->greaterThan($this->end_date) ? null : $next;
    }

    /**
     * Determine if an invoice should be created on (or before) the given day.
     */
    public function isDue(CarbonImmutable $today): bool
    {
        return $this->is_active && $this->next_date !== null && $this->next_date->lessThanOrEqualTo($today);
    }

    /**
     * Scope a query to profiles matching a search term.
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
            ->where('title', 'like', $like)
            ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', $like)));
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [
            'created_at', 'updated_at', 'deleted_at', 'subtotal', 'discount_amount', 'tax_total', 'further_tax_amount',
            'total', 'next_date', 'generated_count', 'last_generated_at',
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
            ...$this->amountCasts(),
            'frequency' => RecurringFrequency::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_date' => 'date',
            'generated_count' => 'integer',
            'auto_issue' => 'boolean',
            'auto_send' => 'boolean',
            'is_active' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }
}
