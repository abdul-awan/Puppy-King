<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Goods used on a project and/or a work order (e.g. cables, parts) that can be invoiced to the customer.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $project_id
 * @property int|null $work_order_id
 * @property int|null $product_id
 * @property CarbonImmutable $date
 * @property string $description
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price
 * @property string|null $unit_cost
 * @property int|null $tax_rate_id
 * @property bool $is_billable
 * @property int|null $invoice_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project|null $project
 * @property-read WorkOrder|null $workOrder
 * @property-read Product|null $product
 * @property-read TaxRate|null $taxRate
 * @property-read Invoice|null $invoice
 */
#[Fillable(['product_id', 'date', 'description', 'quantity', 'unit', 'unit_price', 'unit_cost', 'tax_rate_id', 'is_billable', 'created_by'])]
class ProjectMaterial extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * Get the sales value: quantity × unit price.
     */
    public function value(): string
    {
        return (string) BigDecimal::of($this->quantity)->multipliedBy(BigDecimal::of($this->unit_price))->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'is_billable' => 'boolean',
        ];
    }
}
