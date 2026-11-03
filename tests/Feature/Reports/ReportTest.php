<?php

use App\Enums\DealStatus;
use App\Enums\TeamRole;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LostReason;
use App\Models\Product;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-09-30 12:00:00')));

/**
 * Credit two of the ten chairs of an issued invoice (50,000 + 18% sales tax).
 */
function creditTwoChairs($test, User $user, Team $team, Invoice $invoice): CreditNote
{
    $test->actingAs($user)->post(route('invoices.credit', ['current_team' => $team->slug, 'invoice' => $invoice->id]));

    $creditNote = CreditNote::withoutGlobalScopes()->where('invoice_id', $invoice->id)->latest('id')->firstOrFail();

    $test->actingAs($user)
        ->put(route('credit-notes.update', ['current_team' => $team->slug, 'credit_note' => $creditNote->id]), [
            'date' => '2026-09-30',
            'reason' => 'Two chairs returned',
            'issue_now' => true,
            'lines' => [['type' => 'item', 'description' => 'Office chair (returned)', 'quantity' => '2', 'unit_price' => '25000', 'tax_rate_id' => salesTax($team)->id]],
        ])
        ->assertSessionHasNoErrors();

    return $creditNote->fresh();
}

/**
 * Enter an approved bill (10,000 + 18% sales tax unless overridden).
 *
 * @param  array<string, mixed>  $overrides
 */
function reportBill($test, User $user, Team $team, array $overrides = []): void
{
    $test->actingAs($user)
        ->post(route('expenses.store', ['current_team' => $team->slug]), array_merge([
            'title' => 'Electricity September',
            'supplier_name' => 'K-Electric',
            'category_id' => ExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Electricity, gas & water')->value('id'),
            'date' => '2026-09-30',
            'currency' => 'PKR',
            'net_amount' => '10000',
            'tax_rate_id' => salesTax($team)->id,
            'tax_claimable' => true,
            'approve_now' => true,
        ], $overrides))
        ->assertSessionHasNoErrors();
}

test('the revenue report shows issued invoices minus credit notes per month, customer and product', function () {
    [$user, $team] = teamOwner();
    $indus = Company::factory()->for($team)->create(['name' => 'Indus Traders']);
    $other = Company::factory()->for($team)->create(['name' => 'Ravi Textiles']);
    $consulting = Product::factory()->for($team)->create(['name' => 'Consulting package']);

    $september = issuedInvoice($this, $user, $team, $indus);
    issuedInvoice($this, $user, $team, $other, [
        'date' => '2026-08-15',
        'lines' => [['type' => 'item', 'product_id' => $consulting->id, 'description' => 'Consulting package', 'quantity' => '1', 'unit_price' => '100000', 'tax_rate_id' => null]],
    ]);
    createInvoice($this, $user, $team, $other); // a draft does not count
    creditTwoChairs($this, $user, $team, $september);

    $this->actingAs($user)
        ->get(route('reports.revenue', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Revenue')
            ->where('filters.period', 'this_fiscal_year')
            ->where('range.from', '2026-07-01')
            ->where('range.to', '2027-06-30')
            ->has('months', 12)
            ->where('months.1.month', '2026-08')
            ->where('months.1.net', '100000.00')
            ->where('months.2.invoiced', '255000.00')
            ->where('months.2.credited', '50000.00')
            ->where('months.2.net', '205000.00')
            ->where('totals.net', '305000.00')
            ->where('totals.invoices', 2)
            ->where('customers.0.name', 'Indus Traders')
            ->where('customers.0.net', '205000.00')
            ->where('customers.1.net', '100000.00')
            ->where('products.0.name', 'Other lines (no product)')
            ->where('products.0.net', '205000.00')
            ->where('products.1.name', 'Consulting package')
            ->where('products.1.quantity', '1'));

    $this->get(route('reports.revenue', ['current_team' => $team->slug, 'period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('months', 1)
            ->where('totals.net', '205000.00'));
});

test('the receivables report splits what customers owe by how long it is overdue', function () {
    [$user, $team] = teamOwner();
    $indus = Company::factory()->for($team)->create(['name' => 'Indus Traders']);

    issuedInvoice($this, $user, $team, $indus); // due 30 Oct 2026
    issuedInvoice($this, $user, $team, $indus, ['date' => '2026-06-01']); // due 1 Jul 2026, 91 days late

    $this->actingAs($user)
        ->get(route('reports.receivables', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Receivables')
            ->where('report.total', '600000.00')
            ->where('report.overdue', '300000.00')
            ->where('report.invoices', 2)
            ->where('report.totals.not_due', '300000.00')
            ->where('report.totals.days_over_90', '300000.00')
            ->has('report.rows', 1)
            ->where('report.rows.0.name', 'Indus Traders')
            ->where('report.rows.0.invoices', 2));
});

test('the sales tax report shows output and input tax per authority and the tax withheld', function () {
    [$user, $team] = teamOwner();
    $indus = Company::factory()->for($team)->create();
    $invoice = issuedInvoice($this, $user, $team, $indus);
    creditTwoChairs($this, $user, $team, $invoice);

    $this->actingAs($user)
        ->post(route('invoices.payments.store', ['current_team' => $team->slug, 'invoice' => $invoice->id]), [
            'date' => '2026-09-30', 'amount' => '100000', 'income_tax_withheld' => '10000', 'sales_tax_withheld' => '5000', 'method' => 'bank_transfer',
        ])
        ->assertSessionHasNoErrors();

    reportBill($this, $user, $team);
    reportBill($this, $user, $team, ['title' => 'Staff lunch', 'net_amount' => '5000', 'tax_claimable' => false]);
    reportBill($this, $user, $team, ['title' => 'Last year', 'date' => '2026-08-31']); // outside the period

    $this->get(route('reports.sales-tax', ['current_team' => $team->slug, 'period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/SalesTax')
            ->where('report.totals.output_tax', '36000.00')
            ->where('report.totals.input_tax', '1800.00')
            ->where('report.totals.net', '34200.00')
            ->has('report.output', 2)
            ->where('report.output.0.authority', 'fbr')
            ->where('report.output.0.rate', '18')
            ->where('report.output.0.net', '200000.00')
            ->where('report.output.0.tax', '36000.00')
            ->where('report.output.1.name', 'No tax')
            ->where('report.output.1.net', '5000.00')
            ->has('report.input', 1)
            ->where('report.input.0.tax', '1800.00')
            ->where('report.input_not_claimable', '900.00')
            ->where('report.authorities.0.authority', 'fbr')
            ->where('report.authorities.0.net', '34200.00')
            ->where('report.withheld_by_customers.income_tax', '10000.00')
            ->where('report.withheld_by_customers.sales_tax', '5000.00'));

    // By default the report shows last month (for the monthly return).
    $this->get(route('reports.sales-tax', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.period', 'last_month')
            ->where('report.totals.output_tax', '0.00')
            ->where('report.totals.input_tax', '1800.00'));
});

test('the pipeline report shows open deals per phase, won and lost deals and the win rate', function () {
    [$user, $team] = teamOwner();
    $phases = DealPhase::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('sort')->get();
    $reason = LostReason::withoutGlobalScopes()->where('team_id', $team->id)->where('name', 'Price too high')->firstOrFail();

    Deal::factory()->forTeam($team)->create(['value' => 100000]); // New lead, 10%
    Deal::factory()->forTeam($team)->create(['value' => 200000, 'phase_id' => $phases[3]->id, 'probability' => 50]);
    Deal::factory()->forTeam($team)->create(['value' => 300000, 'status' => DealStatus::Won, 'closed_at' => '2026-09-15 10:00:00']);
    Deal::factory()->forTeam($team)->create(['value' => 50000, 'status' => DealStatus::Lost, 'closed_at' => '2026-09-20 10:00:00', 'lost_reason_id' => $reason->id]);
    Deal::factory()->forTeam($team)->create(['value' => 999, 'status' => DealStatus::Won, 'closed_at' => '2026-06-30 10:00:00']); // previous quarter
    Deal::factory()->forTeam($team)->create(['value' => 5000, 'currency' => 'USD']);

    $this->actingAs($user)
        ->get(route('reports.pipeline', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Pipeline')
            ->where('report.open.count', 2)
            ->where('report.open.value', '300000.00')
            ->where('report.open.weighted', '110000.00')
            ->where('report.phases.0.count', 1)
            ->where('report.phases.3.value', '200000.00')
            ->where('report.won.count', 1)
            ->where('report.won.value', '300000.00')
            ->where('report.lost.count', 1)
            ->where('report.win_rate', 50)
            ->where('report.lost_reasons.0.name', 'Price too high')
            ->where('report.other_currency', 1));
});

test('the time report groups the hours; members only see their own hours', function () {
    [$user, $team] = teamOwner();
    $member = teamMember($team);
    $project = Project::factory()->for($team)->create(['title' => 'Website', 'number' => 'PRJ-0001']);

    foreach ([[$user, 90, $project->id], [$member, 60, null]] as [$who, $minutes, $projectId]) {
        $this->actingAs($who)
            ->post(route('time.store', ['current_team' => $team->slug]), [
                'date' => '2026-09-29', 'duration_minutes' => $minutes, 'project_id' => $projectId, 'hourly_rate' => '4000', 'is_billable' => true,
            ])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($user)
        ->get(route('reports.time', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Time')
            ->where('onlyMine', false)
            ->has('report.rows', 2)
            ->where('report.totals.minutes', 150)
            ->where('report.totals.value', '10000.00')
            ->where('report.totals.unbilled', '10000.00'));

    $this->get(route('reports.time', ['current_team' => $team->slug, 'group' => 'project']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.group', 'project')
            ->where('report.rows.0.name', 'PRJ-0001 Website')
            ->where('report.rows.1.name', 'No project'));

    $this->actingAs($member)
        ->get(route('reports.time', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onlyMine', true)
            ->has('report.rows', 1)
            ->where('report.totals.minutes', 60));
});

test('the expense report groups approved bills per category and supplier', function () {
    [$user, $team] = teamOwner();
    $rent = ExpenseCategory::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('sort')->where('name', '!=', 'Electricity, gas & water')->firstOrFail();

    reportBill($this, $user, $team);
    reportBill($this, $user, $team, ['title' => 'Rent', 'supplier_name' => 'Mall Road Estates', 'category_id' => $rent->id, 'net_amount' => '50000', 'tax_rate_id' => null]);
    reportBill($this, $user, $team, ['title' => 'Not approved yet', 'approve_now' => false]);

    $this->actingAs($user)
        ->get(route('reports.expenses', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Expenses')
            ->where('report.totals.count', 2)
            ->where('report.totals.net', '60000.00')
            ->where('report.totals.tax', '1800.00')
            ->where('report.totals.total', '61800.00')
            ->where('report.categories.0.name', $rent->name)
            ->where('report.suppliers.0.name', 'Mall Road Estates')
            ->where('report.suppliers.1.total', '11800.00'));
});

test('reports can be downloaded as CSV', function () {
    [$user, $team] = teamOwner();
    issuedInvoice($this, $user, $team, Company::factory()->for($team)->create(['name' => 'Indus Traders']));

    $revenue = $this->actingAs($user)->get(route('reports.export', ['current_team' => $team->slug, 'report' => 'revenue']));
    $revenue->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($revenue->streamedContent())
        ->toContain('Month,"Invoiced (excl. tax)",Credited,"Net revenue"')
        ->toContain('2026-09,255000.00,0.00,255000.00');

    $receivables = $this->get(route('reports.export', ['current_team' => $team->slug, 'report' => 'receivables']));

    expect($receivables->streamedContent())->toContain('"Indus Traders",1,300000.00,0.00,0.00,0.00,0.00,300000.00');

    foreach (['sales-tax', 'pipeline', 'time', 'expenses'] as $report) {
        $this->get(route('reports.export', ['current_team' => $team->slug, 'report' => $report]))->assertOk();
    }

    $this->get("/{$team->slug}/reports/unknown/export")->assertNotFound();
});

test('reports only show figures of modules the user may see', function () {
    [, $team] = teamOwner();
    $sales = teamMember($team, TeamRole::Member, ['invoicing' => 'none', 'expenses' => 'none']);
    $noReports = teamMember($team, TeamRole::Member, ['reports' => 'none']);

    $this->actingAs($sales)
        ->get(route('reports.index', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('reports', ['pipeline', 'time', 'daily-expenses']));

    $this->get(route('reports.revenue', ['current_team' => $team->slug]))->assertForbidden();
    // Salaries are private: members only see the payroll report when they are given access.
    $this->get(route('reports.payroll', ['current_team' => $team->slug]))->assertForbidden();
    $this->get(route('reports.export', ['current_team' => $team->slug, 'report' => 'sales-tax']))->assertForbidden();

    $this->actingAs($noReports)
        ->get(route('reports.index', ['current_team' => $team->slug]))
        ->assertForbidden();
});

test('the dashboard shows the figures and work of the modules the user may see', function () {
    [$user, $team] = teamOwner();
    $indus = Company::factory()->for($team)->create();

    issuedInvoice($this, $user, $team, $indus);
    $late = issuedInvoice($this, $user, $team, $indus, ['date' => '2026-08-01']); // due 31 Aug

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('currency', 'PKR')
            ->where('sales.this_month', '255000.00')
            ->where('sales.last_month', '255000.00')
            ->where('sales.outstanding', '600000.00')
            ->where('sales.overdue', '300000.00')
            ->where('sales.overdue_count', 1)
            ->where('sales.overdue_invoices.0.id', $late->id)
            ->where('sales.overdue_invoices.0.days_overdue', 30)
            ->has('sales.months', 12)
            ->where('pipeline.open_count', 0)
            ->where('tasks.count', 0)
            ->has('events', 0));

    $member = teamMember($team, TeamRole::Member, ['invoicing' => 'none']);

    $this->actingAs($member)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sales', null)
            ->whereNot('pipeline', null));
});
