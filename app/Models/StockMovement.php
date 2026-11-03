<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A change in the stock of a product: a sale, a return or a manual adjustment.
 *
 * @property int $id
 * @property int $team_id
 * @property int $product_id
 * @property string $quantity
 * @property string $balance
 * @property string|null $reason
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property-read Product $product
 * @property-read Model|null $source
 * @property-read User|null $user
 */
#[Fillable(['team_id', 'product_id', 'quantity', 'balance', 'reason', 'source_type', 'source_id', 'user_id'])]
class StockMovement extends Model
{
    use BelongsToTeam;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Get the document that caused the movement, if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance' => 'decimal:3',
        ];
    }
}
