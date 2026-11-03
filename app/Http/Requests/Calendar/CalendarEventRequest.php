<?php

namespace App\Http\Requests\Calendar;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Support\TeamRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEventRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EventType::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'all_day' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'contact_id' => ['nullable', 'integer', TeamRule::exists('contacts')],
            'deal_id' => ['nullable', 'integer', TeamRule::exists('deals', softDeletes: true)],
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'status' => ['sometimes', 'required', Rule::enum(EventStatus::class)],
            'outcome' => ['nullable', 'string', 'max:10000'],
            'reminder_minutes' => ['nullable', 'integer', Rule::in([0, 5, 10, 15, 30, 60, 120, 1440])],
            'attendee_ids' => ['nullable', 'array', 'max:50'],
            'attendee_ids.*' => ['integer', TeamRule::member()],
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
            'ends_at.after_or_equal' => 'The end cannot be before the start.',
        ];
    }
}
