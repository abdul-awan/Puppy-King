<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\LineType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A row on a quotation, invoice or credit note.
 *
 * @property int $id
 * @property int $team_id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property LineType $type
 * @property int|null $product_id
 * @property string $description
 * @property string|null $details
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price
 * @property string $discount_percent
 * @property int|null $tax_rate_id
 * @property string $tax_rate
 * @property string|null $tax_name
 * @property string|null $hs_code
 * @property string $subtotal
 * @property string $net_amount
 * @property string $tax_amount
 * @property string $total
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 */
#[Fillable([
    'team_id', 'type', 'product_id', 'description', 'details', 'quantity', 'unit', 'unit_price',
    'discount_percent', 'tax_rate_id', 'tax_rate', 'tax_name', 'hs_code', 'subtotal', 'net_amount',
    'tax_amount', 'total', 'source_type', 'source_id', 'sort',
])]
class DocumentLine extends Model
{
    use BelongsToTeam;

    /**
     * Get the document the line belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the product on the line, if any.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the record the line was created from (e.g. a time entry), if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Present the line for pages and PDFs.
     *
     * @return array<string, mixed>
     */
    public function toPresentation(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'product_id' => $this->product_id,
            'description' => $this->description,
            'details' => $this->details,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'discount_percent' => $this->discount_percent,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_rate' => $this->tax_rate,
            'tax_name' => $this->tax_name,
            'hs_code' => $this->hs_code,
            'subtotal' => $this->subtotal,
            'net_amount' => $this->net_amount,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LineType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:3',
            'subtotal' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sort' => 'integer',
        ];
    }
}
