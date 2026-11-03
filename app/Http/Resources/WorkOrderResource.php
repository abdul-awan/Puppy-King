<?php

namespace App\Http\Resources;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array. The signature image itself is left out (it is large);
     * the detail page receives it separately.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->displayStatus();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'status' => $this->status->value,
            'display_status' => $status['key'],
            'status_label' => $status['label'],
            'status_color' => $status['color'],
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'project_id' => $this->project_id,
            'ticket_id' => $this->ticket_id,
            'assignee_id' => $this->assignee_id,
            'department_id' => $this->department_id,
            'location' => $this->location,
            'description' => $this->description,
            'internal_notes' => $this->internal_notes,
            'show_prices' => $this->show_prices,
            'customer_label' => $this->customerLabel(),
            'customer_email' => $this->customerEmail(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_signed' => $this->isSigned(),
            'signed_by_name' => $this->signed_by_name,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'customer_remarks' => $this->customer_remarks,
            'invoice_id' => $this->invoice_id,
            'invoiced_at' => $this->invoiced_at?->toIso8601String(),
            'public_url' => $this->publicUrl(),
            'logged_minutes' => $this->whenHas('logged_minutes', fn ($minutes) => (int) $minutes),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'project' => $this->whenLoaded('project', fn () => $this->project?->only(['id', 'number', 'title'])),
            'ticket' => $this->whenLoaded('ticket', fn () => $this->ticket?->only(['id', 'number', 'subject'])),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->only(['id', 'name'])),
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->displayName()] : null),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice?->only(['id', 'number'])),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
