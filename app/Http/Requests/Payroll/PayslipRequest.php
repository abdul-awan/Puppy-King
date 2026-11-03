<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeAdvance;
use App\Models\Payslip;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The changes to a draft payslip: unpaid days, one-off earnings and deductions, the advance
 * recoveries of the month and, if needed, the income tax set by hand.
 */
class PayslipRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unpaid_days' => ['required', 'numeric', 'between:0,31'],
            'tax_override' => ['nullable', 'numeric', 'between:0,999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'adjustments' => ['array', 'max:30'],
            'adjustments.*.kind' => ['required', Rule::in(['earning', 'deduction'])],
            'adjustments.*.name' => ['required', 'string', 'max:100'],
            'adjustments.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'adjustments.*.taxable' => ['boolean'],
            'advances' => ['array', 'max:30'],
            'advances.*.id' => ['required', 'integer', 'distinct'],
            'advances.*.amount' => ['required', 'numeric', 'between:0,999999999'],
        ];
    }

    /**
     * Advance recoveries must belong to the employee and cannot be more than what is left of the advance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $payslip = $this->route('payslip');

            if (! $payslip instanceof Payslip) {
                return;
            }

            /** @var array<int, array{id?: mixed, amount?: mixed}> $recoveries */
            $recoveries = (array) $this->input('advances', []);
            $advances = EmployeeAdvance::query()
                ->where('employee_id', $payslip->employee_id)
                ->whereKey(array_map(fn (array $recovery) => (int) ($recovery['id'] ?? 0), $recoveries))
                ->get()
                ->keyBy('id');

            foreach ($recoveries as $index => $recovery) {
                $advance = $advances->get((int) ($recovery['id'] ?? 0));

                if ($advance === null) {
                    $validator->errors()->add("advances.{$index}.id", 'This advance does not belong to the employee.');

                    continue;
                }

                $left = BigDecimal::of($advance->amount)->minus($advance->recovered(includeDrafts: true, exceptPayslipId: $payslip->id));

                if (is_numeric($recovery['amount'] ?? null) && BigDecimal::of((string) $recovery['amount'])->isGreaterThan($left)) {
                    $validator->errors()->add("advances.{$index}.amount", 'At most '.number_format((float) (string) $left, 2).' is left of this advance.');
                }
            }
        }];
    }

    /**
     * Get the one-off earnings and deductions.
     *
     * @return list<array{kind: string, name: string, amount: string, taxable: bool}>
     */
    public function adjustments(): array
    {
        /** @var array<int, array{kind: string, name: string, amount: int|float|string, taxable?: bool|int|string}> $adjustments */
        $adjustments = $this->validated('adjustments', []);

        return array_values(array_map(fn (array $adjustment) => [
            'kind' => $adjustment['kind'],
            'name' => trim($adjustment['name']),
            'amount' => number_format((float) $adjustment['amount'], 2, '.', ''),
            'taxable' => $adjustment['kind'] === 'earning' && filter_var($adjustment['taxable'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ], $adjustments));
    }

    /**
     * Get the advance recoveries (leaving out the ones set to zero).
     *
     * @return list<array{id: int, amount: string}>
     */
    public function recoveries(): array
    {
        /** @var array<int, array{id: int|string, amount: int|float|string}> $recoveries */
        $recoveries = $this->validated('advances', []);

        return array_values(array_filter(array_map(fn (array $recovery) => [
            'id' => (int) $recovery['id'],
            'amount' => number_format((float) $recovery['amount'], 2, '.', ''),
        ], $recoveries), fn (array $recovery) => (float) $recovery['amount'] > 0));
    }
}
