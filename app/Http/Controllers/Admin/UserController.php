<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every user on the platform: find them, see their companies, confirm their e-mail address
 * and give or take platform admin rights.
 */
class UserController extends Controller
{
    /**
     * List the users, newest first.
     */
    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString();

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'filter' => in_array($filter, ['admins', 'unverified'], true) ? $filter : 'all',
        ];

        $users = User::query()
            ->withCount('teams')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $like = '%'.addcslashes($filters['search'], '%_\\').'%';

                $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters['filter'] === 'admins', fn (Builder $query) => $query->where('is_platform_admin', true))
            ->when($filters['filter'] === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => $this->summary($user));

        return Inertia::render('admin/users/Index', [
            'users' => JsonResource::collection($users),
            'filters' => $filters,
        ]);
    }

    /**
     * Show a user with the companies they belong to.
     */
    public function show(Request $request, User $user): Response
    {
        $user->loadCount('teams');

        $lastActive = DB::table('sessions')->where('user_id', $user->id)->max('last_activity');

        return Inertia::render('admin/users/Show', [
            'user' => [
                ...$this->summary($user),
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'last_active_at' => is_numeric($lastActive) ? CarbonImmutable::createFromTimestamp((int) $lastActive)->toIso8601String() : null,
                'is_you' => $user->is($request->user()),
            ],
            // Memberships of deleted companies are left out.
            'companies' => $user->teamMemberships()
                ->whereHas('team')
                ->with('team')
                ->get()
                ->sortBy(fn (Membership $membership) => $membership->team->name)
                ->map(fn (Membership $membership) => [
                    'slug' => $membership->team->slug,
                    'name' => $membership->team->name,
                    'role_label' => $membership->role->label(),
                    'suspended' => $membership->team->isSuspended(),
                    'is_current' => $membership->team_id === $user->current_team_id,
                ])
                ->values(),
        ]);
    }

    /**
     * Give the user platform admin rights.
     */
    public function grant(User $user): RedirectResponse
    {
        $user->forceFill(['is_platform_admin' => true])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now a platform administrator.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Take the platform admin rights away (not your own, and never from the last administrator).
     */
    public function revoke(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot take your own platform admin rights away. Ask another administrator.']);
        }

        if (User::query()->where('is_platform_admin', true)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'The platform needs at least one administrator.']);
        }

        $user->forceFill(['is_platform_admin' => false])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is no longer a platform administrator.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Confirm the user's e-mail address (e.g. when the confirmation e-mail did not arrive).
     */
    public function verify(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The e-mail address of :name is confirmed.', ['name' => $user->name])]);

        return back();
    }

    /**
     * The row of a user in lists.
     *
     * @return array<string, mixed>
     */
    protected function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'verified' => $user->email_verified_at !== null,
            'is_platform_admin' => $user->isPlatformAdmin(),
            'teams_count' => (int) $user->getAttribute('teams_count'),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
