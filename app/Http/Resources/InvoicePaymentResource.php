<?php

namespace App\Http\Resources;

use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoicePayment
 */
class InvoicePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'date' => $this->date->toDateString(),
            'amount' => $this->amount,
            'income_tax_withheld' => $this->income_tax_withheld,
            'sales_tax_withheld' => $this->sales_tax_withheld,
            'settled_amount' => (string) $this->settledAmount()->toScale(2),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name'])),
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'number' => $this->invoice->number,
                'customer' => $this->invoice->customer_name,
                'currency' => $this->invoice->currency,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
