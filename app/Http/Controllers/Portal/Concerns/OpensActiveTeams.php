<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\Team;
use App\Support\CurrentTeam;

/**
 * Customer links (documents, tickets, the support form) of a suspended company do not open.
 */
trait OpensActiveTeams
{
    /**
     * Scope the request to the team of the opened link, unless the company is suspended.
     */
    protected function openTeam(Team $team): void
    {
        abort_if($team->isSuspended(), 403, __('This page is temporarily unavailable.'));

        app(CurrentTeam::class)->set($team);
    }
}
