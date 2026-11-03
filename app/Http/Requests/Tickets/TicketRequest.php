<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TaskPriority;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'contact_id' => ['nullable', 'integer', TeamRule::exists('contacts')],
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'assignee_id' => ['nullable', 'integer', TeamRule::member()],
            'status' => ['sometimes', 'required', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'channel' => ['sometimes', 'required', Rule::enum(TicketChannel::class)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * Get the ticket attributes.
     *
     * @return array<string, mixed>
     */
    public function ticket(): array
    {
        return $this->safe()->except(['description', 'files']);
    }
}
