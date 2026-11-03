<?php

namespace App\Http\Resources;

use App\Models\DocumentLine;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expired = $this->isExpired();
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'is_expired' => $expired,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'discount_percent' => $this->discount_percent,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_total' => $this->tax_total,
            'further_tax_rate' => $this->further_tax_rate,
            'further_tax_amount' => $this->further_tax_amount,
            'total' => $this->total,
            'customer' => [
                'name' => $this->customer_name,
                'address' => $this->customer_address,
                'email' => $this->customer_email,
                'ntn' => $this->customer_ntn,
                'strn' => $this->customer_strn,
                'cnic' => $this->customer_cnic,
                'registered' => $this->customer_registered,
            ],
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'deal_id' => $this->deal_id,
            'department_id' => $this->department_id,
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'deal' => $this->whenLoaded('deal', fn () => $this->deal?->only(['id', 'title'])),
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'name'])),
            'introduction' => $this->introduction,
            'terms' => $this->terms,
            'notes' => $this->notes,
            'public_url' => $this->publicUrl(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'declined_at' => $this->declined_at?->toIso8601String(),
            'accepted_by_name' => $this->accepted_by_name,
            'decline_reason' => $this->decline_reason,
            'invoiced_at' => $this->invoiced_at?->toIso8601String(),
            'has_signature' => filled($this->accepted_signature),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (DocumentLine $line) => $line->toPresentation())->values()),
            'taxes' => $this->whenLoaded('lines', fn () => $this->taxBreakdown()),
            'created_at' => $this->created_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
        ];
    }
}
