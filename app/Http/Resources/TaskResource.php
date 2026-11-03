<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'priority_color' => $this->priority->color(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'estimated_minutes' => $this->estimated_minutes,
            'is_billable' => $this->is_billable,
            'project_id' => $this->project_id,
            'milestone_id' => $this->milestone_id,
            'company_id' => $this->company_id,
            'deal_id' => $this->deal_id,
            'assignee_id' => $this->assignee_id,
            'work_type_id' => $this->work_type_id,
            'project' => $this->whenLoaded('project', fn () => $this->project?->only(['id', 'number', 'title'])),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'deal' => $this->whenLoaded('deal', fn () => $this->deal?->only(['id', 'title'])),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->only(['id', 'name'])),
            'logged_minutes' => $this->whenAggregated('timeEntries', 'duration_minutes', 'sum', fn ($value) => (int) $value),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
