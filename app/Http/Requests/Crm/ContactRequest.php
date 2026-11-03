<?php

namespace App\Http\Requests\Crm;

use App\Http\Requests\Concerns\ValidatesAddresses;
use App\Models\Contact;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    use ValidatesAddresses;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'salutation' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'cnic' => ['nullable', 'string', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'language' => ['nullable', 'string', 'max:5'],
            'responsible_user_id' => ['nullable', 'integer', TeamRule::member()],
            'marketing_consent' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', TeamRule::exists('tags')],
            'companies' => ['nullable', 'array', 'max:20'],
            'companies.*.company_id' => ['required', 'integer', 'distinct', TeamRule::exists('companies', softDeletes: true)],
            'companies.*.position' => ['nullable', 'string', 'max:100'],
            'companies.*.is_primary' => ['boolean'],
            'companies.*.is_decision_maker' => ['boolean'],
            ...$this->addressRules(),
            ...Contact::customFieldRules(),
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
            'companies.*.company_id.distinct' => 'A company can only be linked once.',
        ];
    }
}
