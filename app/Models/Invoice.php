<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An invoice. Drafts have no number; the number is assigned when the invoice is issued.
 *
 * @property string|null $title
 * @property int|null $deal_id
 * @property int|null $project_id
 * @property int|null $quotation_id
 * @property int|null $recurring_invoice_id
 * @property int|null $payment_term_id
 * @property CarbonImmutable|null $due_date
 * @property InvoiceStatus $status
 * @property string $amount_paid
 * @property string $amount_credited
 * @property string|null $fbr_invoice_number
 * @property string|null $introduction
 * @property string|null $terms
 * @property string|null $notes
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $paid_at
 * @property int $reminder_count
 * @property CarbonImmutable|null $last_reminder_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Deal|null $deal
 * @property-read Quotation|null $quotation
 * @property-read PaymentTerm|null $paymentTerm
 * @property-read RecurringInvoice|null $recurringInvoice
 * @property-read Collection<int, InvoicePayment> $payments
 * @property-read Collection<int, CreditNote> $creditNotes
 * @property-read Collection<int, WorkOrder> $workOrders
 */
#[Fillable([
    'department_id', 'title', 'company_id', 'contact_id', 'deal_id', 'project_id', 'quotation_id', 'recurring_invoice_id',
    'payment_term_id', 'date', 'due_date', 'currency', 'exchange_rate', 'discount_percent', 'fbr_invoice_number',
    'introduction', 'terms', 'notes', 'created_by',
])]
class Invoice extends SalesDocument
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'amount_paid' => '0.00',
        'amount_credited' => '0.00',
        'reminder_count' => 0,
    ];

    /**
     * Boot the model: a deleted (draft) invoice releases the time, materials and work orders billed on it.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::deleted(function (Invoice $invoice) {
            $invoice->timeEntries()->update(['invoice_id' => null]);
            $invoice->projectMaterials()->update(['invoice_id' => null]);
            $invoice->workOrders()->update(['invoice_id' => null, 'invoiced_at' => null]);
        });
    }

    /**
     * Get the numbering type of the document.
     */
    public function documentType(): DocumentType
    {
        return DocumentType::Invoice;
    }

    /**
     * Get the title printed on the document: a sales tax invoice when the seller is registered.
     */
    public function documentTitle(): string
    {
        $department = $this->department ?? $this->team()->first()?->defaultDepartment();

        return filled($department?->strn) ? 'Sales Tax Invoice' : 'Invoice';
    }

    /**
     * Get the link the customer can open without logging in.
     */
    public function publicUrl(): string
    {
        return route('public.invoices.show', ['token' => $this->public_token]);
    }

    /**
     * Determine if the customer can open the invoice through its link (drafts cannot).
     */
    public function hasPublicPage(): bool
    {
        return $this->exists && $this->status !== InvoiceStatus::Draft;
    }

    /**
     * Get the link to the invoice inside the application.
     */
    public function internalUrl(): string
    {
        return route('invoices.show', ['current_team' => $this->teamSlug(), 'invoice' => $this->id]);
    }

    /**
     * Get the label/value rows shown next to the customer on the document.
     *
     * @return array<string, string|null>
     */
    public function documentMeta(string $dateFormat = 'd M Y'): array
    {
        return array_filter([
            'Invoice no.' => $this->number ?? 'DRAFT',
            'Date' => $this->date->format($dateFormat),
            'Due date' => $this->due_date?->format($dateFormat),
            'FBR invoice no.' => $this->fbr_invoice_number,
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Get the label and badge colour describing the invoice's current state.
     *
     * @return array{label: string, color: string}
     */
    public function displayStatus(): array
    {
        if ($this->status === InvoiceStatus::Open) {
            if ($this->isOverdue()) {
                return ['label' => 'Overdue', 'color' => 'red'];
            }

            if (BigDecimal::of($this->amount_paid)->isPositive()) {
                return ['label' => 'Partially paid', 'color' => 'amber'];
            }
        }

        return ['label' => $this->status->label(), 'color' => $this->status->color()];
    }

    /**
     * Extra blocks for the PDF: a status stamp, payment details and the balance.
     *
     * @return array<string, mixed>
     */
    public function pdfExtra(): array
    {
        $extra = [];

        $extra['stamp'] = match ($this->status) {
            InvoiceStatus::Draft => ['text' => 'DRAFT', 'color' => '#6b7280'],
            InvoiceStatus::Paid => ['text' => 'PAID', 'color' => '#15803d'],
            InvoiceStatus::Credited => ['text' => 'CREDITED', 'color' => '#7e22ce'],
            InvoiceStatus::Open => null,
        };

        $department = $this->department ?? $this->team()->first()?->defaultDepartment();
        $bank = array_values(array_filter([
            $department?->bank_name,
            $department?->bank_account_title ? 'Account title: '.$department->bank_account_title : null,
            $department?->bank_account_number ? 'Account no.: '.$department->bank_account_number : null,
            $department?->iban ? 'IBAN: '.$department->iban : null,
        ]));

        if ($bank !== [] && $this->status !== InvoiceStatus::Credited) {
            $bank[] = 'Please quote '.($this->number ?? 'the invoice number').' as payment reference.';
            $extra['payment_box'] = ['label' => 'Payment details', 'lines' => $bank];
        }

        if (BigDecimal::of($this->amount_paid)->isPositive() || BigDecimal::of($this->amount_credited)->isPositive()) {
            $extra['total_rows'] = array_values(array_filter([
                BigDecimal::of($this->amount_paid)->isPositive() ? ['label' => 'Paid', 'value' => $this->amount_paid] : null,
                BigDecimal::of($this->amount_credited)->isPositive() ? ['label' => 'Credited', 'value' => $this->amount_credited] : null,
                ['label' => 'Balance due', 'value' => $this->balanceDue(), 'bold' => true],
            ]));
        }

        return array_filter($extra);
    }

    /**
     * The document specific placeholders for e-mail templates.
     *
     * @return array<string, string|int|float|null>
     */
    public function emailPlaceholders(): array
    {
        return [
            '{invoice_number}' => $this->number,
            '{invoice_total}' => $this->formatMoney($this->total),
            '{amount_due}' => $this->formatMoney($this->balanceDue()),
            '{due_date}' => $this->due_date?->format('d M Y') ?? '—',
            '{days_overdue}' => $this->daysOverdue(),
        ];
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * The time billed on this invoice.
     *
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The project materials billed on this invoice.
     *
     * @return HasMany<ProjectMaterial, $this>
     */
    public function projectMaterials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    /**
     * The work orders billed on this invoice.
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PaymentTerm, $this>
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * @return BelongsTo<RecurringInvoice, $this>
     */
    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class)->withTrashed();
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('date')->orderBy('id');
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * Determine if the invoice can still be changed (only drafts can).
     */
    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * Determine if the invoice is open and past its due date.
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Open
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast();
    }

    /**
     * Get the number of days the invoice is overdue (0 when it is not).
     */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date?->startOfDay()->diffInDays(today()) : 0;
    }

    /**
     * Get the amount still to be paid (negative when more was paid or credited than invoiced).
     */
    public function balanceDue(): string
    {
        return (string) BigDecimal::of($this->total)
            ->minus(BigDecimal::of($this->amount_paid))
            ->minus(BigDecimal::of($this->amount_credited))
            ->toScale(2);
    }

    /**
     * Get the amount that can still be credited.
     */
    public function creditableAmount(): string
    {
        return (string) BigDecimal::of($this->total)->minus(BigDecimal::of($this->amount_credited))->toScale(2);
    }

    /**
     * Recalculate the paid and credited amounts and update the status accordingly.
     */
    public function refreshBalance(): void
    {
        $paid = $this->payments()->get()->reduce(
            fn (BigDecimal $sum, InvoicePayment $payment) => $sum->plus($payment->settledAmount()),
            BigDecimal::zero(),
        );

        $credited = $this->creditNotes()->where('status', CreditNoteStatus::Issued)->get(['total'])->reduce(
            fn (BigDecimal $sum, CreditNote $note) => $sum->plus(BigDecimal::of($note->total)),
            BigDecimal::zero(),
        );

        $this->amount_paid = (string) $paid->toScale(2);
        $this->amount_credited = (string) $credited->toScale(2);

        if ($this->status !== InvoiceStatus::Draft) {
            $balance = BigDecimal::of($this->total)->minus($paid)->minus($credited);

            if ($balance->isPositive()) {
                $this->status = InvoiceStatus::Open;
                $this->paid_at = null;
            } elseif ($credited->isPositive() && $credited->isGreaterThanOrEqualTo(BigDecimal::of($this->total)) && $paid->isZero()) {
                $this->status = InvoiceStatus::Credited;
                $this->paid_at = null;
            } else {
                $this->status = InvoiceStatus::Paid;
                $this->paid_at ??= now();
            }
        }

        $this->save();
    }

    /**
     * Scope a query to invoices matching a search term.
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
            ->orWhere('customer_name', 'like', $like)
            ->orWhere('fbr_invoice_number', 'like', $like));
    }

    /**
     * Scope a query to open invoices past their due date.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Open)->whereDate('due_date', '<', today());
    }

    /**
     * Scope a query to issued invoices (everything but drafts), i.e. the ones that count as turnover.
     *
     * @param  Builder<self>  $query
     */
    public function scopeIssued(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '!=', InvoiceStatus::Draft);
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [
            ...parent::activityIgnoredAttributes(),
            'amount_paid', 'amount_credited', 'status', 'issued_at', 'sent_at', 'paid_at', 'reminder_count',
            'last_reminder_at', 'number',
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
            ...$this->documentCasts(),
            'date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'amount_paid' => 'decimal:2',
            'amount_credited' => 'decimal:2',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }
}
