<?php

namespace App\Http\Resources;

use App\Models\Payslip;
use App\Models\PayslipLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payslip
 */
class PayslipResource extends JsonResource
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
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'number' => $this->number,
            'period' => $this->period_start->toDateString(),
            'period_label' => $this->periodLabel(),
            'employee_name' => $this->employee_name,
            'employee_code' => $this->employee_code,
            'employee_email' => $this->whenLoaded('employee', fn () => $this->employee->email),
            'designation' => $this->designation,
            'department' => $this->department,
            'cnic' => $this->cnic,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'bank_name' => $this->bank_name,
            'bank_account_title' => $this->bank_account_title,
            'bank_account_number' => $this->bank_account_number,
            'days_in_period' => $this->days_in_period,
            'employed_days' => $this->employed_days,
            'unpaid_days' => $this->unpaid_days,
            'payable_days' => $this->payable_days,
            'basic_salary' => $this->basic_salary,
            'gross' => $this->gross,
            'taxable_income' => $this->taxable_income,
            'income_tax' => $this->income_tax,
            'tax_override' => $this->tax_override,
            'tax_details' => $this->tax_details,
            'deductions' => $this->deductions,
            'employer_contributions' => $this->employer_contributions,
            'net' => $this->net,
            'paid_on' => $this->paid_on?->toDateString(),
            'payment_reference' => $this->payment_reference,
            'emailed_at' => $this->emailed_at?->toIso8601String(),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (PayslipLine $line) => [
                'id' => $line->id,
                'source' => $line->source->value,
                'kind' => $line->kind->value,
                'name' => $line->name,
                'amount' => $line->amount,
                'taxable_amount' => $line->taxable_amount,
                'pay_component_id' => $line->pay_component_id,
                'employee_advance_id' => $line->employee_advance_id,
            ])->values()),
        ];
    }
}
