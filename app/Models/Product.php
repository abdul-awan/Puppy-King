<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Concerns\HasAttachments;
use App\Concerns\HasCustomFields;
use App\Concerns\HasNotes;
use App\Concerns\LogsActivity;
use App\Contracts\Attachable;
use App\Enums\CustomFieldEntity;
use App\Enums\ProductType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A product or service that can be put on quotations and invoices.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $category_id
 * @property ProductType $type
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property string $unit
 * @property string $sale_price
 * @property string|null $purchase_price
 * @property int|null $tax_rate_id
 * @property string|null $hs_code
 * @property bool $track_stock
 * @property string $stock_quantity
 * @property bool $is_active
 * @property array<string, mixed>|null $custom_fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ProductCategory|null $category
 * @property-read TaxRate|null $taxRate
 */
#[Fillable([
    'category_id', 'type', 'name', 'code', 'description', 'unit', 'sale_price', 'purchase_price',
    'tax_rate_id', 'hs_code', 'track_stock', 'stock_quantity', 'is_active', 'custom_fields',
])]
class Product extends Model implements Attachable
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTeam, HasAttachments, HasCustomFields, HasFactory, HasNotes, LogsActivity, SoftDeletes;

    /**
     * Common units of measure offered in the product form.
     *
     * @var array<int, string>
     */
    public const UNITS = ['pcs', 'hour', 'day', 'month', 'kg', 'g', 'ton', 'litre', 'metre', 'sq ft', 'box', 'carton', 'dozen', 'pack', 'set', 'job', 'trip'];

    /**
     * Get the custom field entity type of the model.
     */
    public static function customFieldEntity(): CustomFieldEntity
    {
        return CustomFieldEntity::Product;
    }

    /**
     * Get the product's category.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Get the product's default tax rate.
     *
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /**
     * Get the stock movements, newest first.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /**
     * Add (positive) or remove (negative) stock and record the movement.
     */
    public function changeStock(string $quantity, ?string $reason = null, ?Model $source = null): StockMovement
    {
        return DB::transaction(function () use ($quantity, $reason, $source) {
            $current = static::query()->withoutGlobalScopes()->withTrashed()->whereKey($this->id)->lockForUpdate()->value('stock_quantity');
            $change = BigDecimal::of($quantity)->toScale(3, RoundingMode::HalfUp);
            $balance = BigDecimal::of((string) $current)->plus($change)->toScale(3);

            static::query()->withoutGlobalScopes()->withTrashed()->whereKey($this->id)->update(['stock_quantity' => (string) $balance]);
            $this->setRawAttributes([...$this->getAttributes(), 'stock_quantity' => (string) $balance], sync: true);

            return StockMovement::create([
                'team_id' => $this->team_id,
                'product_id' => $this->id,
                'quantity' => (string) $change,
                'balance' => (string) $balance,
                'reason' => $reason,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => Auth::id(),
            ]);
        });
    }

    /**
     * Scope a query to products matching a search term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('code', 'like', $like)
            ->orWhere('hs_code', 'like', $like));
    }

    /**
     * Scope a query to active products.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'sale_price' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'track_stock' => 'boolean',
            'stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}
