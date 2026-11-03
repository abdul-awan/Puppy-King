<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasDocumentLines;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Contracts\Summarizable;
use App\Enums\AddressType;
use App\Enums\DocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Shared behaviour of quotations, invoices and credit notes: lines, totals,
 * a snapshot of the customer's details and a public link for the customer.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $department_id
 * @property string|null $number
 * @property CarbonImmutable $date
 * @property int|null $company_id
 * @property int|null $contact_id
 * @property string $currency
 * @property string $exchange_rate
 * @property string $discount_percent
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $tax_total
 * @property string|null $further_tax_rate
 * @property string $further_tax_amount
 * @property string $total
 * @property string|null $customer_name
 * @property string|null $customer_address
 * @property string|null $customer_email
 * @property string|null $customer_ntn
 * @property string|null $customer_strn
 * @property string|null $customer_cnic
 * @property bool $customer_registered
 * @property string $public_token
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Department|null $department
 * @property-read Company|null $company
 * @property-read Contact|null $contact
 * @property-read User|null $creator
 * @property-read Collection<int, DocumentLine> $lines
 */
abstract class SalesDocument extends Model implements Attachable, Summarizable
{
    use BelongsToTeam, HasAttachments, HasDocumentLines, HasNotes, LogsActivity, SoftDeletes;

    /**
     * Get the numbering type of the document.
     */
    abstract public function documentType(): DocumentType;

    /**
     * Get the title printed on the document, e.g. "Quotation".
     */
    abstract public function documentTitle(): string;

    /**
     * Get the link the customer can open without logging in.
     */
    abstract public function publicUrl(): string;

    /**
     * Determine if the customer can open the document through its link (drafts cannot).
     */
    abstract public function hasPublicPage(): bool;

    /**
     * Get the label/value rows shown next to the customer on the document.
     *
     * @return array<string, string|null>
     */
    abstract public function documentMeta(string $dateFormat = 'd M Y'): array;

    /**
     * Get the link to the document inside the application.
     */
    abstract public function internalUrl(): string;

    /**
     * Get the label and badge colour describing the document's current state.
     *
     * @return array{label: string, color: string}
     */
    abstract public function displayStatus(): array;

    /**
     * Get the compact representation used in lists of related documents.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'type' => $this->documentType()->value,
            'number' => $this->number,
            'title' => $this->getAttribute('title'),
            'date' => $this->date->toDateString(),
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'total' => $this->total,
            'currency' => $this->currency,
            'customer' => $this->customer_name,
            'url' => $this->internalUrl(),
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (SalesDocument $document) {
            if (empty($document->public_token)) {
                $document->public_token = Str::random(48);
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Copy the customer's current details onto the document.
     */
    public function refreshCustomerSnapshot(): void
    {
        $company = $this->company_id ? $this->company()->first() : null;
        $contact = $this->contact_id ? $this->contact()->first() : null;
        $customer = $company ?? $contact;
        $address = $customer?->addressFor(AddressType::Billing);

        $this->forceFill([
            'customer_name' => $company->name ?? $contact?->full_name,
            'customer_address' => $address ? implode("\n", $address->lines()) : null,
            'customer_email' => $contact->email ?? $company?->email,
            'customer_ntn' => $company?->ntn,
            'customer_strn' => $company?->strn,
            'customer_cnic' => $company->cnic ?? $contact?->cnic,
            'customer_registered' => (bool) $company?->sales_tax_registered,
        ]);
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return [
            'created_at', 'updated_at', 'deleted_at', 'public_token', 'subtotal', 'discount_amount', 'tax_total',
            'further_tax_amount', 'total', 'customer_name', 'customer_address', 'customer_email', 'customer_ntn',
            'customer_strn', 'customer_cnic', 'customer_registered', 'accepted_signature',
        ];
    }

    /**
     * Get the name of the customer as printed on the document.
     */
    public function customerLabel(): string
    {
        return $this->customer_name ?? '—';
    }

    /**
     * Extra blocks for the PDF (stamp, signature, payment details, extra total rows).
     *
     * @return array<string, mixed>
     */
    public function pdfExtra(): array
    {
        return [];
    }

    /**
     * The document specific placeholders for e-mail templates.
     *
     * @return array<string, string|int|float|null>
     */
    public function emailPlaceholders(): array
    {
        return [];
    }

    /**
     * Format an amount with the document's currency.
     */
    public function formatMoney(string|float|int $amount): string
    {
        return $this->currency.' '.number_format((float) $amount, 2);
    }

    /**
     * The casts shared by all sales documents.
     *
     * @return array<string, string>
     */
    protected function documentCasts(): array
    {
        return [
            ...$this->amountCasts(),
            'customer_registered' => 'boolean',
        ];
    }
}
