<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Models\ExpensePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'company_id' => $this->company_id,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier?->only(['id', 'name'])),
            'supplier_name' => $this->supplier_name,
            'supplier_ntn' => $this->supplier_ntn,
            'supplier_strn' => $this->supplier_strn,
            'bill_number' => $this->bill_number,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name'])),
            'date' => $this->date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'net_amount' => $this->net_amount,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_rate' => $this->tax_rate,
            'tax_name' => $this->whenLoaded('taxRate', fn () => $this->taxRate?->name),
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'tax_claimable' => $this->tax_claimable,
            'income_tax_withheld' => $this->income_tax_withheld,
            'sales_tax_withheld' => $this->sales_tax_withheld,
            'payable' => $this->payable(),
            'amount_paid' => $this->amount_paid,
            'balance_due' => $this->balanceDue(),
            'status' => $this->status->value,
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'is_overdue' => $this->isOverdue(),
            'is_paid' => $this->isPaid(),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->only(['id', 'name'])),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'notes' => $this->notes,
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (ExpensePayment $payment) => [
                'id' => $payment->id,
                'date' => $payment->date->toDateString(),
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'user' => $payment->user?->only(['id', 'name']),
            ])->values()),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
