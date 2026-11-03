<?php

namespace App\Http\Resources;

use App\Models\DailyExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DailyExpense
 */
class DailyExpenseResource extends JsonResource
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
            'number' => $this->number,
            'date' => $this->date->toDateString(),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name'])),
            'description' => $this->description,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'paid_by' => $this->paid_by,
            'paid_to' => $this->paid_to,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'attachments_count' => $this->whenCounted('attachments'),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
