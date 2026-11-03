<?php

namespace App\Http\Middleware;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $team] = [$request->user(), $this->team($request)];

        abort_if(! $user || ! $team || ! $user->belongsToTeam($team), 403);

        $this->ensureTeamMemberHasRequiredRole($user, $team, $minimumRole);

        // Nobody works in a suspended company (its team settings stay reachable, e.g. to leave it).
        if ($request->route('current_team') && $team->isSuspended()) {
            return Inertia::render('errors/CompanySuspended', [
                'company' => $team->name,
                'reason' => $team->suspension_reason,
            ])->toResponse($request)->setStatusCode(403);
        }

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        // Scope every team-owned model to this team for the rest of the request.
        // This middleware runs before route model binding, so bound records are scoped too.
        app(CurrentTeam::class)->set($team);

        // The team is resolved now; drop the {current_team} URL segment so controllers
        // only receive their own parameters (URLs are still generated via URL defaults).
        $request->route()?->forgetParameter('current_team');

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureTeamMemberHasRequiredRole(User $user, Team $team, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $role = $user->teamRole($team);

        $requiredRole = TeamRole::tryFrom($minimumRole);

        abort_if(
            $requiredRole === null ||
            $role === null ||
            ! $role->isAtLeast($requiredRole),
            403,
        );
    }

    /**
     * Get the team associated with the request.
     */
    protected function team(Request $request): ?Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        return $team;
    }
}
