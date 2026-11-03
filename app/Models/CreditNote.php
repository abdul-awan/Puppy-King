<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentType;
use Carbon\CarbonImmutable;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credit note reducing (part of) an issued invoice, e.g. for returned goods or a price correction.
 *
 * @property int $invoice_id
 * @property string|null $title
 * @property CreditNoteStatus $status
 * @property string|null $reason
 * @property bool $restock
 * @property string|null $introduction
 * @property string|null $terms
 * @property string|null $notes
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Invoice $invoice
 */
#[Fillable([
    'invoice_id', 'department_id', 'title', 'company_id', 'contact_id', 'date', 'reason', 'restock', 'currency',
    'exchange_rate', 'discount_percent', 'introduction', 'terms', 'notes', 'created_by',
])]
class CreditNote extends SalesDocument
{
    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'restock' => false,
    ];

    /**
     * Get the numbering type of the document.
     */
    public function documentType(): DocumentType
    {
        return DocumentType::CreditNote;
    }

    /**
     * Get the title printed on the document.
     */
    public function documentTitle(): string
    {
        return 'Credit Note';
    }

    /**
     * Get the link the customer can open without logging in.
     */
    public function publicUrl(): string
    {
        return route('public.credit-notes.show', ['token' => $this->public_token]);
    }

    /**
     * Determine if the customer can open the credit note through its link (only issued ones).
     */
    public function hasPublicPage(): bool
    {
        return $this->exists && $this->status === CreditNoteStatus::Issued;
    }

    /**
     * Get the link to the credit note inside the application.
     */
    public function internalUrl(): string
    {
        return route('credit-notes.show', ['current_team' => $this->teamSlug(), 'credit_note' => $this->id]);
    }

    /**
     * Get the label/value rows shown next to the customer on the document.
     *
     * @return array<string, string|null>
     */
    public function documentMeta(string $dateFormat = 'd M Y'): array
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : $this->invoice()->first();

        return array_filter([
            'Credit note no.' => $this->number ?? 'DRAFT',
            'Date' => $this->date->format($dateFormat),
            'Original invoice' => $invoice?->number,
            'Invoice date' => $invoice?->date->format($dateFormat),
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Get the label and badge colour describing the credit note's current state.
     *
     * @return array{label: string, color: string}
     */
    public function displayStatus(): array
    {
        return ['label' => $this->status->label(), 'color' => $this->status->color()];
    }

    /**
     * Extra blocks for the PDF: the reason for the credit.
     *
     * @return array<string, mixed>
     */
    public function pdfExtra(): array
    {
        $extra = [];

        if ($this->status === CreditNoteStatus::Draft) {
            $extra['stamp'] = ['text' => 'DRAFT', 'color' => '#6b7280'];
        }

        if (filled($this->reason)) {
            $extra['side_box'] = ['label' => 'Reason', 'lines' => [$this->reason]];
        }

        return $extra;
    }

    /**
     * The document specific placeholders for e-mail templates.
     *
     * @return array<string, string|int|float|null>
     */
    public function emailPlaceholders(): array
    {
        return [
            '{credit_note_number}' => $this->number,
            '{credit_note_total}' => $this->formatMoney($this->total),
            '{invoice_number}' => $this->invoice()->value('number'),
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * Determine if the credit note can still be changed (only drafts can).
     */
    public function isDraft(): bool
    {
        return $this->status === CreditNoteStatus::Draft;
    }

    /**
     * Scope a query to credit notes matching a search term.
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
            ->orWhereHas('invoice', fn (Builder $query) => $query->where('number', 'like', $like)));
    }

    /**
     * Scope a query to issued credit notes (the ones that reduce turnover).
     *
     * @param  Builder<self>  $query
     */
    public function scopeIssued(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), CreditNoteStatus::Issued);
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [...parent::activityIgnoredAttributes(), 'status', 'issued_at', 'sent_at', 'number'];
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
            'status' => CreditNoteStatus::class,
            'restock' => 'boolean',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
