<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\User;
use App\Support\Reports\Sql;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform overview for administrators: how many companies and users there are,
 * how many joined lately and which companies are in use.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $firstMonth = $today->subMonthsNoOverflow(11)->startOfMonth();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'companies' => Team::query()->count(),
                'companies_new' => Team::query()->where('created_at', '>=', $monthStart)->count(),
                'companies_suspended' => Team::query()->whereNotNull('suspended_at')->count(),
                'companies_active' => ActivityLog::query()->withoutGlobalScopes()->where('created_at', '>=', now()->subDays(30))->distinct()->count('team_id'),
                'users' => User::query()->count(),
                'users_new' => User::query()->where('created_at', '>=', $monthStart)->count(),
                'users_unverified' => User::query()->whereNull('email_verified_at')->count(),
                'admins' => User::query()->where('is_platform_admin', true)->count(),
                'invoices_month' => Invoice::query()->withoutGlobalScopes()->whereNull('deleted_at')
                    ->where('status', '!=', InvoiceStatus::Draft)->where('issued_at', '>=', $monthStart)->count(),
            ],
            'signups' => $this->signups($firstMonth, $today),
            'recentCompanies' => Team::query()
                ->withCount('members')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Team $team) => [
                    'slug' => $team->slug,
                    'name' => $team->name,
                    'members_count' => (int) $team->getAttribute('members_count'),
                    'suspended' => $team->isSuspended(),
                    'created_at' => $team->created_at?->toIso8601String(),
                ])
                ->values(),
            'recentUsers' => User::query()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'verified' => $user->email_verified_at !== null,
                    'created_at' => $user->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    /**
     * New companies and users per month.
     *
     * @return list<array{month: string, companies: int, users: int}>
     */
    protected function signups(CarbonImmutable $first, CarbonImmutable $today): array
    {
        $month = Sql::month('created_at');
        $count = fn (Builder $query) => $query
            ->where('created_at', '>=', $first)
            ->toBase()
            ->selectRaw("{$month} as month")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($month)
            ->pluck('total', 'month');

        $companies = $count(Team::query()->withTrashed());
        $users = $count(User::query());
        $months = [];

        for ($date = $first; $date->lessThanOrEqualTo($today); $date = $date->addMonthNoOverflow()) {
            $key = $date->format('Y-m');
            $months[] = [
                'month' => $key,
                'companies' => (int) ($companies[$key] ?? 0),
                'users' => (int) ($users[$key] ?? 0),
            ];
        }

        return $months;
    }
}
