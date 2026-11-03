<?php

namespace App\Http\Requests\Expenses;

use App\Support\TeamOptions;
use App\Support\TeamRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'supplier_name' => ['nullable', 'required_without:company_id', 'string', 'max:255'],
            'supplier_ntn' => ['nullable', 'string', 'max:20'],
            'supplier_strn' => ['nullable', 'string', 'max:20'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', TeamRule::exists('expense_categories')],
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['required', Rule::in(array_keys(TeamOptions::CURRENCIES))],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'net_amount' => ['required', 'numeric', 'between:0,99999999999'],
            'tax_rate_id' => ['nullable', 'integer', TeamRule::exists('tax_rates')],
            'tax_amount' => ['nullable', 'numeric', 'between:0,99999999999'],
            'tax_claimable' => ['boolean'],
            'income_tax_withheld' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'sales_tax_withheld' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'approve_now' => ['boolean'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * The withheld tax cannot be more than the bill.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $total = (float) $this->input('net_amount') + (float) $this->input('tax_amount');
            $withheld = (float) $this->input('income_tax_withheld') + (float) $this->input('sales_tax_withheld');

            if ($withheld > $total + 0.001) {
                $validator->errors()->add('income_tax_withheld', 'The tax withheld cannot be more than the total of the bill.');
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
            'supplier_name.required_without' => 'Choose a supplier or type the supplier\'s name.',
            'due_date.after_or_equal' => 'The due date cannot be before the bill date.',
            'files.*.max' => 'Each file can be at most 20 MB.',
        ];
    }

    /**
     * Get the expense details.
     *
     * @return array<string, mixed>
     */
    public function expense(): array
    {
        return [
            ...$this->safe()->except(['tax_rate_id', 'tax_amount', 'approve_now', 'files']),
            'exchange_rate' => $this->validated('exchange_rate') ?? 1,
            'net_amount' => number_format((float) $this->validated('net_amount'), 2, '.', ''),
            'tax_claimable' => $this->boolean('tax_claimable'),
            'income_tax_withheld' => number_format((float) $this->validated('income_tax_withheld'), 2, '.', ''),
            'sales_tax_withheld' => number_format((float) $this->validated('sales_tax_withheld'), 2, '.', ''),
        ];
    }
}
