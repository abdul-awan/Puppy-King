<?php

namespace App\Http\Resources;

use App\Models\ProjectMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectMaterial
 */
class ProjectMaterialResource extends JsonResource
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
            'project_id' => $this->project_id,
            'work_order_id' => $this->work_order_id,
            'product_id' => $this->product_id,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'unit_cost' => $this->unit_cost,
            'tax_rate_id' => $this->tax_rate_id,
            'is_billable' => $this->is_billable,
            'value' => $this->value(),
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice?->only(['id', 'number'])),
        ];
    }
}
