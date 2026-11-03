<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\Reports\DailyExpenseReport;
use App\Support\Reports\ExpenseReport;
use App\Support\Reports\PayrollReport;
use App\Support\Reports\PipelineReport;
use App\Support\Reports\ReceivablesReport;
use App\Support\Reports\RevenueReport;
use App\Support\Reports\SalesTaxReport;
use App\Support\Reports\TimeReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports on the team's figures. Besides the reports module, every report needs view access
 * to the module its figures come from (e.g. invoicing for the revenue report).
 */
class ReportController extends Controller
{
    /**
     * The reports: the module of their figures and their default period.
     *
     * @var array<string, array{0: Module, 1: string|null}>
     */
    public const REPORTS = [
        'revenue' => [Module::Invoicing, 'this_fiscal_year'],
        'receivables' => [Module::Invoicing, null],
        'sales-tax' => [Module::Invoicing, 'last_month'],
        'pipeline' => [Module::Deals, 'this_quarter'],
        'time' => [Module::TimeTracking, 'this_month'],
        'expenses' => [Module::Expenses, 'this_month'],
        'daily-expenses' => [Module::DailyExpenses, 'this_month'],
        'payroll' => [Module::Payroll, 'this_fiscal_year'],
    ];

    /**
     * Show the reports the user may open.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('reports/Index', [
            'reports' => array_values(array_filter(
                array_keys(self::REPORTS),
                fn (string $report) => $this->canOpen($request->user(), $report),
            )),
        ]);
    }

    /**
     * Revenue per month, customer and product.
     */
    public function revenue(Request $request): Response
    {
        $this->authorizeReport($request, 'revenue');
        [$filters, $start, $end] = $this->period($request, 'revenue');

        return Inertia::render('reports/Revenue', [
            ...$this->periodProps($filters, $start, $end),
            'totals' => RevenueReport::totals($start, $end),
            'months' => RevenueReport::perMonth($start, $end),
            'customers' => RevenueReport::perCustomer($start, $end),
            'products' => RevenueReport::perProduct($start, $end),
        ]);
    }

    /**
     * What customers still owe, by how long it is overdue.
     */
    public function receivables(Request $request): Response
    {
        $this->authorizeReport($request, 'receivables');

        return Inertia::render('reports/Receivables', [
            'currency' => $this->currency(),
            'buckets' => ReceivablesReport::BUCKETS,
            'report' => ReceivablesReport::aging(),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    /**
     * The figures for the sales tax returns.
     */
    public function salesTax(Request $request): Response
    {
        $this->authorizeReport($request, 'sales-tax');
        [$filters, $start, $end] = $this->period($request, 'sales-tax');

        return Inertia::render('reports/SalesTax', [
            ...$this->periodProps($filters, $start, $end),
            'report' => SalesTaxReport::for($start, $end),
        ]);
    }

    /**
     * The sales pipeline and the deals won and lost.
     */
    public function pipeline(Request $request): Response
    {
        $this->authorizeReport($request, 'pipeline');
        [$filters, $start, $end] = $this->period($request, 'pipeline');
        $report = PipelineReport::for($request->integer('pipeline') ?: null, $start, $end);

        return Inertia::render('reports/Pipeline', [
            ...$this->periodProps([...$filters, 'pipeline' => $report['pipeline_id']], $start, $end),
            'report' => $report,
        ]);
    }

    /**
     * The hours worked (members see only their own hours).
     */
    public function time(Request $request): Response
    {
        $this->authorizeReport($request, 'time');
        [$filters, $start, $end] = $this->period($request, 'time');
        $group = $this->timeGroup($request);
        $manager = $this->isManager($request->user());

        return Inertia::render('reports/Time', [
            ...$this->periodProps([...$filters, 'group' => $group], $start, $end),
            'groups' => array_map(fn (string $value, string $label) => ['value' => $value, 'label' => $label], array_keys(TimeReport::GROUPS), TimeReport::GROUPS),
            'onlyMine' => ! $manager,
            'report' => TimeReport::for($group, $start, $end, $manager ? null : $request->user()->id),
        ]);
    }

    /**
     * The approved bills per category and supplier.
     */
    public function expenses(Request $request): Response
    {
        $this->authorizeReport($request, 'expenses');
        [$filters, $start, $end] = $this->period($request, 'expenses');

        return Inertia::render('reports/Expenses', [
            ...$this->periodProps($filters, $start, $end),
            'report' => ExpenseReport::for($start, $end),
        ]);
    }

    /**
     * The daily expenses per category, way of paying and person.
     */
    public function dailyExpenses(Request $request): Response
    {
        $this->authorizeReport($request, 'daily-expenses');
        [$filters, $start, $end] = $this->period($request, 'daily-expenses');

        return Inertia::render('reports/DailyExpenses', [
            ...$this->periodProps($filters, $start, $end),
            'report' => DailyExpenseReport::for($start, $end),
        ]);
    }

    /**
     * The approved payrolls: salaries, income tax withheld and EOBI per month and employee.
     */
    public function payroll(Request $request): Response
    {
        $this->authorizeReport($request, 'payroll');
        [$filters, $start, $end] = $this->period($request, 'payroll');

        return Inertia::render('reports/Payroll', [
            ...$this->periodProps($filters, $start, $end),
            'report' => PayrollReport::for($start, $end),
        ]);
    }

    /**
     * Download the main table of a report as CSV (for Excel).
     */
    public function export(Request $request, string $report): StreamedResponse
    {
        $this->authorizeReport($request, $report);
        [, $start, $end] = $this->period($request, $report);

        [$headers, $rows] = match ($report) {
            'revenue' => [
                ['Month', 'Invoiced (excl. tax)', 'Credited', 'Net revenue'],
                array_map(fn (array $month) => [$month['month'], $month['invoiced'], $month['credited'], $month['net']], RevenueReport::perMonth($start, $end)),
            ],
            'receivables' => $this->receivablesRows(),
            'sales-tax' => $this->salesTaxRows(SalesTaxReport::for($start, $end)),
            'pipeline' => [
                ['Phase', 'Probability %', 'Deals', 'Value', 'Weighted value'],
                array_map(
                    fn (array $phase) => [$phase['name'], $phase['probability'], $phase['count'], $phase['value'], $phase['weighted']],
                    PipelineReport::for($request->integer('pipeline') ?: null, $start, $end)['phases'],
                ),
            ],
            'time' => [
                [TimeReport::GROUPS[$this->timeGroup($request)], 'Hours', 'Billable hours', 'Value', 'Invoiced', 'Not invoiced'],
                array_map(fn (array $row) => [
                    $row['name'],
                    number_format($row['minutes'] / 60, 2, '.', ''),
                    number_format($row['billable_minutes'] / 60, 2, '.', ''),
                    $row['value'],
                    $row['invoiced'],
                    $row['unbilled'],
                ], TimeReport::for($this->timeGroup($request), $start, $end, $this->isManager($request->user()) ? null : $request->user()->id)['rows']),
            ],
            'daily-expenses' => [
                ['Category', 'Expenses', 'Total'],
                array_map(fn (array $row) => [$row['name'], $row['count'], $row['total']], DailyExpenseReport::for($start, $end)['categories']),
            ],
            'payroll' => [
                ['Code', 'Employee', 'Payslips', 'Gross pay', 'Taxable pay', 'Income tax withheld', 'Total deductions', 'Net pay'],
                array_map(fn (array $row) => [
                    $row['code'], $row['name'], $row['payslips'], $row['gross'], $row['taxable'], $row['income_tax'], $row['deductions'], $row['net'],
                ], PayrollReport::for($start, $end)['employees']),
            ],
            default => [
                ['Category', 'Bills', 'Net', 'Sales tax', 'Total'],
                array_map(fn (array $row) => [$row['name'], $row['count'], $row['net'], $row['tax'], $row['total']], ExpenseReport::for($start, $end, null)['categories']),
            ],
        };

        $name = $report === 'receivables'
            ? "receivables-{$start->toDateString()}"
            : "{$report}-{$start->toDateString()}-to-{$end->toDateString()}";

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The rows of the receivables export.
     *
     * @return array{0: list<string>, 1: list<list<string|int>>}
     */
    protected function receivablesRows(): array
    {
        $report = ReceivablesReport::aging();

        return [
            ['Customer', 'Invoices', ...array_values(ReceivablesReport::BUCKETS), 'Total'],
            array_map(fn (array $row) => [$row['name'], $row['invoices'], ...array_values($row['buckets']), $row['total']], $report['rows']),
        ];
    }

    /**
     * The rows of the sales tax export.
     *
     * @param  array<string, mixed>  $report
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    protected function salesTaxRows(array $report): array
    {
        $rows = [];

        foreach (['output' => 'Output tax (sales)', 'input' => 'Input tax (purchases)'] as $side => $label) {
            /** @var list<array{authority_label: string, name: string, rate: string, net: string, tax: string}> $lines */
            $lines = $report[$side];

            foreach ($lines as $line) {
                $rows[] = [$label, $line['authority_label'], $line['name'], $line['rate'], $line['net'], $line['tax']];
            }

            if ($side === 'output') {
                $rows[] = [$label, SalesTaxReport::authorityLabel('fbr'), 'Further tax', '', '', (string) $report['further_tax']];
            }
        }

        /** @var array{income_tax: string, sales_tax: string} $byCustomers */
        $byCustomers = $report['withheld_by_customers'];
        /** @var array{income_tax: string, sales_tax: string} $fromSuppliers */
        $fromSuppliers = $report['withheld_from_suppliers'];

        $rows[] = ['Withheld by customers', '', 'Income tax', '', '', $byCustomers['income_tax']];
        $rows[] = ['Withheld by customers', '', 'Sales tax', '', '', $byCustomers['sales_tax']];
        $rows[] = ['Withheld from suppliers', '', 'Income tax', '', '', $fromSuppliers['income_tax']];
        $rows[] = ['Withheld from suppliers', '', 'Sales tax', '', '', $fromSuppliers['sales_tax']];

        return [['Type', 'Tax authority', 'Tax', 'Rate %', 'Taxable value', 'Tax amount'], $rows];
    }

    /**
     * The chosen period of a report, or its default period.
     *
     * @return array{0: array{period: string|null, from: string|null, to: string|null}, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    protected function period(Request $request, string $report): array
    {
        $default = self::REPORTS[$report][1] ?? 'this_month';
        $filters = DateRange::filters($request);
        $filters['period'] ??= $default;

        $range = DateRange::resolve($filters);

        if ($range === null) {
            $filters = ['period' => $default, 'from' => null, 'to' => null];
            $range = DateRange::resolve($filters) ?? [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()];
        }

        return [$filters, $range[0], $range[1]];
    }

    /**
     * The props every report with a period shares.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function periodProps(array $filters, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'filters' => $filters,
            'periods' => DateRange::options(),
            'range' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'currency' => $this->currency(),
        ];
    }

    /**
     * How the time report is grouped.
     */
    protected function timeGroup(Request $request): string
    {
        $group = $request->string('group')->toString();

        return array_key_exists($group, TimeReport::GROUPS) ? $group : 'user';
    }

    /**
     * Stop users without access to the figures of the report.
     */
    protected function authorizeReport(Request $request, string $report): void
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);
        abort_unless($this->canOpen($request->user(), $report), 403);
    }

    /**
     * Determine if the user may see the figures the report is made of.
     */
    protected function canOpen(User $user, string $report): bool
    {
        return $user->canAccessModule(app(CurrentTeam::class)->team(), self::REPORTS[$report][0], AccessLevel::Read);
    }

    /**
     * Owners and admins see the hours of everybody.
     */
    protected function isManager(User $user): bool
    {
        return $user->teamRole(app(CurrentTeam::class)->team())?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * The team's currency, which all amounts are converted to.
     */
    protected function currency(): string
    {
        return app(CurrentTeam::class)->team()->teamSettings()->currency();
    }
}
