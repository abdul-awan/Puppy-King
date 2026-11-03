<?php

namespace App\Http\Requests\Projects;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Support\TeamRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaskRequest extends FormRequest
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
            'project_id' => ['nullable', 'integer', TeamRule::exists('projects', softDeletes: true)],
            'milestone_id' => ['nullable', 'integer', TeamRule::exists('milestones')],
            'company_id' => ['nullable', 'integer', TeamRule::exists('companies')],
            'deal_id' => ['nullable', 'integer', TeamRule::exists('deals', softDeletes: true)],
            'assignee_id' => ['nullable', 'integer', TeamRule::member()],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'work_type_id' => ['nullable', 'integer', TeamRule::exists('work_types')],
            'is_billable' => ['boolean'],
        ];
    }

    /**
     * The milestone must be part of the task's project.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->filled('milestone_id')) {
                return;
            }

            $projectId = Milestone::query()->whereKey($this->integer('milestone_id'))->value('project_id');

            if ($projectId === null || (int) $projectId !== $this->integer('project_id')) {
                $validator->errors()->add('milestone_id', 'The milestone is not part of this project.');
            }
        }];
    }
}
