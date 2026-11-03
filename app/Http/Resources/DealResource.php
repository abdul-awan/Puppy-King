<?php

namespace App\Http\Resources;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deal
 */
class DealResource extends JsonResource
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
            'value' => $this->value,
            'currency' => $this->currency,
            'probability' => $this->probability,
            'effective_probability' => $this->whenLoaded('phase', fn () => $this->effectiveProbability()),
            'weighted_value' => $this->whenLoaded('phase', fn () => $this->weightedValue()),
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'description' => $this->description,
            'lost_note' => $this->lost_note,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'pipeline_id' => $this->pipeline_id,
            'phase_id' => $this->phase_id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'responsible_user_id' => $this->responsible_user_id,
            'source_id' => $this->source_id,
            'department_id' => $this->department_id,
            'lost_reason_id' => $this->lost_reason_id,
            'pipeline' => $this->whenLoaded('pipeline', fn () => $this->pipeline->only(['id', 'name'])),
            'phase' => $this->whenLoaded('phase', fn () => $this->phase->only(['id', 'name', 'probability'])),
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->full_name] : null),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn () => $this->responsibleUser?->only(['id', 'name'])),
            'source' => $this->whenLoaded('source', fn () => $this->source?->only(['id', 'name'])),
            'lost_reason' => $this->whenLoaded('lostReason', fn () => $this->lostReason?->only(['id', 'name'])),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
