<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $pipeline_id
 * @property string $name
 * @property int $probability
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DealPipeline $pipeline
 */
#[Fillable(['team_id', 'pipeline_id', 'name', 'probability', 'sort'])]
class DealPhase extends Model
{
    use BelongsToTeam;

    /**
     * Get the pipeline the phase belongs to.
     *
     * @return BelongsTo<DealPipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(DealPipeline::class, 'pipeline_id');
    }

    /**
     * Get the deals in this phase.
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'phase_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'sort' => 'integer',
        ];
    }
}
