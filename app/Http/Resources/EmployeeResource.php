<?php

namespace App\Http\Resources;

use App\Models\Employee;
use App\Models\EmployeePayItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'cnic' => $this->cnic,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'designation' => $this->designation,
            'department' => $this->department,
            'employment_type' => $this->employment_type->value,
            'employment_type_label' => $this->employment_type->label(),
            'joining_date' => $this->joining_date->toDateString(),
            'leaving_date' => $this->leaving_date?->toDateString(),
            'has_left' => $this->hasLeft(),
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'basic_salary' => $this->basic_salary,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'bank_name' => $this->bank_name,
            'bank_account_title' => $this->bank_account_title,
            'bank_account_number' => $this->bank_account_number,
            'tax_mode' => $this->tax_mode->value,
            'tax_mode_label' => $this->tax_mode->label(),
            'fixed_tax' => $this->fixed_tax,
            'eobi' => $this->eobi,
            'eobi_number' => $this->eobi_number,
            'opening_tax_year' => $this->opening_tax_year,
            'opening_taxable' => $this->opening_taxable,
            'opening_tax' => $this->opening_tax,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name', 'email'])),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('payItems', fn () => $this->payItems->map(fn (EmployeePayItem $item) => [
                'id' => $item->id,
                'pay_component_id' => $item->pay_component_id,
                'name' => $item->component->name,
                'kind' => $item->component->kind->value,
                'amount_type' => $item->component->amount_type->value,
                'value' => $item->value,
                'tax_label' => $item->component->taxLabel(),
            ])->values()),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
