<?php

namespace App\Http\Requests\DailyExpenses;

use App\Enums\PaymentMethod;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyExpenseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'category_id' => ['nullable', 'integer', TeamRule::exists('daily_expense_categories')],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_by' => ['nullable', 'string', 'max:100'],
            'paid_to' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,gif,webp,heic,heif'],
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
            'amount.gt' => 'Type the amount spent.',
            'files.*.max' => 'Each receipt can be at most 20 MB.',
            'files.*.mimes' => 'Attach a photo or a PDF of the receipt.',
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
            ...$this->safe()->except(['files']),
            'amount' => number_format((float) $this->validated('amount'), 2, '.', ''),
        ];
    }
}
