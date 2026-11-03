<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isPlatformAdmin' => $user?->isPlatformAdmin() === true,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'permissions' => fn () => $user?->currentTeam ? $user->moduleAccessMap($user->currentTeam) : (object) [],
            'company' => fn () => $user?->currentTeam ? [
                'currency' => $user->currentTeam->teamSettings()->currency(),
                'fiscalYearStartMonth' => $user->currentTeam->teamSettings()->fiscalYearStartMonth(),
            ] : null,
            'timer' => fn () => $user?->currentTeam && $user->canAccessModule($user->currentTeam, Module::TimeTracking)
                ? $this->runningTimer($user)
                : null,
            'notifications' => fn () => $user?->current_team_id !== null ? $this->notifications($user) : null,
            // Employees with a login see "My payslips" in the menu.
            'hasPayslips' => fn () => $user?->current_team_id !== null && Employee::query()
                ->withoutGlobalScopes()
                ->where('team_id', $user->current_team_id)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->exists(),
        ];
    }

    /**
     * The user's newest notifications in the current team, for the bell in the header.
     *
     * @return array{unread: int, items: list<array{id: string, kind: string, title: string, body: string|null, read: bool, created_at: string|null}>}
     */
    protected function notifications(User $user): array
    {
        $teamId = $user->current_team_id;

        return [
            'unread' => $user->unreadNotifications()->where('data->team_id', $teamId)->count(),
            'items' => array_values($user->notifications()
                ->where('data->team_id', $teamId)
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => (string) $notification->id,
                    'kind' => (string) ($notification->data['kind'] ?? $notification->type),
                    'title' => (string) ($notification->data['title'] ?? ''),
                    'body' => isset($notification->data['body']) ? (string) $notification->data['body'] : null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }

    /**
     * The user's running timer in the current team, shown in the header.
     *
     * @return array{id: int, started_at: string|null, description: string|null, project: string|null, minutes_before: int}|null
     */
    protected function runningTimer(User $user): ?array
    {
        $entry = TimeEntry::query()
            ->withoutGlobalScopes()
            ->where('team_id', $user->current_team_id)
            ->where('user_id', $user->id)
            ->running()
            ->latest('id')
            ->first();

        if ($entry === null) {
            return null;
        }

        return [
            'id' => $entry->id,
            'started_at' => $entry->started_at?->toIso8601String(),
            'description' => $entry->description,
            'project' => $entry->project_id !== null ? Project::query()->withoutGlobalScopes()->whereKey($entry->project_id)->value('title') : null,
            'minutes_before' => $entry->duration_minutes,
        ];
    }
}
