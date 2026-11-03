<?php

use App\Enums\ExpenseStatus;
use App\Enums\TeamRole;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// The examples use fixed dates in fiscal year 2026-27.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * A valid expense form submission: an electricity bill of 10,000 + 18% sales tax, approved right away.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function expensePayload(Team $team, array $overrides = []): array
{
    return array_merge([
        'title' => 'Electricity September',
        'supplier_name' => 'K-Electric',
        'supplier_ntn' => '0710122-3',
        'bill_number' => 'KE-998877',
        'category_id' => ExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Electricity, gas & water')->value('id'),
        'date' => '2026-09-30',
        'due_date' => '2026-10-10',
        'currency' => 'PKR',
        'net_amount' => '10000',
        'tax_rate_id' => salesTax($team)->id,
        'tax_claimable' => true,
        'approve_now' => true,
    ], $overrides);
}

/**
 * Enter an expense through the form and return it.
 *
 * @param  array<string, mixed>  $overrides
 */
function createExpense($test, User $user, Team $team, array $overrides = []): Expense
{
    $test->actingAs($user)
        ->post(route('expenses.store', ['current_team' => $team->slug]), expensePayload($team, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return Expense::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

test('new teams get expense categories', function () {
    [, $team] = teamOwner();

    expect(ExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(14);
});

test('an expense is entered with its sales tax and numbered', function () {
    [$user, $team] = teamOwner();

    $expense = createExpense($this, $user, $team);

    expect($expense->number)->toBe('EXP-2627-0001')
        ->and($expense->status)->toBe(ExpenseStatus::Approved)
        ->and($expense->approved_by)->toBe($user->id)
        ->and($expense->net_amount)->toBe('10000.00')
        ->and($expense->tax_rate)->toBe('18.000')
        ->and($expense->tax_amount)->toBe('1800.00')
        ->and($expense->total)->toBe('11800.00')
        ->and($expense->payable())->toBe('11800.00')
        ->and($expense->displayStatus()['label'])->toBe('To pay');
});

test('the tax can be corrected to match the bill', function () {
    [$user, $team] = teamOwner();

    $expense = createExpense($this, $user, $team, ['tax_amount' => '1799.50']);

    expect($expense->tax_amount)->toBe('1799.50')
        ->and($expense->total)->toBe('11799.50');
});

test('the supplier details come from the company', function () {
    [$user, $team] = teamOwner();
    $supplier = Company::factory()->for($team)->create(['name' => 'Lucky Cement', 'ntn' => '1111111-1', 'strn' => '32-77-8888-999-11']);

    $expense = createExpense($this, $user, $team, ['company_id' => $supplier->id, 'supplier_name' => null, 'supplier_ntn' => null]);

    expect($expense->supplier_name)->toBe('Lucky Cement')
        ->and($expense->supplier_ntn)->toBe('1111111-1')
        ->and($expense->supplier_strn)->toBe('32-77-8888-999-11');
});

test('a supplier is required', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('expenses.store', ['current_team' => $team->slug]), expensePayload($team, ['supplier_name' => '']))
        ->assertSessionHasErrors('supplier_name');
});

test('the scanned bill is uploaded with the expense', function () {
    Storage::fake('local');
    [$user, $team] = teamOwner();

    $expense = createExpense($this, $user, $team, [
        'files' => [UploadedFile::fake()->create('ke-bill.pdf', 120, 'application/pdf')],
    ]);

    $attachment = $expense->attachments()->sole();

    expect($attachment->name)->toBe('ke-bill.pdf')
        ->and($attachment->user_id)->toBe($user->id);

    Storage::disk('local')->assertExists($attachment->path);
});

test('removing a team for good also removes its files', function () {
    Storage::fake('local');
    [$user, $team] = teamOwner();
    $expense = createExpense($this, $user, $team, [
        'files' => [UploadedFile::fake()->create('bill.pdf', 10, 'application/pdf')],
    ]);
    $path = $expense->attachments()->sole()->path;

    $team->delete();
    Storage::disk('local')->assertExists($path);

    $team->forceDelete();
    Storage::disk('local')->assertMissing($path);
});

test('bills can wait for approval and are paid after it', function () {
    [$user, $team] = teamOwner();
    $expense = createExpense($this, $user, $team, ['approve_now' => false]);
    $pay = route('expenses.payments.store', ['current_team' => $team->slug, 'expense' => $expense->id]);

    expect($expense->status)->toBe(ExpenseStatus::Pending);

    $this->actingAs($user)->post($pay, ['date' => '2026-09-30', 'amount' => '100', 'method' => 'cash'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)->post(route('expenses.approve', ['current_team' => $team->slug, 'expense' => $expense->id]))
        ->assertRedirect();

    $this->actingAs($user)->post($pay, ['date' => '2026-09-30', 'amount' => '5000', 'method' => 'cheque', 'reference' => 'CHQ 111'])
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->displayStatus()['label'])->toBe('Partially paid');

    $this->actingAs($user)->post($pay, ['date' => '2026-09-30', 'amount' => '6800.01', 'method' => 'cheque'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)->post($pay, ['date' => '2026-09-30', 'amount' => '6800', 'method' => 'bank_transfer'])
        ->assertSessionHasNoErrors();

    $expense->refresh();

    expect($expense->isPaid())->toBeTrue()
        ->and($expense->amount_paid)->toBe('11800.00')
        ->and($expense->paid_at)->not->toBeNull()
        ->and($expense->displayStatus()['label'])->toBe('Paid');
});

test('tax withheld from the supplier lowers what is paid', function () {
    [$user, $team] = teamOwner();
    // 100,000 + 18% = 118,000; 10% income tax on 100,000 and 1/5 of the 18,000 sales tax are withheld.
    $expense = createExpense($this, $user, $team, [
        'net_amount' => '100000',
        'income_tax_withheld' => '10000',
        'sales_tax_withheld' => '3600',
    ]);

    expect($expense->payable())->toBe('104400.00');

    $this->actingAs($user)
        ->post(route('expenses.payments.store', ['current_team' => $team->slug, 'expense' => $expense->id]), [
            'date' => '2026-09-30', 'amount' => '104400', 'method' => 'bank_transfer',
        ])
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->isPaid())->toBeTrue();
});

test('the tax withheld cannot be more than the bill', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('expenses.store', ['current_team' => $team->slug]), expensePayload($team, ['income_tax_withheld' => '20000']))
        ->assertSessionHasErrors('income_tax_withheld');
});

test('a bill can be rejected with a reason', function () {
    [$user, $team] = teamOwner();
    $expense = createExpense($this, $user, $team, ['approve_now' => false]);

    $this->actingAs($user)
        ->post(route('expenses.reject', ['current_team' => $team->slug, 'expense' => $expense->id]), ['reason' => 'Duplicate bill'])
        ->assertRedirect();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Rejected)
        ->and($expense->fresh()->rejection_reason)->toBe('Duplicate bill');
});

test('a changed approved expense needs approval again', function () {
    [$user, $team] = teamOwner();
    $expense = createExpense($this, $user, $team);

    $this->actingAs($user)
        ->put(route('expenses.update', ['current_team' => $team->slug, 'expense' => $expense->id]), expensePayload($team, ['net_amount' => '12000']))
        ->assertSessionHasNoErrors();

    $expense->refresh();

    expect($expense->total)->toBe('14160.00')
        ->and($expense->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->approved_by)->toBeNull();
});

test('expenses with payments cannot be changed or deleted', function () {
    [$user, $team] = teamOwner();
    $expense = createExpense($this, $user, $team);
    $this->actingAs($user)->post(route('expenses.payments.store', ['current_team' => $team->slug, 'expense' => $expense->id]), [
        'date' => '2026-09-30', 'amount' => '1000', 'method' => 'cash',
    ]);

    $this->actingAs($user)
        ->put(route('expenses.update', ['current_team' => $team->slug, 'expense' => $expense->id]), expensePayload($team))
        ->assertSessionHasErrors('expense');

    $this->actingAs($user)
        ->delete(route('expenses.destroy', ['current_team' => $team->slug, 'expense' => $expense->id]))
        ->assertSessionHasErrors('expense');

    expect($expense->fresh()->trashed())->toBeFalse();
});

test('the expense list shows what is still to pay', function () {
    [$user, $team] = teamOwner();
    createExpense($this, $user, $team);
    createExpense($this, $user, $team, ['approve_now' => false]);

    $this->actingAs($user)
        ->get(route('expenses.index', ['current_team' => $team->slug, 'status' => 'to_pay']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->has('expenses.data', 1)
            ->where('summary.to_approve_count', 1)
            ->where('summary.to_pay_total', fn ($value) => (float) $value === 11800.0)
            ->where('summary.month_input_tax', fn ($value) => (float) $value === 1800.0));

    $this->actingAs($user)
        ->get(route('expenses.show', ['current_team' => $team->slug, 'expense' => Expense::withoutGlobalScopes()->where('team_id', $team->id)->first()->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('expenses/Show')->where('expense.number', 'EXP-2627-0001'));
});

test('the purchase register lists approved expenses', function () {
    [$user, $team] = teamOwner();
    createExpense($this, $user, $team, ['bill_number' => 'KE-APPROVED']);
    createExpense($this, $user, $team, ['approve_now' => false, 'bill_number' => 'KE-PENDING']);

    $csv = $this->actingAs($user)->get(route('expenses.export', ['current_team' => $team->slug]))->assertOk()->streamedContent();

    expect($csv)->toContain('KE-APPROVED')
        ->toContain('0710122-3')
        ->not->toContain('KE-PENDING');
});

test('expense categories can be managed in the company settings', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)
        ->post(route('settings.expense-categories.store', ['current_team' => $team->slug]), ['name' => 'Generator fuel', 'sort' => 20])
        ->assertRedirect();

    expect(ExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Generator fuel')->exists())->toBeTrue();
});

test('expenses are private to the team and the expenses module', function () {
    [$user, $team] = teamOwner();
    [$otherUser, $otherTeam] = teamOwner();
    $foreign = createExpense($this, $otherUser, $otherTeam);
    $own = createExpense($this, $user, $team);
    $member = teamMember($team, TeamRole::Member, ['expenses' => 'none', 'crm' => 'read']);

    $this->actingAs($user)
        ->get(route('expenses.show', ['current_team' => $team->slug, 'expense' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($member)
        ->get(route('expenses.show', ['current_team' => $team->slug, 'expense' => $own->id]))
        ->assertForbidden();
});

test('the supplier page lists its bills', function () {
    [$user, $team] = teamOwner();
    $supplier = Company::factory()->for($team)->create();
    createExpense($this, $user, $team, ['company_id' => $supplier->id]);

    $this->actingAs($user)
        ->get(route('companies.show', ['current_team' => $team->slug, 'company' => $supplier->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('related.expenses.total', 1)
            ->where('related.expenses.items.0.number', 'EXP-2627-0001'));
});
