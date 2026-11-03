<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offer to a customer, which they can accept online.
 *
 * @property string|null $title
 * @property int|null $deal_id
 * @property CarbonImmutable|null $valid_until
 * @property QuotationStatus $status
 * @property string|null $introduction
 * @property string|null $terms
 * @property string|null $notes
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $declined_at
 * @property string|null $accepted_by_name
 * @property string|null $accepted_signature
 * @property string|null $accepted_ip
 * @property string|null $decline_reason
 * @property CarbonImmutable|null $invoiced_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Deal|null $deal
 */
#[Fillable([
    'department_id', 'number', 'title', 'deal_id', 'company_id', 'contact_id', 'date', 'valid_until', 'status',
    'currency', 'exchange_rate', 'discount_percent', 'further_tax_rate', 'introduction', 'terms', 'notes', 'created_by',
])]
class Quotation extends SalesDocument
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    /**
     * Get the numbering type of the document.
     */
    public function documentType(): DocumentType
    {
        return DocumentType::Quotation;
    }

    /**
     * Get the title printed on the document.
     */
    public function documentTitle(): string
    {
        return 'Quotation';
    }

    /**
     * Get the link the customer can open without logging in.
     */
    public function publicUrl(): string
    {
        return route('public.quotations.show', ['token' => $this->public_token]);
    }

    /**
     * Determine if the customer can open the quotation through its link (drafts cannot).
     */
    public function hasPublicPage(): bool
    {
        return $this->exists && $this->status !== QuotationStatus::Draft;
    }

    /**
     * Get the link to the quotation inside the application.
     */
    public function internalUrl(): string
    {
        return route('quotations.show', ['current_team' => $this->teamSlug(), 'quotation' => $this->id]);
    }

    /**
     * Get the label and badge colour describing the quotation's current state.
     *
     * @return array{label: string, color: string}
     */
    public function displayStatus(): array
    {
        if ($this->isExpired()) {
            return ['label' => 'Expired', 'color' => 'orange'];
        }

        return ['label' => $this->status->label(), 'color' => $this->status->color()];
    }

    /**
     * Get the label/value rows shown next to the customer on the document.
     *
     * @return array<string, string|null>
     */
    public function documentMeta(string $dateFormat = 'd M Y'): array
    {
        return [
            'Quotation no.' => $this->number,
            'Date' => $this->date->format($dateFormat),
            'Valid until' => $this->valid_until?->format($dateFormat),
        ];
    }

    /**
     * Extra blocks for the PDF: the customer's signature once accepted.
     *
     * @return array<string, mixed>
     */
    public function pdfExtra(): array
    {
        $extra = [];

        if ($this->status === QuotationStatus::Accepted) {
            $extra['stamp'] = ['text' => 'ACCEPTED', 'color' => '#15803d'];

            if ($this->accepted_signature) {
                $extra['signature'] = [
                    'image' => $this->accepted_signature,
                    'name' => (string) $this->accepted_by_name,
                    'date' => $this->accepted_at?->format('d M Y H:i') ?? '',
                ];
            }
        } elseif ($this->status === QuotationStatus::Declined) {
            $extra['stamp'] = ['text' => 'DECLINED', 'color' => '#b91c1c'];
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
            '{quotation_number}' => $this->number,
            '{quotation_total}' => $this->formatMoney($this->total),
            '{valid_until}' => $this->valid_until?->format('d M Y') ?? '—',
        ];
    }

    /**
     * Get the deal the quotation belongs to.
     *
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withTrashed();
    }

    /**
     * Determine if the quotation was sent but has passed its validity date.
     */
    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Sent
            && $this->valid_until !== null
            && $this->valid_until->endOfDay()->isPast();
    }

    /**
     * Determine if the customer can still accept or decline the quotation online.
     */
    public function canBeAnswered(): bool
    {
        return $this->status === QuotationStatus::Sent && ! $this->isExpired();
    }

    /**
     * Scope a query to quotations matching a search term.
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
            ->orWhere('customer_name', 'like', $like));
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
            'valid_until' => 'date',
            'status' => QuotationStatus::class,
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'invoiced_at' => 'datetime',
        ];
    }
}
