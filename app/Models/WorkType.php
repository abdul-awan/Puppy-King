<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A kind of work (consulting, development, installation...) with an optional hourly rate.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $hourly_rate
 * @property bool $is_billable
 * @property bool $is_active
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'hourly_rate', 'is_billable', 'is_active', 'sort'])]
class WorkType extends Model
{
    use BelongsToTeam;

    /**
     * Scope a query to active work types in display order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
