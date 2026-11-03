<?php

namespace App\Http\Requests\Payroll;

use App\Enums\EmploymentType;
use App\Enums\PaymentMethod;
use App\Enums\SalaryTaxMode;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Support\CurrentTeam;
use App\Support\TeamRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = app(CurrentTeam::class)->id();
        $employee = $this->route('employee');
        $ignore = $employee instanceof Employee ? $employee->id : null;

        return [
            // Codes stay taken by removed employees too (their payslips keep showing them).
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-_. ]+$/', Rule::unique('employees', 'code')->where('team_id', $teamId)->ignore($ignore)],
            'name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'joining_date' => ['required', 'date'],
            'leaving_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'basic_salary' => ['required', 'numeric', 'between:0,999999999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_title' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'tax_mode' => ['required', Rule::enum(SalaryTaxMode::class)],
            'fixed_tax' => ['nullable', 'required_if:tax_mode,fixed', 'numeric', 'between:0,999999999'],
            'eobi' => ['boolean'],
            'eobi_number' => ['nullable', 'string', 'max:30'],
            'opening_tax_year' => ['nullable', 'required_with:opening_taxable,opening_tax', 'integer', 'between:2000,2100'],
            'opening_taxable' => ['nullable', 'numeric', 'between:0,99999999999'],
            'opening_tax' => ['nullable', 'numeric', 'between:0,99999999999'],
            'user_id' => [
                'nullable', 'integer', TeamRule::member(),
                Rule::unique('employees', 'user_id')->where('team_id', $teamId)->whereNull('deleted_at')->ignore($ignore),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['array', 'max:30'],
            'items.*.pay_component_id' => ['required', 'integer', 'distinct', TeamRule::exists('pay_components')],
            'items.*.value' => ['required', 'numeric', 'between:0,999999999'],
        ];
    }

    /**
     * Percentages cannot be more than 100.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var array<int, array{pay_component_id?: mixed, value?: mixed}> $items */
            $items = (array) $this->input('items', []);
            $components = PayComponent::query()
                ->whereKey(array_filter(array_map(fn (array $item) => (int) ($item['pay_component_id'] ?? 0), $items)))
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                $component = $components->get((int) ($item['pay_component_id'] ?? 0));

                if ($component !== null && $component->amount_type->isPercent() && (float) ($item['value'] ?? 0) > 100) {
                    $validator->errors()->add("items.{$index}.value", "{$component->name} is a percentage: at most 100.");
                }
            }
        }];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Another employee already has this code.',
            'code.regex' => 'The code may only use letters, digits, spaces and / - _ .',
            'cnic.regex' => 'Type the CNIC as 13 digits, e.g. 35202-1234567-1.',
            'fixed_tax.required_if' => 'Type the income tax to deduct every month.',
            'opening_tax_year.required_with' => 'Choose the tax year of the salary paid before.',
            'leaving_date.after_or_equal' => 'The leaving date cannot be before the joining date.',
            'user_id.unique' => 'This login already belongs to another employee.',
            'items.*.pay_component_id.distinct' => 'Each allowance or deduction can only be on the salary once.',
        ];
    }

    /**
     * Get the employee's details.
     *
     * @return array<string, mixed>
     */
    public function employee(): array
    {
        $cnic = $this->validated('cnic');
        $digits = is_string($cnic) ? preg_replace('/\D/', '', $cnic) : null;

        return [
            ...$this->safe()->except(['items', 'code']),
            'cnic' => $digits ? substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1) : null,
            'basic_salary' => number_format((float) $this->validated('basic_salary'), 2, '.', ''),
            'fixed_tax' => $this->validated('tax_mode') === SalaryTaxMode::Fixed->value
                ? number_format((float) $this->validated('fixed_tax'), 2, '.', '')
                : null,
            'eobi' => $this->boolean('eobi'),
            ...$this->opening(),
        ];
    }

    /**
     * Get the salary and tax of the tax year before the payroll was used (none when no amount is given).
     *
     * @return array{opening_tax_year: int|null, opening_taxable: string|null, opening_tax: string|null}
     */
    protected function opening(): array
    {
        $taxable = $this->validated('opening_taxable');
        $tax = $this->validated('opening_tax');

        if (! filled($taxable) && ! filled($tax)) {
            return ['opening_tax_year' => null, 'opening_taxable' => null, 'opening_tax' => null];
        }

        return [
            'opening_tax_year' => (int) $this->validated('opening_tax_year'),
            'opening_taxable' => number_format((float) $taxable, 2, '.', ''),
            'opening_tax' => number_format((float) $tax, 2, '.', ''),
        ];
    }

    /**
     * Get the allowances, deductions and employer contributions of the salary.
     *
     * @return list<array{pay_component_id: int, value: string}>
     */
    public function items(): array
    {
        /** @var array<int, array{pay_component_id: int|string, value: int|float|string}> $items */
        $items = $this->validated('items', []);

        return array_values(array_map(fn (array $item) => [
            'pay_component_id' => (int) $item['pay_component_id'],
            'value' => number_format((float) $item['value'], 2, '.', ''),
        ], $items));
    }
}
