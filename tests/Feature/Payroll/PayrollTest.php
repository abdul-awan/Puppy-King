<?php

use App\Enums\PayrollRunStatus;
use App\Enums\TeamRole;
use App\Mail\DocumentMail;
use App\Models\DailyExpenseCategory;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryTaxTable;
use App\Models\SentEmail;
use App\Models\Team;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

// The examples use July 2026 (tax year 2027); the company has the slabs of tax year 2026 (Finance Act 2025),
// which the payroll uses until the slabs of tax year 2027 are added.
beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-07-20 12:00:00')));

/**
 * One of the team's pay components by name.
 */
function payComponent(Team $team, string $name): PayComponent
{
    return PayComponent::withoutGlobalScopes()->where('team_id', $team->id)->where('name', $name)->firstOrFail();
}

/**
 * A valid employee form submission: basic 100,000 + house rent 45,000 + medical 10,000 (tax-free up to 10% of basic).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(Team $team, array $overrides = []): array
{
    return array_merge([
        'name' => 'Ayesha Khan',
        'father_name' => 'Imran Khan',
        'cnic' => '3520212345671',
        'designation' => 'Accountant',
        'department' => 'Finance',
        'employment_type' => 'permanent',
        'joining_date' => '2025-01-01',
        'basic_salary' => '100000',
        'payment_method' => 'bank_transfer',
        'bank_name' => 'Meezan Bank',
        'bank_account_title' => 'Ayesha Khan',
        'bank_account_number' => 'PK36MEZN0001234567890123',
        'tax_mode' => 'auto',
        'eobi' => false,
        'email' => 'ayesha@example.test',
        'items' => [
            ['pay_component_id' => payComponent($team, 'House rent allowance')->id, 'value' => '45000'],
            ['pay_component_id' => payComponent($team, 'Medical allowance')->id, 'value' => '10000'],
        ],
    ], $overrides);
}

/**
 * Add an employee through the form and return them.
 *
 * @param  array<string, mixed>  $overrides
 */
function createEmployee($test, User $user, Team $team, array $overrides = []): Employee
{
    $test->actingAs($user)
        ->post(route('employees.store', ['current_team' => $team->slug]), employeePayload($team, $overrides))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return Employee::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

/**
 * Start the payroll of a month (Y-m) through the app and return it.
 */
function startPayroll($test, User $user, Team $team, string $month): PayrollRun
{
    $test->actingAs($user)
        ->post(route('payroll.store', ['current_team' => $team->slug]), ['month' => $month])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    return PayrollRun::withoutGlobalScopes()->where('team_id', $team->id)->latest('id')->firstOrFail();
}

/**
 * The payslip of an employee in a payroll.
 */
function payslipOf(PayrollRun $run, Employee $employee): Payslip
{
    return Payslip::withoutGlobalScopes()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->with('lines')->firstOrFail();
}

/**
 * The route parameters of the team plus extra ones.
 *
 * @param  array<string, mixed>  $parameters
 * @return array<string, mixed>
 */
function on(Team $team, array $parameters = []): array
{
    return ['current_team' => $team->slug, ...$parameters];
}

test('new teams get pay components, the salary tax slabs and a payslip e-mail', function () {
    [, $team] = teamOwner();

    expect(PayComponent::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(15)
        ->and(SalaryTaxTable::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('tax_year')->pluck('tax_year')->all())->toBe([2025, 2026])
        ->and($team->emailTemplates()->withoutGlobalScopes()->where('type', 'payslip')->exists())->toBeTrue();
});

test('existing companies get the payroll and daily expense defaults from the set-up command', function () {
    [, $team] = teamOwner();
    PayComponent::withoutGlobalScopes()->where('team_id', $team->id)->delete();
    SalaryTaxTable::withoutGlobalScopes()->where('team_id', $team->id)->delete();
    DailyExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->delete();
    $team->emailTemplates()->withoutGlobalScopes()->where('type', 'payslip')->delete();

    $this->artisan('teams:set-up-defaults', ['team' => $team->id])->assertSuccessful();

    expect(PayComponent::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(15)
        ->and(SalaryTaxTable::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(2)
        ->and(DailyExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->count())->toBe(14)
        ->and($team->emailTemplates()->withoutGlobalScopes()->where('type', 'payslip')->count())->toBe(1)
        // The other templates are not doubled.
        ->and($team->emailTemplates()->withoutGlobalScopes()->where('type', 'invoice')->count())->toBe(1);
});

test('the salary tax slabs of tax year 2026 give the right yearly tax', function (string $income, string $tax) {
    [, $team] = teamOwner();
    $table = SalaryTaxTable::forTaxYear($team->id, 2026);

    expect((string) $table->annualTax(BigDecimal::of($income)))->toBe($tax);
})->with([
    'up to 600,000 is free' => ['600000', '0.00'],
    '1% above 600,000' => ['1200000', '6000.00'],
    '11% above 1,200,000' => ['1800000', '72000.00'],
    '23% above 2,200,000' => ['2400000', '162000.00'],
    '35% above 4,100,000' => ['6000000', '1281000.00'],
    'with the 9% surcharge above 10 million' => ['12000000', '3685290.00'],
]);

test('employees are added with their salary and get a code', function () {
    [$user, $team] = teamOwner();

    $employee = createEmployee($this, $user, $team);

    expect($employee->code)->toBe('EMP-0001')
        ->and($employee->cnic)->toBe('35202-1234567-1')
        ->and($employee->basic_salary)->toBe('100000.00')
        ->and($employee->payItems()->count())->toBe(2);

    $this->actingAs($user)
        ->get(route('employees.show', on($team, ['employee' => $employee->id])))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payroll/employees/Show')
            ->where('estimate.gross', '155000.00')
            // 145,000 taxable a month = 1,740,000 a year: 6,000 + 11% of 540,000 = 65,400 a year.
            ->where('estimate.income_tax', '5450.00')
            ->where('estimate.net', '149550.00'));
});

test('a payroll makes a payslip with the salary, the tax-free medical allowance and the income tax', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team);

    $run = startPayroll($this, $user, $team, '2026-07');
    $payslip = payslipOf($run, $employee);

    expect($run->status)->toBe(PayrollRunStatus::Draft)
        ->and($run->pay_date?->toDateString())->toBe('2026-07-31')
        ->and($payslip->gross)->toBe('155000.00')
        ->and($payslip->taxable_income)->toBe('145000.00')
        // 1,740,000 for the tax year: 65,400 tax, spread over 12 months.
        ->and($payslip->income_tax)->toBe('5450.00')
        ->and($payslip->net)->toBe('149550.00')
        ->and($payslip->tax_details['table_year'])->toBe(2026)
        ->and($payslip->tax_details['tax_year'])->toBe(2027)
        ->and($payslip->lines->pluck('name')->all())->toBe(['Basic salary', 'House rent allowance', 'Medical allowance', 'Income tax'])
        ->and($run->fresh()->net)->toBe('149550.00');
});

test('someone joining halfway through the month is paid for the days worked', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['joining_date' => '2026-07-16', 'items' => []]);

    $payslip = payslipOf(startPayroll($this, $user, $team, '2026-07'), $employee);

    // 16 of 31 days, rounded to whole rupees; the tax counts on full salaries for the 11 months left.
    expect($payslip->employed_days)->toBe(16)
        ->and($payslip->basic_salary)->toBe('51613.00')
        ->and($payslip->income_tax)->toBe('460.00');
});

test('unpaid days are taken off the salary and one-off earnings are taxed in their month', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['basic_salary' => '200000', 'items' => []]);
    $run = startPayroll($this, $user, $team, '2026-07');
    $payslip = payslipOf($run, $employee);

    expect($payslip->income_tax)->toBe('13500.00');

    $this->actingAs($user)
        ->put(route('payslips.update', on($team, ['payslip' => $payslip->id])), [
            'unpaid_days' => 0,
            'adjustments' => [['kind' => 'earning', 'name' => 'Bonus', 'amount' => '100000', 'taxable' => true]],
        ])
        ->assertSessionHasNoErrors();

    $payslip = $payslip->fresh();

    // 13,500 for the month plus 23% of the bonus.
    expect($payslip->gross)->toBe('300000.00')
        ->and($payslip->income_tax)->toBe('36500.00');

    $this->actingAs($user)
        ->put(route('payslips.update', on($team, ['payslip' => $payslip->id])), ['unpaid_days' => 3, 'adjustments' => []])
        ->assertSessionHasNoErrors();

    expect($payslip->fresh()->basic_salary)->toBe('180645.00')
        ->and($payslip->fresh()->payable_days)->toBe('28.00');
});

test('the tax of later months builds on the earlier months of the tax year', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['basic_salary' => '200000', 'items' => []]);

    $july = startPayroll($this, $user, $team, '2026-07');
    $this->actingAs($user)->put(route('payslips.update', on($team, ['payslip' => payslipOf($july, $employee)->id])), [
        'unpaid_days' => 0,
        'adjustments' => [['kind' => 'earning', 'name' => 'Bonus', 'amount' => '100000', 'taxable' => true]],
    ])->assertSessionHasNoErrors();

    $august = startPayroll($this, $user, $team, '2026-08');

    // The approval order follows the months: July first.
    $this->actingAs($user)->post(route('payroll.approve', on($team, ['payroll_run' => $august->id])))->assertSessionHasErrors('run');

    // 2,500,000 expected for the year: 185,000 tax, of which 36,500 was deducted in July (11 months left).
    expect(payslipOf($august, $employee)->income_tax)->toBe('13500.00');
});

test('the salary paid before the payroll was used counts for the tax of the year', function () {
    [$user, $team] = teamOwner();
    $before = createEmployee($this, $user, $team, ['name' => 'Paid Before', 'cnic' => null, 'basic_salary' => '200000', 'items' => [],
        // July and August were paid outside the app: 400,000 taxable, 13,500 tax each month.
        'opening_tax_year' => 2027, 'opening_taxable' => '400000', 'opening_tax' => '27000']);
    $unknown = createEmployee($this, $user, $team, ['name' => 'Not Filled In', 'cnic' => null, 'basic_salary' => '200000', 'items' => []]);

    $september = startPayroll($this, $user, $team, '2026-09');

    // 2,400,000 for the year: 162,000 tax, minus the 27,000 deducted before, over the 10 months left.
    expect(payslipOf($september, $before)->income_tax)->toBe('13500.00')
        ->and(payslipOf($september, $before)->tax_details['opening_taxable'])->toBe('400000.00')
        // Without the earlier months only 10 months are expected: too little tax, so the payroll warns.
        ->and(payslipOf($september, $unknown)->income_tax)->toBe('9400.00');

    $this->actingAs($user)
        ->get(route('payroll.show', on($team, ['payroll_run' => $september->id])))
        ->assertInertia(fn (Assert $page) => $page
            ->has('missingHistory', 1)
            ->where('missingHistory.0.name', 'Not Filled In')
            ->where('missingHistory.0.since', 'July 2026'));
});

test('changing a payslip without sending the advances keeps the recoveries', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['items' => []]);
    $this->actingAs($user)->post(route('employees.advances.store', on($team, ['employee' => $employee->id])), [
        'date' => '2026-07-01', 'amount' => '20000', 'installment' => '5000',
    ])->assertSessionHasNoErrors();
    $payslip = payslipOf(startPayroll($this, $user, $team, '2026-07'), $employee);

    $this->actingAs($user)->put(route('payslips.update', on($team, ['payslip' => $payslip->id])), [
        'unpaid_days' => 1,
        'adjustments' => [['kind' => 'earning', 'name' => 'Overtime', 'amount' => '3000', 'taxable' => true]],
    ])->assertSessionHasNoErrors();

    $lines = payslipOf($payslip->run()->withoutGlobalScopes()->firstOrFail(), $employee)->lines;

    expect($lines->firstWhere('source.value', 'advance')?->amount)->toBe('5000.00')
        ->and($lines->firstWhere('name', 'Overtime')?->amount)->toBe('3000.00');
});

test('the slabs of the tax year are used once they are added', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['basic_salary' => '200000', 'items' => []]);

    $this->actingAs($user)->post(route('settings.salary-tax-tables.store', on($team)), [
        'tax_year' => 2027,
        'name' => 'Example rates',
        'slabs' => [
            ['from' => '1200000', 'fixed' => '0', 'rate' => '10'],
            ['from' => '0', 'fixed' => '0', 'rate' => '0'],
        ],
    ])->assertSessionHasNoErrors();

    $payslip = payslipOf(startPayroll($this, $user, $team, '2026-07'), $employee);

    // 10% of 1,200,000 = 120,000 a year.
    expect($payslip->income_tax)->toBe('10000.00')
        ->and($payslip->tax_details['table_year'])->toBe(2027)
        ->and(SalaryTaxTable::forTaxYear($team->id, 2027)?->slabs[0]['from'])->toBe('0');
});

test('eobi, a fixed tax and advances are deducted', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['eobi' => true, 'tax_mode' => 'fixed', 'fixed_tax' => '2500', 'items' => []]);

    $this->actingAs($user)->post(route('employees.advances.store', on($team, ['employee' => $employee->id])), [
        'date' => '2026-07-05', 'amount' => '25000', 'installment' => '10000', 'reason' => 'Motorbike',
    ])->assertSessionHasNoErrors();

    $july = startPayroll($this, $user, $team, '2026-07');
    $payslip = payslipOf($july, $employee);

    expect($payslip->lines->pluck('amount', 'name')->all())->toBe([
        'Basic salary' => '100000.00',
        'Advance recovery: Motorbike' => '10000.00',
        'EOBI (employee share)' => '370.00',
        'Income tax' => '2500.00',
        'EOBI (employer share)' => '1850.00',
    ])
        ->and($payslip->net)->toBe('87130.00')
        ->and($payslip->employer_contributions)->toBe('1850.00');

    // A recovery cannot be more than what is left of the advance.
    $advance = EmployeeAdvance::withoutGlobalScopes()->where('employee_id', $employee->id)->sole();
    $this->actingAs($user)->put(route('payslips.update', on($team, ['payslip' => $payslip->id])), [
        'unpaid_days' => 0, 'advances' => [['id' => $advance->id, 'amount' => '30000']],
    ])->assertSessionHasErrors('advances.0.amount');

    $this->actingAs($user)->post(route('payroll.approve', on($team, ['payroll_run' => $july->id])))->assertSessionHasNoErrors();

    expect((string) $advance->outstanding())->toBe('15000.00');

    // August recovers the next installment, September only what is left.
    $august = startPayroll($this, $user, $team, '2026-08');
    $this->actingAs($user)->post(route('payroll.approve', on($team, ['payroll_run' => $august->id])))->assertSessionHasNoErrors();
    $september = startPayroll($this, $user, $team, '2026-09');

    expect(payslipOf($september, $employee)->lines->firstWhere('employee_advance_id', $advance->id)?->amount)->toBe('5000.00');
});

test('approving numbers and locks the payslips; paying and reopening follow the rules', function () {
    [$user, $team] = teamOwner();
    $first = createEmployee($this, $user, $team);
    $second = createEmployee($this, $user, $team, ['name' => 'Bilal Ahmed', 'cnic' => null, 'email' => null]);
    $run = startPayroll($this, $user, $team, '2026-07');

    $this->actingAs($user)->post(route('payroll.approve', on($team, ['payroll_run' => $run->id])))->assertSessionHasNoErrors();

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Approved)
        ->and(payslipOf($run, $first)->number)->toBe('PSL-2627-0001')
        ->and(payslipOf($run, $second)->number)->toBe('PSL-2627-0002');

    // Locked.
    $this->actingAs($user)->put(route('payslips.update', on($team, ['payslip' => payslipOf($run, $first)->id])), ['unpaid_days' => 1])
        ->assertSessionHasErrors('run');

    // One salary paid: partly paid, and the payroll can no longer be reopened.
    $this->actingAs($user)->post(route('payslips.pay', on($team, ['payslip' => payslipOf($run, $first)->id])), ['date' => '2026-07-31'])
        ->assertSessionHasNoErrors();
    expect($run->fresh()->displayStatus()['label'])->toBe('Partly paid');
    $this->actingAs($user)->post(route('payroll.reopen', on($team, ['payroll_run' => $run->id])))->assertSessionHasErrors('run');

    $this->actingAs($user)->post(route('payroll.pay', on($team, ['payroll_run' => $run->id])), ['date' => '2026-07-31', 'reference' => 'IBFT batch 77'])
        ->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Paid)
        ->and(payslipOf($run, $second)->payment_reference)->toBe('IBFT batch 77');

    $this->actingAs($user)->delete(route('payroll.unpay', on($team, ['payroll_run' => $run->id])))->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('payroll.reopen', on($team, ['payroll_run' => $run->id])))->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Draft);

    // Payslips keep their numbers; a draft payroll can be deleted.
    $this->actingAs($user)->delete(route('payroll.destroy', on($team, ['payroll_run' => $run->id])))->assertRedirect();
    expect(PayrollRun::withoutGlobalScopes()->whereKey($run->id)->exists())->toBeFalse();
});

test('a month can only have one payroll and needs employees', function () {
    [$user, $team] = teamOwner();

    $this->actingAs($user)->post(route('payroll.store', on($team)), ['month' => '2026-07'])->assertSessionHasErrors('month');

    createEmployee($this, $user, $team);
    startPayroll($this, $user, $team, '2026-07');

    $this->actingAs($user)->post(route('payroll.store', on($team)), ['month' => '2026-07'])->assertSessionHasErrors('month');
});

test('employees who left before the month are not on its payroll', function () {
    [$user, $team] = teamOwner();
    createEmployee($this, $user, $team);
    $left = createEmployee($this, $user, $team, ['name' => 'Left Early', 'cnic' => null, 'leaving_date' => '2026-06-30']);

    $run = startPayroll($this, $user, $team, '2026-07');

    expect($run->employees_count)->toBe(1)
        ->and(Payslip::withoutGlobalScopes()->where('employee_id', $left->id)->exists())->toBeFalse();
});

test('payslips are printed and e-mailed to the employees', function () {
    Mail::fake();
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team);
    createEmployee($this, $user, $team, ['name' => 'No Mail', 'cnic' => null, 'email' => null]);
    $run = startPayroll($this, $user, $team, '2026-07');

    $this->actingAs($user)->get(route('payslips.pdf', on($team, ['payslip' => payslipOf($run, $employee)->id])))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($user)->get(route('payroll.pdf', on($team, ['payroll_run' => $run->id])))->assertOk();

    // Drafts are not sent.
    $this->actingAs($user)->post(route('payroll.email', on($team, ['payroll_run' => $run->id])))->assertSessionHasErrors('run');

    $this->actingAs($user)->post(route('payroll.approve', on($team, ['payroll_run' => $run->id])));
    $this->actingAs($user)->post(route('payroll.email', on($team, ['payroll_run' => $run->id])))->assertSessionHasNoErrors();

    Mail::assertSent(DocumentMail::class, 1);
    expect(payslipOf($run, $employee)->emailed_at)->not->toBeNull()
        ->and(SentEmail::withoutGlobalScopes()->where('emailable_type', 'employee')->where('emailable_id', $employee->id)->sole()->subject)
        ->toBe('Your payslip for July 2026 - '.$team->name);
});

test('the payroll register and the bank transfers are exported', function () {
    [$user, $team] = teamOwner();
    createEmployee($this, $user, $team);
    $run = startPayroll($this, $user, $team, '2026-07');

    $register = $this->actingAs($user)->get(route('payroll.register', on($team, ['payroll_run' => $run->id])));
    $register->assertOk();
    expect($register->streamedContent())->toContain('Ayesha Khan')->toContain('149550.00');

    $bank = $this->actingAs($user)->get(route('payroll.bank-transfers', on($team, ['payroll_run' => $run->id])));
    expect($bank->streamedContent())->toContain('PK36MEZN0001234567890123')->toContain('Salary Jul 2026');
});

test('members only see the payroll when they are allowed to', function () {
    [, $team] = teamOwner();
    $member = teamMember($team);

    $this->actingAs($member)->get(route('employees.index', on($team)))->assertForbidden();
    $this->actingAs($member)->get(route('payroll.index', on($team)))->assertForbidden();

    $viewer = teamMember($team, TeamRole::Member, ['payroll' => 'read']);
    $this->actingAs($viewer)->get(route('payroll.index', on($team)))->assertOk();
    $this->actingAs($viewer)->post(route('employees.store', on($team)), employeePayload($team))->assertForbidden();
});

test('employees with a login see only their own approved payslips', function () {
    [$owner, $team] = teamOwner();
    $staff = teamMember($team);
    $other = teamMember($team);
    $employee = createEmployee($this, $owner, $team, ['user_id' => $staff->id]);
    $run = startPayroll($this, $owner, $team, '2026-07');
    $payslip = payslipOf($run, $employee);

    // A draft is not shown yet.
    $this->actingAs($staff)->get(route('my-payslips.index', on($team)))
        ->assertInertia(fn (Assert $page) => $page->component('payroll/MyPayslips')->has('payslips.data', 0));
    $this->actingAs($staff)->get(route('my-payslips.pdf', on($team, ['payslip' => $payslip->id])))->assertNotFound();

    $this->actingAs($owner)->post(route('payroll.approve', on($team, ['payroll_run' => $run->id])));

    $this->actingAs($staff)->get(route('my-payslips.index', on($team)))
        ->assertInertia(fn (Assert $page) => $page->has('payslips.data', 1)->where('payslips.data.0.net', '149550.00'));
    $this->actingAs($staff)->get(route('my-payslips.pdf', on($team, ['payslip' => $payslip->id])))->assertOk();
    $this->actingAs($other)->get(route('my-payslips.pdf', on($team, ['payslip' => $payslip->id])))->assertNotFound();
});

test('employees with payslips are kept; others can be removed', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team);
    $new = createEmployee($this, $user, $team, ['name' => 'Not Paid Yet', 'cnic' => null, 'joining_date' => '2026-09-01']);
    startPayroll($this, $user, $team, '2026-07');

    $this->actingAs($user)->delete(route('employees.destroy', on($team, ['employee' => $employee->id])))->assertSessionHasErrors('employee');
    $this->actingAs($user)->delete(route('employees.destroy', on($team, ['employee' => $new->id])))->assertRedirect();

    expect(Employee::withoutGlobalScopes()->whereKey($new->id)->first()?->trashed())->toBeTrue();
});

test('pay components on a salary cannot be deleted', function () {
    [$user, $team] = teamOwner();
    createEmployee($this, $user, $team);

    $this->actingAs($user)->delete(route('settings.pay-components.destroy', on($team, ['pay_component' => payComponent($team, 'House rent allowance')->id])))
        ->assertSessionHasErrors('component');
    $this->actingAs($user)->delete(route('settings.pay-components.destroy', on($team, ['pay_component' => payComponent($team, 'Bonus')->id])))
        ->assertSessionHasNoErrors();
});

test('the payroll settings change how payslips are worked out', function () {
    [$user, $team] = teamOwner();
    $employee = createEmployee($this, $user, $team, ['eobi' => true, 'tax_mode' => 'none', 'items' => []]);

    $this->actingAs($user)->put(route('settings.payroll.update', on($team)), [
        'days_basis' => 'thirty',
        'whole_rupees' => true,
        'pay_day' => 5,
        'eobi_default' => true,
        'eobi_wage_base' => '40000',
        'eobi_employer_percent' => '5',
        'eobi_employee_percent' => '1',
        'show_employer_contributions' => true,
        'payslip_note' => 'Salaries are confidential.',
    ])->assertSessionHasNoErrors();

    $run = startPayroll($this, $user, $team, '2026-07');
    $payslip = payslipOf($run, $employee);

    expect($run->pay_date?->toDateString())->toBe('2026-08-05')
        ->and($payslip->lines->pluck('amount', 'name')->all())->toMatchArray(['EOBI (employee share)' => '400.00', 'EOBI (employer share)' => '2000.00']);

    // 30-day months: 2 unpaid days leave 28/30 of the salary.
    $this->actingAs($user)->put(route('payslips.update', on($team, ['payslip' => $payslip->id])), ['unpaid_days' => 2])->assertSessionHasNoErrors();
    expect($payslip->fresh()->basic_salary)->toBe('93333.00');
});

test('the payroll report adds up the approved payrolls', function () {
    [$user, $team] = teamOwner();
    createEmployee($this, $user, $team);
    $run = startPayroll($this, $user, $team, '2026-07');
    $this->actingAs($user)->post(route('payroll.approve', on($team, ['payroll_run' => $run->id])));

    $this->actingAs($user)->get(route('reports.payroll', on($team)))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Payroll')
            ->where('report.totals.gross', '155000.00')
            ->where('report.totals.income_tax', '5450.00')
            ->has('report.employees', 1));

    $csv = $this->actingAs($user)->get(route('reports.export', on($team, ['report' => 'payroll'])));
    expect($csv->streamedContent())->toContain('Ayesha Khan');
});
