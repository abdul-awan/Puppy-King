<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sale = (float) $this->sale_price;
        $purchase = $this->purchase_price !== null ? (float) $this->purchase_price : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'unit' => $this->unit,
            'sale_price' => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'margin_percent' => $purchase !== null && $sale > 0 ? round(($sale - $purchase) / $sale * 100, 1) : null,
            'category_id' => $this->category_id,
            'tax_rate_id' => $this->tax_rate_id,
            'hs_code' => $this->hs_code,
            'track_stock' => $this->track_stock,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'custom_fields' => (object) ($this->custom_fields ?? []),
            'created_at' => $this->created_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => $this->category?->only(['id', 'name'])),
            'tax_rate' => $this->whenLoaded('taxRate', fn () => $this->taxRate ? [
                'id' => $this->taxRate->id,
                'name' => $this->taxRate->name,
                'rate' => $this->taxRate->rate,
            ] : null),
        ];
    }
}
