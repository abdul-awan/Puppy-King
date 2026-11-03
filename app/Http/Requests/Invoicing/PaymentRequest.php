<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'income_tax_withheld' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'sales_tax_withheld' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'A payment cannot be dated in the future.',
        ];
    }

    /**
     * Get the payment details.
     *
     * @return array{date: string, amount: string, income_tax_withheld: string, sales_tax_withheld: string, method: string, reference: string|null, notes: string|null}
     */
    public function payment(): array
    {
        return [
            'date' => $this->string('date')->toString(),
            'amount' => $this->decimal('amount'),
            'income_tax_withheld' => $this->decimal('income_tax_withheld'),
            'sales_tax_withheld' => $this->decimal('sales_tax_withheld'),
            'method' => $this->string('method')->toString(),
            'reference' => $this->filled('reference') ? $this->string('reference')->toString() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
        ];
    }

    /**
     * Get a money field as a string with two decimals (empty means zero).
     */
    private function decimal(string $key): string
    {
        return number_format((float) ($this->input($key) ?: 0), 2, '.', '');
    }
}
