<?php

namespace App\Http\Resources;

use App\Models\DocumentLine;
use App\Models\RecurringInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurringInvoice
 */
class RecurringInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ended = $this->next_date === null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_date' => $this->next_date?->toDateString(),
            'status_label' => $ended ? 'Ended' : ($this->is_active ? 'Active' : 'Paused'),
            'status_color' => $ended ? 'gray' : ($this->is_active ? 'green' : 'amber'),
            'generated_count' => $this->generated_count,
            'auto_issue' => $this->auto_issue,
            'auto_send' => $this->auto_send,
            'is_active' => $this->is_active,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'discount_percent' => $this->discount_percent,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_total' => $this->tax_total,
            'further_tax_rate' => $this->further_tax_rate,
            'further_tax_amount' => $this->further_tax_amount,
            'total' => $this->total,
            'customer_name' => $this->customerName(),
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'department_id' => $this->department_id,
            'payment_term_id' => $this->payment_term_id,
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'payment_term' => $this->whenLoaded('paymentTerm', fn () => $this->paymentTerm?->only(['id', 'name'])),
            'introduction' => $this->introduction,
            'terms' => $this->terms,
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (DocumentLine $line) => $line->toPresentation())->values()),
            'taxes' => $this->whenLoaded('lines', fn () => $this->taxBreakdown()),
            'last_generated_at' => $this->last_generated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
