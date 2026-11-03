<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
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
            'line_1' => $this->line_1,
            'line_2' => $this->line_2,
            'city' => $this->city,
            'province' => $this->province?->value,
            'province_label' => $this->province?->label(),
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'single_line' => $this->singleLine(),
        ];
    }
}
