<?php

use App\Enums\TeamRole;
use App\Models\DailyExpense;
use App\Models\DailyExpenseCategory;
use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use App\Support\PettyCash;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// The examples use fixed dates in fiscal year 2026-27.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00')));

/**
 * One of the team's daily expense categories by name.
 */
function dailyCategory(Team $team, string $name): DailyExpenseCategory
{
    return DailyExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->where('name', $name)->firstOrFail();
}

/**
 * Enter a daily expense through the app and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function addDailyExpense($test, User $user, Team $team, array $overrides = []): DailyExpense
{
    $test->actingAs($user)
        ->post(route('daily-expenses.store', ['current_team' => $team->slug]), array_merge([
            'date' => '2026-10-15',
            'category_id' => dailyCategory($team, 'Tea & refreshments')->id,
            'description' => 'Tea for the client meeting',
            'amount' => '850',
            'payment_method' => 'cash',
            'paid_by' => 'Aslam',
            'paid_to' => 'Cafe Zouk',
        ], $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return DailyExpense::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

/**
 * Put cash into (or take it out of) the cash box through the app.
 */
function addCash($test, User $user, Team $team, string $date, string $amount, string $direction = 'in'): void
{
    $test->actingAs($user)
        ->post(route('cash-book.store', ['current_team' => $team->slug]), [
            'date' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'description' => $direction === 'in' ? 'Cash withdrawn from the bank' : 'Returned to the bank',
        ])
        ->assertSessionHasNoErrors();
}

test('new teams get daily expense categories', function () {
    [, $team] = teamOwner();

    expect(DailyExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(14);
});

test('a daily expense is entered with its receipt and numbered', function () {
    Storage::fake('local');
    [$user, $team] = teamOwner();

    $expense = addDailyExpense($this, $user, $team, [
        'files' => [UploadedFile::fake()->image('receipt.jpg')],
    ]);

    expect($expense->number)->toBe('DE-2627-0001')
        ->and($expense->amount)->toBe('850.00')
        ->and($expense->created_by)->toBe($user->id)
        ->and($expense->attachments()->sole()->name)->toBe('receipt.jpg');

    addDailyExpense($this, $user, $team, ['description' => 'Petrol for the van', 'amount' => '5000', 'payment_method' => 'card', 'category_id' => dailyCategory($team, 'Fuel')->id]);
    addDailyExpense($this, $user, $team, ['date' => '2026-10-14', 'amount' => '300']);

    $this->actingAs($user)
        ->get(route('daily-expenses.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('daily-expenses/Index')
            ->has('expenses.data', 3)
            ->where('dayTotals.2026-10-15.total', '5850.00')
            ->where('dayTotals.2026-10-14.total', '300.00')
            ->where('summary.today', '5850.00')
            ->where('summary.month', '6150.00')
            ->where('summary.month_cash', '1150.00'));
});

test('receipts must be photos or pdfs', function () {
    Storage::fake('local');
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('daily-expenses.store', ['current_team' => $team->slug]), [
            'date' => '2026-10-15',
            'description' => 'Tea',
            'amount' => '100',
            'payment_method' => 'cash',
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])
        ->assertSessionHasErrors('files.0');
});

test('the cash book keeps track of the cash in hand', function () {
    [$user, $team] = teamOwner();

    addCash($this, $user, $team, '2026-09-30', '20000');
    addDailyExpense($this, $user, $team, ['date' => '2026-09-30', 'amount' => '1500']);
    addDailyExpense($this, $user, $team, ['amount' => '850']);
    // Paid by card: not from the cash box.
    addDailyExpense($this, $user, $team, ['amount' => '5000', 'payment_method' => 'card']);
    addCash($this, $user, $team, '2026-10-15', '2000', 'out');

    withinTeam($team, function () {
        expect((string) PettyCash::balance())->toBe('15650.00')
            ->and((string) PettyCash::openingBalance(CarbonImmutable::parse('2026-10-01')))->toBe('18500.00');
    });

    $this->actingAs($user)
        ->get(route('cash-book.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('daily-expenses/CashBook')
            ->where('summary.opening', '18500.00')
            ->where('summary.expenses', '850.00')
            ->where('summary.taken_out', '2000.00')
            ->where('summary.closing', '15650.00')
            ->has('movements', 2)
            ->where('movements.1.balance', '15650.00'));

    $csv = $this->actingAs($user)->get(route('cash-book.export', ['current_team' => $team->slug]));
    expect($csv->streamedContent())->toContain('Cash at the start')->toContain('15650.00');
});

test('daily expenses are filtered, exported and printed', function () {
    [$user, $team] = teamOwner();
    addDailyExpense($this, $user, $team);
    addDailyExpense($this, $user, $team, ['description' => 'Petrol for the van', 'amount' => '5000', 'category_id' => dailyCategory($team, 'Fuel')->id]);

    $this->actingAs($user)
        ->get(route('daily-expenses.index', ['current_team' => $team->slug, 'category' => dailyCategory($team, 'Fuel')->id]))
        ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1)->where('filtered.total', '5000.00'));

    $csv = $this->actingAs($user)->get(route('daily-expenses.export', ['current_team' => $team->slug, 'search' => 'petrol']));
    expect($csv->streamedContent())->toContain('Petrol for the van')->not->toContain('Tea for the client meeting');

    $this->actingAs($user)
        ->get(route('daily-expenses.pdf', ['current_team' => $team->slug, 'period' => 'this_month']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('daily expenses are changed and deleted', function () {
    [$user, $team] = teamOwner();
    $expense = addDailyExpense($this, $user, $team);

    $this->actingAs($user)
        ->put(route('daily-expenses.update', ['current_team' => $team->slug, 'daily_expense' => $expense->id]), [
            'date' => '2026-10-15',
            'description' => 'Tea and samosas',
            'amount' => '1200',
            'payment_method' => 'cash',
        ])
        ->assertSessionHasNoErrors();

    expect($expense->fresh()?->amount)->toBe('1200.00')
        ->and($expense->fresh()?->number)->toBe('DE-2627-0001');

    $this->actingAs($user)
        ->delete(route('daily-expenses.destroy', ['current_team' => $team->slug, 'daily_expense' => $expense->id]), ['to_list' => true])
        ->assertRedirect(route('daily-expenses.index', ['current_team' => $team->slug]));

    expect(DailyExpense::withTrashed()->withoutGlobalScopes()->find($expense->id)?->trashed())->toBeTrue();
});

test('members need access to the daily expenses', function () {
    [, $team] = teamOwner();
    $staff = teamMember($team);
    $viewer = teamMember($team, TeamRole::Member, ['daily_expenses' => 'read']);
    $nobody = teamMember($team, TeamRole::Member, ['daily_expenses' => 'none']);

    // Members can enter daily expenses unless an owner or admin limits them.
    addDailyExpense($this, $staff, $team);

    $this->actingAs($viewer)->get(route('daily-expenses.index', ['current_team' => $team->slug]))->assertOk();
    $this->actingAs($viewer)->post(route('daily-expenses.store', ['current_team' => $team->slug]), [
        'date' => '2026-10-15', 'description' => 'Tea', 'amount' => '100', 'payment_method' => 'cash',
    ])->assertForbidden();
    $this->actingAs($nobody)->get(route('daily-expenses.index', ['current_team' => $team->slug]))->assertForbidden();
});

test('the daily expenses report adds up per category', function () {
    [$user, $team] = teamOwner();
    addDailyExpense($this, $user, $team);
    addDailyExpense($this, $user, $team, ['amount' => '150']);
    addDailyExpense($this, $user, $team, ['amount' => '5000', 'payment_method' => 'card', 'category_id' => dailyCategory($team, 'Fuel')->id]);

    $this->actingAs($user)
        ->get(route('reports.daily-expenses', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/DailyExpenses')
            ->where('report.totals.total', '6000.00')
            ->where('report.totals.cash', '1000.00')
            ->where('report.categories.0.name', 'Fuel')
            ->where('report.categories.1.total', '1000.00')
            ->where('report.timeline.unit', 'day'));
});

test('the search box finds daily expenses and employees', function () {
    [$user, $team] = teamOwner();
    addDailyExpense($this, $user, $team, ['description' => 'Courier to Lahore']);
    Employee::factory()->create(['team_id' => $team->id, 'name' => 'Zainab Courier']);

    $groups = collect($this->actingAs($user)
        ->getJson(route('search', ['current_team' => $team->slug, 'q' => 'courier']))
        ->assertOk()
        ->json('groups'))->pluck('label')->all();

    expect($groups)->toContain('Daily expenses')->toContain('Employees');
});
