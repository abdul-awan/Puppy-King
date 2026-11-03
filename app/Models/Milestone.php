<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A phase of a project that groups tasks (e.g. "Design", "Build", "Handover").
 *
 * @property int $id
 * @property int $team_id
 * @property int $project_id
 * @property string $name
 * @property CarbonImmutable|null $due_date
 * @property int $sort
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['name', 'due_date', 'sort'])]
class Milestone extends Model
{
    use BelongsToTeam;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sort' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
