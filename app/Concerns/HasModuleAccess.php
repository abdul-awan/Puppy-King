<?php

namespace App\Concerns;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\Membership;
use App\Models\Team;

/**
 * Per-module permission checks for a user inside a team.
 */
trait HasModuleAccess
{
    /**
     * Memberships already loaded during this request, keyed by team id.
     *
     * @var array<int, Membership|null>
     */
    protected array $membershipCache = [];

    /**
     * Get the user's membership of the given team.
     */
    public function membershipFor(Team $team): ?Membership
    {
        if (! array_key_exists($team->id, $this->membershipCache)) {
            $this->membershipCache[$team->id] = $this->teamMemberships()->where('team_id', $team->id)->first();
        }

        return $this->membershipCache[$team->id];
    }

    /**
     * Get the user's access level for a module in the given team.
     */
    public function moduleAccess(Team $team, Module $module): AccessLevel
    {
        return $this->membershipFor($team)?->access($module) ?? AccessLevel::None;
    }

    /**
     * Determine if the user has at least the given access to a module in the team.
     */
    public function canAccessModule(Team $team, Module $module, AccessLevel $level = AccessLevel::Read): bool
    {
        return $this->moduleAccess($team, $module)->allows($level);
    }

    /**
     * Get the user's access level for every module in the team.
     *
     * @return array<string, string>
     */
    public function moduleAccessMap(Team $team): array
    {
        $membership = $this->membershipFor($team);

        if ($membership === null) {
            return array_fill_keys(array_map(fn (Module $module) => $module->value, Module::cases()), AccessLevel::None->value);
        }

        return $membership->accessMap();
    }

    /**
     * Forget the cached memberships (e.g. after permissions changed).
     */
    public function forgetMembershipCache(): void
    {
        $this->membershipCache = [];
    }
}
