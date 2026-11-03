<?php

namespace App\Http\Resources;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarEvent
 */
class CalendarEventResource extends JsonResource
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'color' => $this->type->color(),
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at->format('Y-m-d\TH:i'),
            'all_day' => $this->all_day,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'outcome' => $this->outcome,
            'reminder_minutes' => $this->reminder_minutes,
            'organizer_id' => $this->organizer_id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'deal_id' => $this->deal_id,
            'project_id' => $this->project_id,
            'organizer' => $this->whenLoaded('organizer', fn () => $this->organizer?->only(['id', 'name'])),
            'attendees' => $this->whenLoaded('attendees', fn () => $this->attendees->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values()),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'deal' => $this->whenLoaded('deal', fn () => $this->deal?->only(['id', 'title'])),
            'project' => $this->whenLoaded('project', fn () => $this->project?->only(['id', 'title'])),
        ];
    }
}
