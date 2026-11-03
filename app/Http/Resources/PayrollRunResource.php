<?php

namespace App\Http\Resources;

use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollRun
 */
class PayrollRunResource extends JsonResource
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
            'period' => $this->period->toDateString(),
            'label' => $this->label(),
            'tax_year' => $this->taxYear(),
            'pay_date' => $this->pay_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'employees_count' => $this->employees_count,
            'gross' => $this->gross,
            'deductions' => $this->deductions,
            'income_tax' => $this->income_tax,
            'net' => $this->net,
            'employer_cost' => $this->employer_cost,
            'notes' => $this->notes,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->only(['id', 'name'])),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
