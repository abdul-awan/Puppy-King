<?php

namespace App\Http\Requests\WorkOrders;

use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkOrderRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'contact_id' => ['nullable', 'integer', TeamRule::exists('contacts')],
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'ticket_id' => ['nullable', 'integer', TeamRule::exists('tickets', softDeletes: true)],
            'assignee_id' => ['nullable', 'integer', TeamRule::member()],
            'department_id' => ['nullable', 'integer', TeamRule::exists('departments')],
            'location' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
            'show_prices' => ['boolean'],
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
            'title.required' => 'Describe the job in a few words, e.g. "Service of the air conditioners".',
        ];
    }
}
