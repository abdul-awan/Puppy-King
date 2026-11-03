<?php

namespace App\Concerns;

use App\Models\Team;
use App\Support\CurrentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current team (company workspace).
 *
 * While a current team is set (on every team route), all queries are limited to
 * that team and new records are assigned to it automatically. Outside a team
 * context (console commands, queued jobs) queries are not filtered, so those
 * code paths must constrain team_id themselves.
 */
trait BelongsToTeam
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            $teamId = app(CurrentTeam::class)->id();

            if ($teamId !== null) {
                $builder->where($builder->qualifyColumn('team_id'), $teamId);
            }
        });

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('team_id'))) {
                $model->setAttribute('team_id', app(CurrentTeam::class)->id());
            }
        });
    }

    /**
     * Get the team that owns the record.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the URL slug of the owning team without querying when it is the current team.
     */
    public function teamSlug(): string
    {
        $current = app(CurrentTeam::class)->get();

        if ($current !== null && $current->id === $this->getAttribute('team_id')) {
            return $current->slug;
        }

        /** @var Team $team */
        $team = $this->getRelationValue('team');

        return $team->slug;
    }
}
