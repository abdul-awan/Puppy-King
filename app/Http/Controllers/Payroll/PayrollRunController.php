<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\CalculatePayslip;
use App\Actions\Payroll\CreatePayrollRun;
use App\Actions\Payroll\EmailPayslip;
use App\Enums\DocumentType;
use App\Enums\PayComponentKind;
use App\Enums\PaymentMethod;
use App\Enums\PayrollRunStatus;
use App\Enums\PayslipLineSource;
use App\Enums\SalaryTaxMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollRunResource;
use App\Http\Resources\PayslipResource;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\SalaryTaxTable;
use App\Support\CurrentTeam;
use App\Support\DocumentNumberer;
use App\Support\Documents\PayslipPdf;
use App\Support\Payroll\PayrollSettings;
use App\Support\Payroll\TaxYear;
use App\Support\Reports\Sql;
use App\Support\Timeline;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The monthly payroll: start it, check and change the draft payslips, approve, pay and send them.
 */
class PayrollRunController extends Controller
{
    /**
     * The number of payslips e-mailed per click (shared hosting stops requests that take long).
     */
    private const EMAILS_PER_REQUEST = 20;

    /**
     * Display the payrolls, newest month first.
     */
    public function index(): Response
    {
        $team = app(CurrentTeam::class)->team();
        $taxYear = TaxYear::for(CarbonImmutable::today());
        $latest = PayrollRun::query()->latest('period')->first();

        $year = PayrollRun::query()
            ->where('status', '!=', PayrollRunStatus::Draft)
            ->whereDate('period', '>=', TaxYear::start($taxYear))
            ->whereDate('period', '<=', TaxYear::end($taxYear))
            ->toBase()
            ->selectRaw('SUM(gross) as gross, SUM(income_tax) as tax, SUM(net) as net, SUM(employer_cost) as employer')
            ->first();

        $table = SalaryTaxTable::forTaxYear($team->id, $taxYear);

        return Inertia::render('payroll/runs/Index', [
            'runs' => PayrollRunResource::collection(PayrollRun::query()->latest('period')->paginate(12)),
            'summary' => [
                'employees' => Employee::query()->current()->count(),
                'tax_year' => $taxYear,
                'tax_year_label' => TaxYear::label($taxYear),
                'gross' => Sql::money($year->gross ?? 0),
                'income_tax' => Sql::money($year->tax ?? 0),
                'net' => Sql::money($year->net ?? 0),
                'employer' => Sql::money($year->employer ?? 0),
            ],
            'nextMonth' => ($latest !== null ? $latest->period->addMonthNoOverflow() : CarbonImmutable::today())->format('Y-m'),
            'existingMonths' => PayrollRun::query()->pluck('period')->map(fn (mixed $period) => CarbonImmutable::parse((string) $period)->format('Y-m'))->values(),
            'payDay' => PayrollSettings::for($team)->payDay(),
            'taxTable' => ['tax_year' => $taxYear, 'table_year' => $table?->tax_year],
        ]);
    }

    /**
     * Start the payroll of a month.
     */
    public function store(Request $request, CreatePayrollRun $creator): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'pay_date' => ['nullable', 'date'],
        ]);

        $team = app(CurrentTeam::class)->team();
        $month = CarbonImmutable::createFromFormat('!Y-m', (string) $validated['month']);

        if ($month === null || $month->year < 2000 || $month->year > 2100) {
            throw ValidationException::withMessages(['month' => 'Choose a month.']);
        }

        if (PayrollRun::query()->whereDate('period', $month->startOfMonth())->exists()) {
            throw ValidationException::withMessages(['month' => 'There is already a payroll for '.$month->format('F Y').'.']);
        }

        if (! Employee::query()->employedDuring($month->startOfMonth(), $month->endOfMonth())->exists()) {
            throw ValidationException::withMessages(['month' => 'Nobody works for the company in '.$month->format('F Y').'. Add the employees first.']);
        }

        $run = $creator->handle(
            $team,
            $month,
            filled($validated['pay_date'] ?? null) ? CarbonImmutable::parse((string) $validated['pay_date']) : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll for :month started with :count payslips. Check them and approve the payroll.', ['month' => $run->label(), 'count' => $run->employees_count])]);

        return to_route('payroll.show', $run);
    }

    /**
     * Display the payroll with its payslips.
     */
    public function show(PayrollRun $payrollRun): Response
    {
        $payrollRun->load(['payslips.lines', 'payslips.employee', 'approver:id,name', 'creator:id,name']);

        $team = app(CurrentTeam::class)->team();
        $draft = $payrollRun->isDraft();
        $table = SalaryTaxTable::forTaxYear($team->id, $payrollRun->taxYear());

        return Inertia::render('payroll/runs/Show', [
            'run' => PayrollRunResource::make($payrollRun),
            'payslips' => PayslipResource::collection($payrollRun->payslips),
            'advances' => (object) ($draft ? $this->openAdvances($payrollRun) : []),
            'missingEmployees' => $draft
                ? Employee::query()
                    ->employedDuring($payrollRun->periodStart(), $payrollRun->periodEnd())
                    ->whereNotIn('id', $payrollRun->payslips->pluck('employee_id'))
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->name.' ('.$employee->code.')'])
                    ->values()
                : [],
            'earlierDrafts' => $draft ? $this->earlierDrafts($payrollRun) : [],
            'missingHistory' => $draft ? $this->missingHistory($payrollRun) : [],
            'taxTable' => ['tax_year' => $payrollRun->taxYear(), 'table_year' => $table?->tax_year],
            'componentNames' => [
                'earning' => $this->componentNames(PayComponentKind::Earning),
                'deduction' => $this->componentNames(PayComponentKind::Deduction),
            ],
            'timeline' => Timeline::for($payrollRun),
        ]);
    }

    /**
     * Change the pay date or the notes.
     */
    public function update(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        $validated = $request->validate([
            'pay_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payrollRun->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll saved.')]);

        return back();
    }

    /**
     * Delete a draft payroll with its payslips.
     */
    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        $this->ensureDraft($payrollRun);

        $label = $payrollRun->label();
        $payrollRun->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll for :month deleted.', ['month' => $label])]);

        return to_route('payroll.index');
    }

    /**
     * Work out all draft payslips again (after changing salaries, advances or tax rates).
     */
    public function recalculate(PayrollRun $payrollRun, CalculatePayslip $calculator): RedirectResponse
    {
        $this->ensureDraft($payrollRun);

        DB::transaction(function () use ($payrollRun, $calculator) {
            foreach ($payrollRun->payslips()->get() as $payslip) {
                $payslip->setRelation('run', $payrollRun);
                $calculator->handle($payslip);
            }

            $payrollRun->refreshTotals();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payslips worked out again with the current salaries.')]);

        return back();
    }

    /**
     * Put an employee who is missing on the draft payroll.
     */
    public function addEmployee(Request $request, PayrollRun $payrollRun, CreatePayrollRun $creator): RedirectResponse
    {
        $this->ensureDraft($payrollRun);

        $validated = $request->validate(['employee_id' => ['required', 'integer']]);

        $employee = Employee::query()
            ->employedDuring($payrollRun->periodStart(), $payrollRun->periodEnd())
            ->whereNotIn('id', $payrollRun->payslips()->pluck('employee_id'))
            ->find((int) $validated['employee_id']);

        if ($employee === null) {
            throw ValidationException::withMessages(['employee_id' => 'This employee cannot be added to the payroll of '.$payrollRun->label().'.']);
        }

        DB::transaction(function () use ($payrollRun, $employee, $creator) {
            $creator->addEmployee($payrollRun, $employee);
            $payrollRun->refreshTotals();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name added to the payroll.', ['name' => $employee->name])]);

        return back();
    }

    /**
     * Approve the payroll: the payslips are locked and numbered.
     */
    public function approve(Request $request, PayrollRun $payrollRun, DocumentNumberer $numberer): RedirectResponse
    {
        $this->ensureDraft($payrollRun);

        $payslips = $payrollRun->payslips()->get();

        if ($payslips->isEmpty()) {
            throw ValidationException::withMessages(['run' => 'There are no payslips to approve.']);
        }

        if ($earlier = $this->earlierDrafts($payrollRun)) {
            throw ValidationException::withMessages(['run' => 'Approve the payroll of '.implode(', ', $earlier).' first: the income tax builds on the earlier months of the tax year.']);
        }

        $negative = $payslips->first(fn (Payslip $payslip) => BigDecimal::of($payslip->net)->isNegative());

        if ($negative !== null) {
            throw ValidationException::withMessages(['run' => "The net pay of {$negative->employee_name} is below zero. Lower the deductions first."]);
        }

        $team = app(CurrentTeam::class)->team();

        DB::transaction(function () use ($payrollRun, $payslips, $numberer, $team, $request) {
            foreach ($payslips as $payslip) {
                if ($payslip->number === null) {
                    $payslip->forceFill(['number' => $numberer->next($team, DocumentType::Payslip, $payrollRun->periodEnd())])->save();
                }
            }

            $payrollRun->forceFill([
                'status' => PayrollRunStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();

            $payrollRun->logActivity('approved', 'Approved the payroll ('.$payslips->count().' payslips)');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll for :month approved.', ['month' => $payrollRun->label()])]);

        return back();
    }

    /**
     * Make the approved payroll a draft again (only while nothing was paid).
     */
    public function reopen(PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->status !== PayrollRunStatus::Approved || $payrollRun->payslips()->whereNotNull('paid_on')->exists()) {
            throw ValidationException::withMessages(['run' => 'Only an approved payroll without payments can be changed again. Undo the payments first.']);
        }

        $payrollRun->forceFill(['status' => PayrollRunStatus::Draft, 'approved_by' => null, 'approved_at' => null])->save();
        $payrollRun->logActivity('reopened', 'Made the payroll a draft again');

        Inertia::flash('toast', ['type' => 'info', 'message' => __('The payroll is a draft again.')]);

        return back();
    }

    /**
     * Record that every salary of the payroll was paid.
     */
    public function pay(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->isDraft()) {
            throw ValidationException::withMessages(['run' => 'Approve the payroll before paying it.']);
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($payrollRun, $validated) {
            $payrollRun->payslips()->whereNull('paid_on')->update([
                'paid_on' => $validated['date'],
                'payment_reference' => $validated['reference'] ?? null,
                'updated_at' => now(),
            ]);

            $payrollRun->forceFill(['status' => PayrollRunStatus::Paid, 'paid_at' => now()])->save();
            $payrollRun->logActivity('paid', 'Salaries paid on '.CarbonImmutable::parse((string) $validated['date'])->format('d M Y'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salaries for :month marked as paid.', ['month' => $payrollRun->label()])]);

        return back();
    }

    /**
     * Undo the payments of the payroll.
     */
    public function unpay(PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->isDraft()) {
            return back();
        }

        DB::transaction(function () use ($payrollRun) {
            $payrollRun->payslips()->update(['paid_on' => null, 'payment_reference' => null, 'updated_at' => now()]);
            $payrollRun->forceFill(['status' => PayrollRunStatus::Approved, 'paid_at' => null])->save();
            $payrollRun->logActivity('unpaid', 'Undid the payments');
        });

        Inertia::flash('toast', ['type' => 'info', 'message' => __('The payments were undone.')]);

        return back();
    }

    /**
     * E-mail the payslips that were not sent yet to the employees (a batch per click).
     */
    public function email(Request $request, PayrollRun $payrollRun, EmailPayslip $mailer): RedirectResponse
    {
        if ($payrollRun->isDraft()) {
            throw ValidationException::withMessages(['run' => 'Approve the payroll before sending the payslips.']);
        }

        $pending = $payrollRun->payslips()
            ->with('employee')
            ->whereNull('emailed_at')
            ->whereHas('employee', fn ($query) => $query->whereNotNull('email')->where('email', '!=', ''))
            ->get();

        if ($pending->isEmpty()) {
            throw ValidationException::withMessages(['run' => 'All payslips were sent already, or the employees have no e-mail address.']);
        }

        $sent = 0;
        $failed = [];

        foreach ($pending->take(self::EMAILS_PER_REQUEST) as $payslip) {
            try {
                $result = $mailer->handle($payslip, $request->user());
            } catch (Throwable $e) {
                report($e);
                $failed[] = $payslip->employee_name;

                continue;
            }

            if ($result->status === 'sent') {
                $sent++;
            } else {
                $failed[] = $payslip->employee_name;
            }
        }

        $left = max(0, $pending->count() - self::EMAILS_PER_REQUEST);

        if ($failed !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':sent sent; could not send to :names.', ['sent' => $sent, 'names' => implode(', ', $failed)])]);
        } elseif ($left > 0) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __(':sent payslips sent. Click again to send the other :left.', ['sent' => $sent, 'left' => $left])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Payslip sent.|[2,*] :count payslips sent.', $sent)]);
        }

        if ($sent > 0) {
            $payrollRun->logActivity('emailed', "E-mailed {$sent} payslips");
        }

        return back();
    }

    /**
     * Show or download all payslips of the payroll as one PDF.
     */
    public function pdf(Request $request, PayrollRun $payrollRun, PayslipPdf $pdf): HttpResponse
    {
        $payslips = $payrollRun->payslips()->with(['lines', 'employee'])->get();

        if ($payslips->isEmpty()) {
            abort(404);
        }

        $payslips->each(fn (Payslip $payslip) => $payslip->setRelation('run', $payrollRun));
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($payslips), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->runFilename($payrollRun).'"',
        ]);
    }

    /**
     * Download the payroll register (every payslip with its amounts) as CSV.
     */
    public function register(PayrollRun $payrollRun): StreamedResponse
    {
        $payslips = $payrollRun->payslips()->with('lines')->get();

        return $this->csv('payroll-register-'.$payrollRun->period->format('Y-m').'.csv', [
            'Payslip no.', 'Code', 'Employee', 'CNIC', 'Designation', 'Department', 'Days paid', 'Unpaid days', 'Basic salary',
            'Allowances & other earnings', 'Gross pay', 'Income tax', 'EOBI (employee)', 'Advance recovery', 'Other deductions',
            'Total deductions', 'Net pay', 'Employer contributions', 'Paid by', 'Bank', 'Account / IBAN', 'Paid on',
        ], $payslips->map(function (Payslip $payslip) {
            $sum = fn (callable $filter) => (string) $payslip->lines->filter($filter)->reduce(
                fn (BigDecimal $total, PayslipLine $line) => $total->plus(BigDecimal::of($line->amount)),
                BigDecimal::zero(),
            )->toScale(2);

            $tax = $sum(fn (PayslipLine $line) => $line->source === PayslipLineSource::Tax);
            $eobi = $sum(fn (PayslipLine $line) => $line->source === PayslipLineSource::Eobi && $line->kind === PayComponentKind::Deduction);
            $advances = $sum(fn (PayslipLine $line) => $line->source === PayslipLineSource::Advance);

            return [
                $payslip->number,
                $payslip->employee_code,
                $payslip->employee_name,
                $payslip->cnic,
                $payslip->designation,
                $payslip->department,
                $payslip->payable_days,
                $payslip->unpaid_days,
                $payslip->basic_salary,
                (string) BigDecimal::of($payslip->gross)->minus(BigDecimal::of($payslip->basic_salary))->toScale(2),
                $payslip->gross,
                $tax,
                $eobi,
                $advances,
                (string) BigDecimal::of($payslip->deductions)->minus($tax)->minus($eobi)->minus($advances)->toScale(2),
                $payslip->deductions,
                $payslip->net,
                $payslip->employer_contributions,
                $payslip->payment_method->label(),
                $payslip->bank_name,
                $payslip->bank_account_number,
                $payslip->paid_on?->toDateString(),
            ];
        })->all());
    }

    /**
     * Download the salaries to pay by bank transfer as CSV (to upload to the bank or to check the transfers).
     */
    public function bankTransfers(PayrollRun $payrollRun): StreamedResponse
    {
        $payslips = $payrollRun->payslips()->where('payment_method', PaymentMethod::BankTransfer)->get();

        return $this->csv('salary-transfers-'.$payrollRun->period->format('Y-m').'.csv', [
            'Employee code', 'Employee', 'Bank', 'Account title', 'Account number / IBAN', 'Amount', 'Reference',
        ], $payslips->map(fn (Payslip $payslip) => [
            $payslip->employee_code,
            $payslip->employee_name,
            $payslip->bank_name,
            $payslip->bank_account_title ?: $payslip->employee_name,
            $payslip->bank_account_number,
            $payslip->net,
            'Salary '.$payrollRun->period->format('M Y'),
        ])->all());
    }

    /**
     * Stop changes to a payroll that is no longer a draft.
     */
    protected function ensureDraft(PayrollRun $run): void
    {
        if (! $run->isDraft()) {
            throw ValidationException::withMessages(['run' => 'The payroll was approved, so its payslips are locked. Make it a draft again to change it.']);
        }
    }

    /**
     * The months of the same tax year before this one whose payroll is still a draft.
     *
     * @return list<string>
     */
    protected function earlierDrafts(PayrollRun $run): array
    {
        return array_values(PayrollRun::query()
            ->where('status', PayrollRunStatus::Draft)
            ->whereDate('period', '>=', TaxYear::start($run->taxYear()))
            ->whereDate('period', '<', $run->periodStart())
            ->orderBy('period')
            ->get()
            ->map(fn (PayrollRun $earlier) => $earlier->label())
            ->all());
    }

    /**
     * The employees whose income tax is worked out with the slabs but who have no payslips for the
     * earlier months of the tax year and no salary filled in from before the payroll was used: their
     * tax would be too low (e.g. when the company starts using the payroll during the year).
     *
     * @return list<array{employee_id: int, name: string, since: string}>
     */
    protected function missingHistory(PayrollRun $run): array
    {
        $taxYear = $run->taxYear();
        $yearStart = TaxYear::start($taxYear);
        $lastEarlierMonth = $run->periodStart()->subMonthNoOverflow();
        $missing = [];

        foreach ($run->payslips as $payslip) {
            $employee = $payslip->employee;

            if ($employee->tax_mode !== SalaryTaxMode::Auto || $employee->opening_tax_year === $taxYear) {
                continue;
            }

            $from = $employee->joining_date->greaterThan($yearStart) ? $employee->joining_date->startOfMonth() : $yearStart;

            if ($from->greaterThan($lastEarlierMonth)) {
                continue;
            }

            $expected = (int) round($from->diffInMonths($lastEarlierMonth)) + 1;
            $recorded = Payslip::query()
                ->where('employee_id', $employee->id)
                ->whereDate('period_start', '>=', $from)
                ->whereDate('period_start', '<=', $lastEarlierMonth)
                ->count();

            if ($recorded < $expected) {
                $missing[] = ['employee_id' => $employee->id, 'name' => $employee->name, 'since' => $from->format('F Y')];
            }
        }

        return $missing;
    }

    /**
     * The open advances of the employees on the payroll, with what is left to recover on each payslip.
     *
     * @return array<int, list<array{id: int, label: string, left: string, installment: string}>>
     */
    protected function openAdvances(PayrollRun $run): array
    {
        $payslips = $run->payslips->keyBy('employee_id');
        $advances = EmployeeAdvance::query()
            ->whereIn('employee_id', $payslips->keys())
            ->whereDate('date', '<=', $run->periodEnd())
            ->orderBy('date')
            ->get();

        $open = [];

        foreach ($advances as $advance) {
            $payslip = $payslips->get($advance->employee_id);
            $left = BigDecimal::of($advance->amount)->minus($advance->recovered(includeDrafts: true, exceptPayslipId: $payslip?->id));

            if (! $left->isPositive()) {
                continue;
            }

            $open[$advance->employee_id][] = [
                'id' => $advance->id,
                'label' => CreatePayrollRun::advanceLineName($advance).' · '.number_format((float) $advance->amount, 2),
                'left' => (string) $left,
                'installment' => $advance->installment,
            ];
        }

        return $open;
    }

    /**
     * The names of the earnings or deductions, as suggestions for one-off amounts.
     *
     * @return list<string>
     */
    protected function componentNames(PayComponentKind $kind): array
    {
        return array_values(PayComponent::query()->active()->where('kind', $kind)->orderBy('sort')->pluck('name')->map(fn (mixed $name) => (string) $name)->all());
    }

    /**
     * Stream rows as a CSV download (for Excel).
     *
     * @param  list<string>  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    protected function csv(string $name, array $headers, array $rows): StreamedResponse
    {
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
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
