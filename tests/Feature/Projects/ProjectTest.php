<?php

use App\Enums\BillingMethod;
use App\Enums\LineType;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Company;
use App\Models\DocumentLine;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * Create a project through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function createProject($test, User $user, Team $team, array $overrides = []): Project
{
    $test->actingAs($user)
        ->post(route('projects.store', ['current_team' => $team->slug]), array_merge([
            'title' => 'New website',
            'status' => 'active',
            'billing_method' => 'time_and_materials',
            'hourly_rate' => '5000',
            'currency' => 'PKR',
            'responsible_user_id' => $user->id,
        ], $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return Project::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

/**
 * Log billable time on a project directly (without the form).
 */
function logTime(Project $project, User $user, int $minutes, string $rate, ?int $workTypeId = null, string $date = '2026-09-29'): TimeEntry
{
    $entry = new TimeEntry(['project_id' => $project->id, 'company_id' => $project->company_id, 'work_type_id' => $workTypeId, 'date' => $date, 'duration_minutes' => $minutes, 'hourly_rate' => $rate]);
    $entry->team_id = $project->team_id;
    $entry->user_id = $user->id;
    $entry->save();

    return $entry;
}

test('a project gets a number and its team', function () {
    [$user, $team] = teamOwner();
    $colleague = teamMember($team);

    $project = createProject($this, $user, $team, ['member_ids' => [$colleague->id]]);

    expect($project->number)->toBe('PRJ-0001')
        ->and($project->status)->toBe(ProjectStatus::Active)
        ->and($project->members()->pluck('users.id')->sort()->values()->all())->toBe(collect([$user->id, $colleague->id])->sort()->values()->all());
});

test('a project can start from an accepted quotation', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $quotation = Quotation::factory()->for($team)->create(['company_id' => $company->id, 'status' => QuotationStatus::Accepted, 'title' => 'ERP rollout']);
    withinTeam($team, fn () => $quotation->replaceLines([
        ['type' => 'item', 'description' => 'Consulting', 'quantity' => '10', 'unit_price' => '20000', 'tax_rate_id' => salesTax($team)->id],
    ]));

    $this->actingAs($user)
        ->get(route('projects.create', ['current_team' => $team->slug, 'quotation' => $quotation->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Create')
            ->where('prefill.title', 'ERP rollout')
            ->where('prefill.company.id', $company->id)
            ->where('prefill.billing_method', BillingMethod::FixedPrice->value)
            ->where('prefill.budget_amount', '200000.00'));
});

test('tasks are added to a project, ticked off and opened again', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    $project = createProject($this, $user, $team, ['company_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('tasks.store', ['current_team' => $team->slug]), [
            'title' => 'Design the homepage',
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'estimated_minutes' => 240,
            'priority' => 'high',
        ])
        ->assertSessionHasNoErrors();

    $task = Task::withoutGlobalScopes()->where('project_id', $project->id)->sole();

    expect($task->company_id)->toBe($company->id)
        ->and($task->status)->toBe(TaskStatus::Todo);

    $this->actingAs($user)->patch(route('tasks.complete', ['current_team' => $team->slug, 'task' => $task->id]));

    expect($task->fresh()->status)->toBe(TaskStatus::Done)
        ->and($task->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('tasks.complete', ['current_team' => $team->slug, 'task' => $task->id]));

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->fresh()->completed_at)->toBeNull();
});

test('a task can only use a milestone of its own project', function () {
    [$user, $team] = teamOwner();
    $project = createProject($this, $user, $team);
    $other = createProject($this, $user, $team, ['title' => 'Other']);

    $this->actingAs($user)->post(route('projects.milestones.store', ['current_team' => $team->slug, 'project' => $other->id]), ['name' => 'Design']);
    $foreignMilestone = Milestone::withoutGlobalScopes()->where('project_id', $other->id)->sole();

    $this->actingAs($user)
        ->post(route('tasks.store', ['current_team' => $team->slug]), [
            'title' => 'Wrong milestone',
            'project_id' => $project->id,
            'milestone_id' => $foreignMilestone->id,
        ])
        ->assertSessionHasErrors('milestone_id');
});

test('deleting a milestone keeps its tasks', function () {
    [$user, $team] = teamOwner();
    $project = createProject($this, $user, $team);
    $this->actingAs($user)->post(route('projects.milestones.store', ['current_team' => $team->slug, 'project' => $project->id]), ['name' => 'Build', 'due_date' => '2026-10-31']);
    $milestone = Milestone::withoutGlobalScopes()->where('project_id', $project->id)->sole();
    $this->actingAs($user)->post(route('tasks.store', ['current_team' => $team->slug]), ['title' => 'Code it', 'project_id' => $project->id, 'milestone_id' => $milestone->id]);

    $this->actingAs($user)->delete(route('milestones.destroy', ['current_team' => $team->slug, 'milestone' => $milestone->id]));

    expect(Task::withoutGlobalScopes()->where('project_id', $project->id)->sole()->milestone_id)->toBeNull();
});

test('the project page adds up time, materials and progress', function () {
    [$user, $team] = teamOwner();
    $project = createProject($this, $user, $team, ['budget_amount' => '100000', 'budget_hours' => '20']);
    logTime($project, $user, 90, '5000');
    logTime($project, $user, 30, '5000');
    withinTeam($team, function () use ($project) {
        $material = new ProjectMaterial(['date' => '2026-09-29', 'description' => 'Router', 'quantity' => 2, 'unit_price' => 7500, 'unit_cost' => 6000]);
        $material->team_id = $project->team_id;
        $material->project_id = $project->id;
        $material->save();

        Task::factory()->create(['team_id' => $project->team_id, 'project_id' => $project->id, 'status' => TaskStatus::Done]);
        Task::factory()->create(['team_id' => $project->team_id, 'project_id' => $project->id]);
    });

    $this->actingAs($user)
        ->get(route('projects.show', ['current_team' => $team->slug, 'project' => $project->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Show')
            ->where('metrics.logged_minutes', 120)
            ->where('metrics.budget_minutes', 1200)
            ->where('metrics.time_value', fn ($value) => (float) $value === 10000.0)
            ->where('metrics.materials_value', fn ($value) => (float) $value === 15000.0)
            ->where('metrics.unbilled_value', fn ($value) => (float) $value === 25000.0)
            ->where('metrics.materials_cost', fn ($value) => (float) $value === 12000.0)
            ->where('metrics.budget_used_percent', 25)
            ->where('metrics.progress_percent', 50)
            ->has('tasks', 2)
            ->has('timeEntries', 2)
            ->has('materials', 1));
});

test('unbilled time and materials become a draft invoice', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['sales_tax_registered' => true]);
    $project = createProject($this, $user, $team, ['company_id' => $company->id]);
    $development = WorkType::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Development')->firstOrFail();
    $consulting = WorkType::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Consulting')->firstOrFail();
    logTime($project, $user, 120, '5000', $development->id, '2026-09-01');
    logTime($project, $user, 60, '5000', $development->id, '2026-09-15');
    logTime($project, $user, 45, '8000', $consulting->id, '2026-09-20');
    withinTeam($team, function () use ($project) {
        $material = new ProjectMaterial(['date' => '2026-09-29', 'description' => 'Router', 'quantity' => 1, 'unit_price' => 7500, 'is_billable' => true]);
        $material->team_id = $project->team_id;
        $material->project_id = $project->id;
        $material->save();
    });

    $this->actingAs($user)
        ->post(route('projects.invoice', ['current_team' => $team->slug, 'project' => $project->id]), ['mode' => 'time_materials'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $invoice = Invoice::withoutGlobalScopes()->where('project_id', $project->id)->sole();
    $items = $invoice->lines()->where('type', LineType::Item)->get();

    expect($invoice->company_id)->toBe($company->id)
        ->and($items)->toHaveCount(3)
        ->and($items->map(fn (DocumentLine $line) => [$line->quantity, $line->unit_price])->all())->toBe([
            ['3.000', '5000.0000'],
            ['0.750', '8000.0000'],
            ['1.000', '7500.0000'],
        ])
        ->and($invoice->subtotal)->toBe('28500.00')
        ->and(TimeEntry::withoutGlobalScopes()->where('project_id', $project->id)->whereNull('invoice_id')->count())->toBe(0);

    // Nothing is invoiced twice.
    $this->actingAs($user)
        ->post(route('projects.invoice', ['current_team' => $team->slug, 'project' => $project->id]), ['mode' => 'time_materials'])
        ->assertSessionHasErrors('project');

    // Deleting the draft gives the work back to the project.
    $this->actingAs($user)->delete(route('invoices.destroy', ['current_team' => $team->slug, 'invoice' => $invoice->id]));

    expect(TimeEntry::withoutGlobalScopes()->where('project_id', $project->id)->whereNull('invoice_id')->count())->toBe(3)
        ->and(ProjectMaterial::withoutGlobalScopes()->where('project_id', $project->id)->whereNull('invoice_id')->count())->toBe(1);
});

test('a fixed price is invoiced in parts', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create(['sales_tax_registered' => true]);
    $project = createProject($this, $user, $team, ['company_id' => $company->id, 'billing_method' => 'fixed_price', 'budget_amount' => '200000']);

    $this->actingAs($user)
        ->post(route('projects.invoice', ['current_team' => $team->slug, 'project' => $project->id]), [
            'mode' => 'fixed', 'amount' => '100000', 'description' => '50% advance',
        ])
        ->assertSessionHasNoErrors();

    $invoice = Invoice::withoutGlobalScopes()->where('project_id', $project->id)->sole();

    expect($invoice->subtotal)->toBe('100000.00')
        ->and($invoice->lines()->sole()->details)->toBe('50% advance');
});

test('a project needs a customer to be invoiced', function () {
    [$user, $team] = teamOwner();
    $project = createProject($this, $user, $team);
    logTime($project, $user, 60, '5000');

    $this->actingAs($user)
        ->post(route('projects.invoice', ['current_team' => $team->slug, 'project' => $project->id]), ['mode' => 'time_materials'])
        ->assertSessionHasErrors('project');
});

test('invoicing a project needs access to invoicing', function () {
    [$owner, $team] = teamOwner();
    $project = createProject($this, $owner, $team);
    $member = teamMember($team, TeamRole::Member, ['projects' => 'write', 'invoicing' => 'read']);

    $this->actingAs($member)
        ->post(route('projects.invoice', ['current_team' => $team->slug, 'project' => $project->id]), ['mode' => 'time_materials'])
        ->assertForbidden();
});

test('deleting a project also deletes its tasks', function () {
    [$user, $team] = teamOwner();
    $project = createProject($this, $user, $team);
    $this->actingAs($user)->post(route('tasks.store', ['current_team' => $team->slug]), ['title' => 'Code it', 'project_id' => $project->id]);

    $this->actingAs($user)->delete(route('projects.destroy', ['current_team' => $team->slug, 'project' => $project->id]))->assertRedirect();

    expect($project->fresh()->trashed())->toBeTrue()
        ->and(Task::withoutGlobalScopes()->where('project_id', $project->id)->sole()->trashed())->toBeTrue();
});

test('the task list shows my open tasks first', function () {
    [$user, $team] = teamOwner();
    $colleague = teamMember($team);

    withinTeam($team, function () use ($team, $user, $colleague) {
        Task::factory()->create(['team_id' => $team->id, 'title' => 'Mine', 'assignee_id' => $user->id]);
        Task::factory()->create(['team_id' => $team->id, 'title' => 'Done already', 'assignee_id' => $user->id, 'status' => TaskStatus::Done]);
        Task::factory()->create(['team_id' => $team->id, 'title' => 'Theirs', 'assignee_id' => $colleague->id]);
    });

    $this->actingAs($user)
        ->get(route('tasks.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tasks/Index')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Mine'));

    $this->actingAs($user)
        ->get(route('tasks.index', ['current_team' => $team->slug, 'assignee' => 'all', 'status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('tasks.data', 3));
});

test('projects of other teams cannot be reached', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $foreign = createProject($this, $otherUser, $otherTeam);

    $this->actingAs($user)
        ->get(route('projects.show', ['current_team' => $team->slug, 'project' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('tasks.store', ['current_team' => $team->slug]), ['title' => 'Sneaky', 'project_id' => $foreign->id])
        ->assertSessionHasErrors('project_id');
});

test('the company page lists its projects', function () {
    [$user, $team] = teamOwner();
    $company = Company::factory()->for($team)->create();
    createProject($this, $user, $team, ['company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $company->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related.projects.total', 1)
            ->where('related.projects.items.0.number', 'PRJ-0001'));
});
