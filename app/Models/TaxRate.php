<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\TaxAuthority;
use App\Enums\TaxKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $rate
 * @property TaxKind $kind
 * @property TaxAuthority|null $authority
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'rate', 'kind', 'authority', 'description', 'is_default', 'is_active', 'sort'])]
class TaxRate extends Model
{
    use BelongsToTeam;

    /**
     * Scope a query to active tax rates in display order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'kind' => TaxKind::class,
            'authority' => TaxAuthority::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
