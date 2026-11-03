<?php

namespace App\Http\Resources;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomField
 */
class CustomFieldResource extends JsonResource
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
            'entity' => $this->entity->value,
            'entity_label' => $this->entity->label(),
            'label' => $this->label,
            'key' => $this->key,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'options' => $this->options ?? [],
            'is_required' => $this->is_required,
            'sort' => $this->sort,
        ];
    }
}
