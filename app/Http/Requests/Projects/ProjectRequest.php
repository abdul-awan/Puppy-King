<?php

namespace App\Http\Requests\Projects;

use App\Enums\BillingMethod;
use App\Enums\ProjectStatus;
use App\Support\TeamOptions;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:20000'],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'contact_id' => ['nullable', 'integer', TeamRule::exists('contacts')],
            'deal_id' => ['nullable', 'integer', TeamRule::exists('deals', softDeletes: true)],
            'quotation_id' => ['nullable', 'integer', TeamRule::exists('quotations', softDeletes: true)],
            'responsible_user_id' => ['nullable', 'integer', TeamRule::member()],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'billing_method' => ['required', Rule::enum(BillingMethod::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'budget_hours' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', Rule::in(array_keys(TeamOptions::CURRENCIES))],
            'member_ids' => ['nullable', 'array', 'max:100'],
            'member_ids.*' => ['integer', TeamRule::member()],
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
            'due_date.after_or_equal' => 'The deadline cannot be before the start date.',
        ];
    }

    /**
     * Get the project attributes.
     *
     * @return array<string, mixed>
     */
    public function project(): array
    {
        return $this->safe()->except(['member_ids']);
    }
}
