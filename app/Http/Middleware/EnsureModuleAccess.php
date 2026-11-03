<?php

namespace App\Http\Middleware;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Support\CurrentTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the user may use a module of the current team.
 *
 * Usage: ->middleware('module:invoicing') requires view access for GET/HEAD
 * requests and full access for all other methods; ->middleware('module:settings,write')
 * forces a specific level.
 */
class EnsureModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module, ?string $level = null): Response
    {
        $team = app(CurrentTeam::class)->get();
        $user = $request->user();

        $required = $level !== null
            ? AccessLevel::from($level)
            : ($request->isMethodSafe() ? AccessLevel::Read : AccessLevel::Write);

        abort_unless(
            $team !== null && $user !== null && $user->canAccessModule($team, Module::from($module), $required),
            403,
        );

        return $next($request);
    }
}
