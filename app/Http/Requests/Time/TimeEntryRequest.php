<?php

namespace App\Http\Requests\Time;

use App\Models\Task;
use App\Support\TeamRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TimeEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', TeamRule::member()],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'task_id' => ['nullable', 'integer', TeamRule::exists('tasks', softDeletes: true)],
            'ticket_id' => ['nullable', 'integer', TeamRule::exists('tickets', softDeletes: true)],
            'work_order_id' => ['nullable', 'integer', TeamRule::exists('work_orders', softDeletes: true)],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'work_type_id' => ['nullable', 'integer', TeamRule::exists('work_types')],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_billable' => ['boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * A task must belong to the chosen project.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->filled('task_id')) {
                return;
            }

            $projectId = Task::query()->whereKey($this->integer('task_id'))->value('project_id');

            if ($projectId !== null && (int) $projectId !== $this->integer('project_id')) {
                $validator->errors()->add('task_id', 'The task is not part of this project.');
            }
        }];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Time cannot be logged in the future.',
            'duration_minutes.min' => 'Enter how long you worked.',
            'duration_minutes.max' => 'A single entry can be at most 24 hours.',
        ];
    }
}
