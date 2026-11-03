<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property bool $is_default
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, DealPhase> $phases
 */
#[Fillable(['name', 'is_default', 'sort'])]
class DealPipeline extends Model
{
    use BelongsToTeam;

    /**
     * Get the phases of the pipeline in order.
     *
     * @return HasMany<DealPhase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(DealPhase::class, 'pipeline_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * Get the deals in the pipeline.
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
