<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teams\DeleteTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every company (workspace) on the platform: find them, see how they use the software,
 * and suspend or reactivate them.
 */
class CompanyController extends Controller
{
    /**
     * List the companies, newest first.
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($status, ['active', 'suspended'], true) ? $status : 'all',
        ];

        $companies = Team::query()
            ->withCount('members')
            ->with(['memberships' => fn ($query) => $query->where('role', TeamRole::Owner->value)->with('user:id,name,email')])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $like = '%'.addcslashes($filters['search'], '%_\\').'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('members', fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->whereNull('suspended_at'))
            ->when($filters['status'] === 'suspended', fn (Builder $query) => $query->whereNotNull('suspended_at'))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Team $team) => $this->summary($team));

        return Inertia::render('admin/companies/Index', [
            'companies' => JsonResource::collection($companies),
            'filters' => $filters,
        ]);
    }

    /**
     * Show a company: its people, settings and how much it uses the software.
     */
    public function show(Team $team): Response
    {
        $team->loadCount('members');
        $team->load(['memberships' => fn ($query) => $query->where('role', TeamRole::Owner->value)->with('user:id,name,email')]);

        $department = $team->defaultDepartment();
        $settings = $team->teamSettings();
        $lastActivity = ActivityLog::query()->withoutGlobalScope('team')->where('team_id', $team->id)->max('created_at');

        $members = $team->memberships()
            ->with('user:id,name,email,email_verified_at')
            ->get()
            ->sortBy(fn (Membership $membership) => [-$membership->role->level(), $membership->user->name])
            ->map(fn (Membership $membership) => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'verified' => $membership->user->email_verified_at !== null,
                'role' => $membership->role->value,
                'role_label' => $membership->role->label(),
                'joined_at' => $membership->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('admin/companies/Show', [
            'company' => [
                ...$this->summary($team),
                'suspension_reason' => $team->suspension_reason,
                'legal_name' => $department?->displayName(),
                'ntn' => $department?->ntn,
                'strn' => $department?->strn,
                'currency' => $settings->currency(),
                'last_activity_at' => is_string($lastActivity) ? CarbonImmutable::parse($lastActivity)->toIso8601String() : null,
                'pending_invitations' => $team->invitations()->whereNull('accepted_at')->count(),
            ],
            'members' => $members,
            'usage' => [
                ['label' => 'Customers & suppliers', 'count' => $this->count(Company::class, $team)],
                ['label' => 'Contacts', 'count' => $this->count(Contact::class, $team)],
                ['label' => 'Quotations', 'count' => $this->count(Quotation::class, $team)],
                ['label' => 'Invoices issued', 'count' => Invoice::query()->withoutGlobalScope('team')->where('team_id', $team->id)->issued()->count()],
                ['label' => 'Projects', 'count' => $this->count(Project::class, $team)],
                ['label' => 'Tickets', 'count' => $this->count(Ticket::class, $team)],
                ['label' => 'Work orders', 'count' => $this->count(WorkOrder::class, $team)],
                ['label' => 'Expenses', 'count' => $this->count(Expense::class, $team)],
                ['label' => 'Files', 'count' => $this->count(Attachment::class, $team)],
            ],
        ]);
    }

    /**
     * Suspend the company: its people can no longer work in it and its customer links stop working.
     */
    public function suspend(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $team->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => $validated['reason'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':company is suspended.', ['company' => $team->name])]);

        return back();
    }

    /**
     * Let the company work again.
     */
    public function reactivate(Team $team): RedirectResponse
    {
        $team->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':company is active again.', ['company' => $team->name])]);

        return back();
    }

    /**
     * Delete the company for good with all its data, after its name was typed to confirm.
     */
    public function destroy(Request $request, Team $team, DeleteTeam $deleteTeam): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string']]);

        if ($request->string('name')->toString() !== $team->name) {
            throw ValidationException::withMessages(['name' => 'The name does not match. Type the name of the company exactly as shown.']);
        }

        $records = $deleteTeam->handle($team, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':company is deleted, with all its data (:records records).', [
            'company' => $team->name,
            'records' => number_format($records),
        ])]);

        return to_route('admin.companies.index');
    }

    /**
     * The row of a company in lists (the owner must be loaded in "memberships").
     *
     * @return array<string, mixed>
     */
    protected function summary(Team $team): array
    {
        $owner = $team->memberships->first()?->user;

        return [
            'slug' => $team->slug,
            'name' => $team->name,
            'owner' => $owner ? ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email] : null,
            'members_count' => (int) $team->getAttribute('members_count'),
            'is_personal' => $team->is_personal,
            'suspended_at' => $team->suspended_at?->toIso8601String(),
            'created_at' => $team->created_at?->toIso8601String(),
        ];
    }

    /**
     * Count a company's records of one kind (deleted ones excluded).
     *
     * @param  class-string<Model>  $model
     */
    protected function count(string $model, Team $team): int
    {
        return $model::query()->withoutGlobalScope('team')->where('team_id', $team->id)->count();
    }
}
