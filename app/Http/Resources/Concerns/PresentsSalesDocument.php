<?php

namespace App\Http\Resources\Concerns;

use App\Models\DocumentLine;
use App\Models\SalesDocument;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The fields every sales document resource (invoice, credit note) shares.
 *
 * @mixin JsonResource
 */
trait PresentsSalesDocument
{
    /**
     * Get the shared fields.
     *
     * @return array<string, mixed>
     */
    protected function documentFields(SalesDocument $document): array
    {
        $status = $document->displayStatus();

        return [
            'id' => $document->id,
            'number' => $document->number,
            'title' => $document->getAttribute('title'),
            'date' => $document->date->toDateString(),
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'currency' => $document->currency,
            'exchange_rate' => $document->exchange_rate,
            'discount_percent' => $document->discount_percent,
            'subtotal' => $document->subtotal,
            'discount_amount' => $document->discount_amount,
            'tax_total' => $document->tax_total,
            'further_tax_rate' => $document->further_tax_rate,
            'further_tax_amount' => $document->further_tax_amount,
            'total' => $document->total,
            'customer' => [
                'name' => $document->customer_name,
                'address' => $document->customer_address,
                'email' => $document->customer_email,
                'ntn' => $document->customer_ntn,
                'strn' => $document->customer_strn,
                'cnic' => $document->customer_cnic,
                'registered' => $document->customer_registered,
            ],
            'company_id' => $document->company_id,
            'contact_id' => $document->contact_id,
            'department_id' => $document->department_id,
            'company' => $this->whenLoaded('company', fn () => $document->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $document->contact ? ['id' => $document->contact->id, 'name' => $document->contact->full_name] : null),
            'department' => $this->whenLoaded('department', fn () => $document->department?->only(['id', 'name'])),
            'introduction' => $document->getAttribute('introduction'),
            'terms' => $document->getAttribute('terms'),
            'notes' => $document->getAttribute('notes'),
            'public_url' => $document->publicUrl(),
            'lines' => $this->whenLoaded('lines', fn () => $document->lines->map(fn (DocumentLine $line) => $line->toPresentation())->values()),
            'taxes' => $this->whenLoaded('lines', fn () => $document->taxBreakdown()),
            'created_at' => $document->created_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => $document->creator?->only(['id', 'name'])),
        ];
    }
}
