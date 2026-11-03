<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
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
            'user_id' => $this->user_id,
            'date' => $this->date->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'is_running' => $this->isRunning(),
            'duration_minutes' => $this->duration_minutes,
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'hourly_rate' => $this->hourly_rate,
            'value' => $this->value(),
            'project_id' => $this->project_id,
            'task_id' => $this->task_id,
            'ticket_id' => $this->ticket_id,
            'work_order_id' => $this->work_order_id,
            'company_id' => $this->company_id,
            'work_type_id' => $this->work_type_id,
            'invoice_id' => $this->invoice_id,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name'])),
            'project' => $this->whenLoaded('project', fn () => $this->project?->only(['id', 'number', 'title'])),
            'task' => $this->whenLoaded('task', fn () => $this->task?->only(['id', 'title'])),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'work_type' => $this->whenLoaded('workType', fn () => $this->workType?->only(['id', 'name'])),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice?->only(['id', 'number'])),
        ];
    }
}
