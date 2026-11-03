<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsSalesDocument;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    use PresentsSalesDocument;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;

        return [
            ...$this->documentFields($invoice),
            'status' => $invoice->status->value,
            'due_date' => $invoice->due_date?->toDateString(),
            'is_overdue' => $invoice->isOverdue(),
            'days_overdue' => $invoice->daysOverdue(),
            'amount_paid' => $invoice->amount_paid,
            'amount_credited' => $invoice->amount_credited,
            'balance_due' => $invoice->balanceDue(),
            'creditable_amount' => $invoice->creditableAmount(),
            'fbr_invoice_number' => $invoice->fbr_invoice_number,
            'deal_id' => $invoice->deal_id,
            'quotation_id' => $invoice->quotation_id,
            'payment_term_id' => $invoice->payment_term_id,
            'recurring_invoice_id' => $invoice->recurring_invoice_id,
            'deal' => $this->whenLoaded('deal', fn () => $invoice->deal?->only(['id', 'title'])),
            'quotation' => $this->whenLoaded('quotation', fn () => $invoice->quotation?->only(['id', 'number'])),
            'payment_term' => $this->whenLoaded('paymentTerm', fn () => $invoice->paymentTerm?->only(['id', 'name'])),
            'recurring_invoice' => $this->whenLoaded('recurringInvoice', fn () => $invoice->recurringInvoice?->only(['id', 'title'])),
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'credit_notes' => $this->whenLoaded('creditNotes', fn () => $invoice->creditNotes->map->toSummary()->values()),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'sent_at' => $invoice->sent_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'reminder_count' => $invoice->reminder_count,
            'last_reminder_at' => $invoice->last_reminder_at?->toIso8601String(),
        ];
    }
}
