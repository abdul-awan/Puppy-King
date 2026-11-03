<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealRequest extends FormRequest
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
            'company_id' => ['nullable', 'required_without:contact_id', 'integer', TeamRule::exists('companies', softDeletes: true)],
            'contact_id' => ['nullable', 'integer', TeamRule::exists('contacts', softDeletes: true)],
            'pipeline_id' => ['required', 'integer', TeamRule::exists('deal_pipelines')],
            'phase_id' => ['required', 'integer', TeamRule::exists('deal_phases')->where('pipeline_id', (int) $this->input('pipeline_id'))],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['required', Rule::in(array_keys(TeamOptions::CURRENCIES))],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'integer', TeamRule::member()],
            'source_id' => ['nullable', 'integer', TeamRule::exists('deal_sources')],
            'department_id' => ['nullable', 'integer', TeamRule::exists('departments')],
            'description' => ['nullable', 'string', 'max:10000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', TeamRule::exists('tags')],
            ...Deal::customFieldRules(),
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
            'company_id.required_without' => 'Choose a company or a contact for this deal.',
            'phase_id.exists' => 'The phase does not belong to the chosen pipeline.',
        ];
    }
}
