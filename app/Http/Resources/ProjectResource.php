<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'number' => $this->number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'is_open' => $this->status->isOpen(),
            'is_overdue' => $this->isOverdue(),
            'billing_method' => $this->billing_method->value,
            'billing_method_label' => $this->billing_method->label(),
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'budget_amount' => $this->budget_amount,
            'budget_hours' => $this->budget_hours,
            'hourly_rate' => $this->hourly_rate,
            'currency' => $this->currency,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'deal_id' => $this->deal_id,
            'quotation_id' => $this->quotation_id,
            'responsible_user_id' => $this->responsible_user_id,
            'customer_name' => $this->customerName(),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'deal' => $this->whenLoaded('deal', fn () => $this->deal?->only(['id', 'title'])),
            'quotation' => $this->whenLoaded('quotation', fn () => $this->quotation?->only(['id', 'number'])),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => $this->responsibleUser?->only(['id', 'name'])),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values()),
            'tasks_count' => $this->whenCounted('tasks'),
            'done_tasks_count' => $this->whenHas('done_tasks_count'),
            'logged_minutes' => $this->whenAggregated('timeEntries', 'duration_minutes', 'sum', fn ($value) => (int) $value),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
