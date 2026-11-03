<?php

use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\DeleteTeam;
use App\Enums\TemplateLayout;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TeamNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00'));
    Storage::fake('local');
});

/**
 * Fill a company with data of every module, mostly through the app like a user would.
 */
function fillCompanyWithData($test, User $owner, Team $team): void
{
    $team->refresh();
    $route = fn (string $name, array $parameters = []) => route($name, ['current_team' => $team->slug, ...$parameters]);

    $company = Company::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create();
    $contact->companies()->attach($company->id, ['position' => 'Owner']);
    $tag = new Tag(['name' => 'VIP', 'color' => 'blue']);
    $tag->team_id = $team->id;
    $tag->save();
    $company->tags()->attach($tag->id);

    $invoice = issuedInvoice($test, $owner, $team, $company);
    $test->actingAs($owner)->post($route('invoices.payments.store', ['invoice' => $invoice->id]), [
        'date' => '2026-09-30', 'amount' => '1000', 'method' => 'cash',
    ])->assertSessionHasNoErrors();
    $test->actingAs($owner)->post($route('invoices.credit', ['invoice' => $invoice->id]))->assertSessionHasNoErrors();

    Deal::factory()->forTeam($team)->create(['company_id' => $company->id]);
    $project = Project::factory()->for($team)->create(['company_id' => $company->id]);
    $test->actingAs($owner)->post($route('time.store'), [
        'date' => '2026-09-30', 'duration_minutes' => 60, 'project_id' => $project->id,
    ])->assertSessionHasNoErrors();

    Ticket::factory()->for($team)->create(['company_id' => $company->id]);
    WorkOrder::factory()->for($team)->create(['company_id' => $company->id]);
    CalendarEvent::factory()->for($team)->create(['organizer_id' => $owner->id])->attendees()->attach($owner->id);

    $test->actingAs($owner)->post($route('notes.store'), [
        'notable_type' => 'company', 'notable_id' => $company->id, 'body' => 'Pays on time.',
    ])->assertSessionHasNoErrors();
    $test->actingAs($owner)->post($route('attachments.store'), [
        'attachable_type' => 'company', 'attachable_id' => $company->id,
        'files' => [UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf')],
    ])->assertSessionHasNoErrors();
    $test->actingAs($owner)->post($route('settings.invoice-templates.store'), [
        'name' => 'Brand', 'layout' => 'modern', 'use_for' => ['invoice'],
        'definition' => TemplateLayout::Modern->definition()->withPage(['accent' => '#b91c1c'])->toArray(),
    ])->assertSessionHasNoErrors();

    // Payroll: an employee with an allowance and an advance, and the payroll of a month.
    $test->actingAs($owner)->post($route('employees.store'), [
        'name' => 'Sana Iqbal', 'employment_type' => 'permanent', 'joining_date' => '2025-01-01', 'basic_salary' => '80000',
        'payment_method' => 'cash', 'tax_mode' => 'auto', 'eobi' => true,
        'items' => [['pay_component_id' => PayComponent::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'House rent allowance')->value('id'), 'value' => '20000']],
    ])->assertSessionHasNoErrors();
    $employee = Employee::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
    $test->actingAs($owner)->post($route('employees.advances.store', ['employee' => $employee->id]), [
        'date' => '2026-09-01', 'amount' => '10000', 'installment' => '5000',
    ])->assertSessionHasNoErrors();
    $test->actingAs($owner)->post($route('payroll.store'), ['month' => '2026-09'])->assertSessionHasNoErrors();

    // Daily expenses with a receipt, and the cash box.
    $test->actingAs($owner)->post($route('daily-expenses.store'), [
        'date' => '2026-09-30', 'description' => 'Tea', 'amount' => '500', 'payment_method' => 'cash',
        'files' => [UploadedFile::fake()->image('receipt.jpg')],
    ])->assertSessionHasNoErrors();
    $test->actingAs($owner)->post($route('cash-book.store'), [
        'date' => '2026-09-30', 'direction' => 'in', 'amount' => '10000', 'description' => 'Opening balance',
    ])->assertSessionHasNoErrors();

    Notification::send($owner, new TeamNotification($team->id, 'task.assigned', 'Task for you', null, "/{$team->slug}/tasks"));
}

/**
 * The number of records a company has in every company table.
 *
 * @return array<string, int>
 */
function companyRecordCounts(Team $team): array
{
    $counts = [];

    foreach (app(DeleteTeam::class)->tablesInDeleteOrder() as $table) {
        $counts[$table] = DB::table($table)->where('team_id', $team->id)->count();
    }

    return $counts;
}

test('deleting a team removes all its data and files, and nothing of other teams', function () {
    [$owner] = teamOwner();
    $team = app(CreateTeam::class)->handle($owner, 'Indus Traders');
    [$otherOwner, $otherTeam] = teamOwner();

    fillCompanyWithData($this, $owner, $team);
    fillCompanyWithData($this, $otherOwner, $otherTeam);

    $before = companyRecordCounts($team);
    $otherBefore = companyRecordCounts($otherTeam);

    expect($before['invoices'])->toBeGreaterThan(0)
        ->and($before['deals'])->toBe(1)
        ->and($before['attachments'])->toBe(2)
        ->and($before['document_templates'])->toBe(1)
        ->and($before['payslips'])->toBe(1)
        ->and($before['payslip_lines'])->toBeGreaterThan(3)
        ->and($before['employee_advances'])->toBe(1)
        ->and($before['daily_expenses'])->toBe(1)
        ->and($before['petty_cash_entries'])->toBe(1)
        ->and(Storage::disk('local')->allFiles("teams/{$team->id}"))->not->toBeEmpty();

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $team), ['name' => 'Indus Traders'])
        ->assertRedirect(route('teams.index'));

    expect(Team::withTrashed()->find($team->id))->toBeNull();

    foreach (companyRecordCounts($team) as $table => $count) {
        expect($count)->toBe(0, "{$table} still holds data of the deleted team");
    }

    // Link tables, notifications and files of the deleted team are gone as well.
    expect(DB::table('company_contact')->whereNotIn('company_id', DB::table('companies')->select('id'))->count())->toBe(0)
        ->and(DB::table('taggables')->whereNotIn('tag_id', DB::table('tags')->select('id'))->count())->toBe(0)
        ->and(DB::table('calendar_event_attendees')->whereNotIn('calendar_event_id', DB::table('calendar_events')->select('id'))->count())->toBe(0)
        ->and(DB::table('notifications')->where('data->team_id', $team->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles("teams/{$team->id}"))->toBeEmpty();

    // The other team is untouched.
    expect(companyRecordCounts($otherTeam))->toBe($otherBefore)
        ->and(Storage::disk('local')->allFiles("teams/{$otherTeam->id}"))->not->toBeEmpty()
        ->and(DB::table('notifications')->where('data->team_id', $otherTeam->id)->count())->toBe(1);

    // The owner keeps the account and continues in their own team.
    expect($owner->fresh()->current_team_id)->toBe($owner->personalTeam()?->id);
});

test('platform administrators delete any company after typing its name', function () {
    [$admin] = teamOwner();
    $admin->forceFill(['is_platform_admin' => true])->save();
    [$owner, $team] = teamOwner();
    fillCompanyWithData($this, $owner, $team);

    $this->actingAs($owner)
        ->delete(route('admin.companies.destroy', $team), ['name' => $team->name])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('admin.companies.destroy', $team), ['name' => 'Something else'])
        ->assertSessionHasErrors('name');

    expect(Team::query()->find($team->id))->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.companies.destroy', $team), ['name' => $team->name])
        ->assertRedirect(route('admin.companies.index'));

    expect(Team::withTrashed()->find($team->id))->toBeNull()
        ->and(array_sum(companyRecordCounts($team)))->toBe(0);

    // This was the owner's only company: the account gets a new, empty one and keeps working.
    $owner->refresh();
    $newTeam = $owner->currentTeam;

    expect($owner->teams()->count())->toBe(1)
        ->and($newTeam)->not->toBeNull()
        ->and($newTeam?->id)->not->toBe($team->id)
        ->and($newTeam?->is_personal)->toBeTrue()
        ->and(DB::table('invoices')->where('team_id', $newTeam?->id)->count())->toBe(0);

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $newTeam?->slug]))
        ->assertOk();
});

test('when deleting fails halfway, nothing is deleted', function () {
    [$owner] = teamOwner();
    $team = app(CreateTeam::class)->handle($owner, 'Indus Traders');
    fillCompanyWithData($this, $owner, $team);
    $before = companyRecordCounts($team);

    // A wrong order: pipelines cannot be deleted while deals still refer to them.
    $broken = new class(app(CreateTeam::class)) extends DeleteTeam
    {
        public function tablesInDeleteOrder(): array
        {
            return ['invoices', 'deal_pipelines', 'deals'];
        }
    };

    expect(fn () => $broken->handle($team, $owner))->toThrow(QueryException::class);

    expect(Team::query()->find($team->id))->not->toBeNull()
        ->and(companyRecordCounts($team))->toBe($before)
        ->and(Storage::disk('local')->allFiles("teams/{$team->id}"))->not->toBeEmpty();
});
