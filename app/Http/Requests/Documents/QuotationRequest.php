<?php

namespace App\Http\Requests\Documents;

use App\Http\Requests\Concerns\ValidatesDocumentLines;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuotationRequest extends FormRequest
{
    use ValidatesDocumentLines;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', TeamRule::exists('departments')],
            'deal_id' => ['nullable', 'integer', TeamRule::exists('deals', softDeletes: true)],
            'company_id' => ['nullable', 'required_without:contact_id', 'integer', TeamRule::exists('companies')],
            'contact_id' => ['nullable', 'integer', TeamRule::exists('contacts')],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:date'],
            'currency' => ['required', Rule::in(array_keys(TeamOptions::CURRENCIES))],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'apply_further_tax' => ['boolean'],
            'introduction' => ['nullable', 'string', 'max:10000'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            ...$this->lineRules(),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->requireItemLine($validator)];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->lineMessages(),
            'company_id.required_without' => 'Choose a company or a contact.',
        ];
    }
}
