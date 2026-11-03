<?php

namespace App\Support;

use App\Models\Team;
use RuntimeException;

/**
 * Holds the team (company workspace) that the current request operates on.
 *
 * It is set by the EnsureTeamMembership middleware for team routes and is used
 * by the BelongsToTeam trait to scope every query to that team.
 */
class CurrentTeam
{
    protected ?Team $team = null;

    /**
     * Set the current team.
     */
    public function set(?Team $team): void
    {
        $this->team = $team;
    }

    /**
     * Get the current team, if one has been resolved.
     */
    public function get(): ?Team
    {
        return $this->team;
    }

    /**
     * Get the current team's id, if one has been resolved.
     */
    public function id(): ?int
    {
        return $this->team?->id;
    }

    /**
     * Get the current team or fail when none has been resolved.
     */
    public function team(): Team
    {
        return $this->team ?? throw new RuntimeException('No current team has been resolved for this request.');
    }
}
