<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-member module access, hourly rate and daily capacity.
 */
class MemberAccessController extends Controller
{
    /**
     * List the members with their access levels.
     */
    public function index(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();

        return Inertia::render('company-settings/Members', [
            'members' => Membership::query()
                ->with('user:id,name,email')
                ->where('team_id', $team->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Membership $membership) => [
                    'user_id' => $membership->user_id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'full_access' => $membership->role->isAtLeast(TeamRole::Admin),
                    'access' => $membership->accessMap(),
                    'hourly_rate' => $membership->hourly_rate,
                    'daily_capacity_minutes' => $membership->daily_capacity_minutes,
                ]),
            'modules' => Module::options(),
            'levels' => AccessLevel::options(),
            'canManage' => $this->canManage($request->user()),
            'teamsUrl' => route('teams.edit', ['team' => $team->slug]),
        ]);
    }

    /**
     * Update a member's access, hourly rate and capacity.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManage($request->user()), 403);

        $team = app(CurrentTeam::class)->team();

        $membership = Membership::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $rules = [
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'daily_capacity_minutes' => ['required', 'integer', 'between:0,1440'],
            'access' => ['array'],
        ];

        foreach (Module::cases() as $module) {
            $rules["access.{$module->value}"] = ['nullable', Rule::enum(AccessLevel::class)];
        }

        $validated = $request->validate($rules);

        $attributes = [
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'daily_capacity_minutes' => (int) $validated['daily_capacity_minutes'],
        ];

        // Owners and admins always have full access, so only store module access for members.
        if (! $membership->role->isAtLeast(TeamRole::Admin)) {
            $attributes['permissions'] = array_filter(
                array_intersect_key($validated['access'] ?? [], array_flip(array_map(fn (Module $module) => $module->value, Module::cases()))),
                fn ($value) => $value !== null,
            );
        }

        $membership->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Access for :name saved.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Only owners and admins may change what members can do.
     */
    protected function canManage(User $user): bool
    {
        return $user->teamRole(app(CurrentTeam::class)->team())?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
