<?php

namespace App\Http\Requests\Crm;

use App\Enums\BusinessType;
use App\Enums\CompanyRelationship;
use App\Http\Requests\Concerns\ValidatesAddresses;
use App\Models\Company;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    use ValidatesAddresses;

    /**
     * Normalise input before validation.
     */
    protected function prepareForValidation(): void
    {
        $website = $this->input('website');

        if (is_string($website) && $website !== '' && ! preg_match('#^https?://#i', $website)) {
            $this->merge(['website' => 'https://'.$website]);
        }

        $iban = $this->input('iban');

        if (is_string($iban)) {
            $this->merge(['iban' => strtoupper(str_replace(' ', '', $iban)) ?: null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', Rule::enum(CompanyRelationship::class)],
            'business_type' => ['nullable', Rule::enum(BusinessType::class)],
            'ntn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z-]+$/'],
            'strn' => ['nullable', 'string', 'max:20', 'regex:/^[0-9-]+$/'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'sales_tax_registered' => ['boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'responsible_user_id' => ['nullable', 'integer', TeamRule::member()],
            'payment_term_id' => ['nullable', 'integer', TeamRule::exists('payment_terms')],
            'currency' => ['nullable', Rule::in(array_keys(TeamOptions::CURRENCIES))],
            'language' => ['nullable', 'string', 'max:5'],
            'iban' => ['nullable', 'string', 'max:34'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', TeamRule::exists('tags')],
            ...$this->addressRules(),
            ...Company::customFieldRules(),
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
            'cnic.regex' => 'The CNIC must look like 12345-1234567-1.',
            'ntn.regex' => 'The NTN may only contain digits, letters and dashes.',
            'strn.regex' => 'The STRN may only contain digits and dashes.',
        ];
    }
}
