<?php

namespace App\Actions\Payroll;

use App\Enums\PayAmountType;
use App\Enums\PayComponentKind;
use App\Enums\PayslipLineSource;
use App\Enums\SalaryTaxMode;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\SalaryTaxTable;
use App\Models\Team;
use App\Support\Payroll\PayrollSettings;
use App\Support\Payroll\TaxYear;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Works out a payslip from the employee's salary and saves it.
 *
 * 1. The salary is paid for the days the employee worked in the month: the days employed (after
 *    joining, before leaving) minus the unpaid days, per day of the month or per 1/30 (setting).
 * 2. Allowances follow the same days; deductions and employer contributions are fixed amounts or
 *    a percentage of the basic or gross salary. One-off adjustments (bonus, overtime, fines) and
 *    advance recoveries entered on the payslip are kept as they are.
 * 3. EOBI: the employee's and the employer's share of the contribution, when the employee pays it.
 * 4. Income tax (section 149): the year's tax on the expected taxable salary of the tax year
 *    (what was paid in earlier months + this month + the full monthly salary for the months left),
 *    minus the tax already deducted, spread over the months left. One-off earnings are taxed in
 *    the month they are paid. The tax can also be a fixed amount, none, or set by hand.
 */
class CalculatePayslip
{
    /**
     * The payroll settings per team (one payroll works out many payslips).
     *
     * @var array<int, PayrollSettings>
     */
    private array $settings = [];

    /**
     * Work out the payslip again and save it with its lines.
     */
    public function handle(Payslip $payslip): void
    {
        $payslip->loadMissing(['run', 'employee.payItems.component', 'lines']);

        $run = $payslip->run;
        $employee = $payslip->employee;
        $settings = $this->settings($payslip->team_id);

        [$employedDays, $payableDays, $factor] = $this->days($payslip, $employee, $run, $settings);

        $fullBasic = BigDecimal::of($employee->basic_salary);
        $basic = $settings->round($fullBasic->multipliedBy($factor));

        $earnings = $fullBasic->isZero() ? [] : [$this->line(PayslipLineSource::Salary, PayComponentKind::Earning, 'Basic salary', $basic, $basic)];
        $deductions = [];
        $employer = [];

        $gross = $basic;
        $regularTaxable = $basic;
        $fullMonthTaxable = $fullBasic;

        // Allowances (they follow the days paid, like the basic salary).
        foreach ($employee->payItems as $item) {
            $component = $item->component;

            if ($component->kind !== PayComponentKind::Earning) {
                continue;
            }

            $value = BigDecimal::of($item->value);

            [$amount, $fullAmount] = $component->amount_type === PayAmountType::Fixed
                ? [$settings->round($value->multipliedBy($factor)), $value]
                : [$this->percent($basic, $value, $settings), $this->percent($fullBasic, $value, $settings)];

            $taxable = $component->taxablePart($amount, $basic);
            $gross = $gross->plus($amount);
            $regularTaxable = $regularTaxable->plus($taxable);
            $fullMonthTaxable = $fullMonthTaxable->plus($component->taxablePart($fullAmount, $fullBasic));

            if (! $amount->isZero()) {
                $earnings[] = $this->line(PayslipLineSource::Salary, PayComponentKind::Earning, $component->name, $amount, $taxable, $component->id);
            }
        }

        // One-off earnings and deductions entered on the payslip, and the advance recoveries.
        $oneOffTaxable = BigDecimal::zero();
        $kept = $payslip->lines->reject(fn (PayslipLine $line) => $line->source->isCalculated());

        foreach ($kept as $line) {
            $entry = $this->line($line->source, $line->kind, $line->name, BigDecimal::of($line->amount), BigDecimal::of($line->taxable_amount), $line->pay_component_id, $line->employee_advance_id);

            if ($line->kind === PayComponentKind::Earning) {
                $gross = $gross->plus(BigDecimal::of($line->amount));
                $oneOffTaxable = $oneOffTaxable->plus(BigDecimal::of($line->taxable_amount));
                $earnings[] = $entry;
            } elseif ($line->kind === PayComponentKind::Deduction) {
                $deductions[] = $entry;
            } else {
                $employer[] = $entry;
            }
        }

        // Deductions and employer contributions of the salary (fixed amounts are not reduced for days not paid).
        $salaryDeductions = [];
        $salaryEmployer = [];

        foreach ($employee->payItems as $item) {
            $component = $item->component;

            if ($component->kind === PayComponentKind::Earning) {
                continue;
            }

            $value = BigDecimal::of($item->value);
            $amount = match ($component->amount_type) {
                PayAmountType::Fixed => $settings->round($value),
                PayAmountType::PercentBasic => $this->percent($basic, $value, $settings),
                PayAmountType::PercentGross => $this->percent($gross, $value, $settings),
            };

            if ($amount->isZero()) {
                continue;
            }

            if ($component->kind === PayComponentKind::Deduction) {
                $salaryDeductions[] = $this->line(PayslipLineSource::Salary, PayComponentKind::Deduction, $component->name, $amount, BigDecimal::zero(), $component->id);
            } else {
                $salaryEmployer[] = $this->line(PayslipLineSource::Salary, PayComponentKind::Employer, $component->name, $amount, BigDecimal::zero(), $component->id);
            }
        }

        $deductions = [...$salaryDeductions, ...$deductions];
        $employer = [...$salaryEmployer, ...$employer];

        if ($employee->eobi && $employedDays > 0) {
            $deductions[] = $this->line(PayslipLineSource::Eobi, PayComponentKind::Deduction, 'EOBI (employee share)', $settings->eobiEmployeeShare(), BigDecimal::zero());
            $employer[] = $this->line(PayslipLineSource::Eobi, PayComponentKind::Employer, 'EOBI (employer share)', $settings->eobiEmployerShare(), BigDecimal::zero());
        }

        [$tax, $taxDetails] = $this->incomeTax($payslip, $employee, $run, $regularTaxable, $oneOffTaxable, $fullMonthTaxable, $settings);

        if ($tax->isPositive()) {
            $deductions[] = $this->line(PayslipLineSource::Tax, PayComponentKind::Deduction, 'Income tax', $tax, BigDecimal::zero());
        }

        $sum = fn (array $lines): BigDecimal => array_reduce($lines, fn (BigDecimal $total, array $line) => $total->plus($line['amount']), BigDecimal::zero());
        $totalDeductions = $sum($deductions);

        $payslip->forceFill([
            'employee_name' => $employee->name,
            'employee_code' => $employee->code,
            'designation' => $employee->designation,
            'department' => $employee->department,
            'cnic' => $employee->cnic,
            'payment_method' => $employee->payment_method,
            'bank_name' => $employee->bank_name,
            'bank_account_title' => $employee->bank_account_title,
            'bank_account_number' => $employee->bank_account_number,
            'days_in_period' => $run->periodStart()->daysInMonth,
            'employed_days' => $employedDays,
            'payable_days' => (string) $payableDays,
            'basic_salary' => (string) $basic,
            'gross' => (string) $gross->toScale(2),
            'taxable_income' => (string) $regularTaxable->plus($oneOffTaxable)->toScale(2),
            'income_tax' => (string) $tax,
            'tax_details' => $taxDetails,
            'deductions' => (string) $totalDeductions->toScale(2),
            'employer_contributions' => (string) $sum($employer)->toScale(2),
            'net' => (string) $gross->minus($totalDeductions)->toScale(2),
        ]);

        DB::transaction(function () use ($payslip, $earnings, $deductions, $employer) {
            $payslip->save();
            $payslip->lines()->delete();

            foreach ([...$earnings, ...$deductions, ...$employer] as $sort => $line) {
                $model = new PayslipLine([
                    ...$line,
                    'amount' => (string) $line['amount']->toScale(2),
                    'taxable_amount' => (string) $line['taxable_amount']->toScale(2),
                    'sort' => $sort,
                ]);
                $model->team_id = $payslip->team_id;
                $payslip->lines()->save($model);
            }
        });

        $payslip->unsetRelation('lines');
    }

    /**
     * Work out the days employed and paid in the month, and the share of the monthly salary earned.
     *
     * @return array{0: int, 1: BigDecimal, 2: BigDecimal}
     */
    private function days(Payslip $payslip, Employee $employee, PayrollRun $run, PayrollSettings $settings): array
    {
        $start = $run->periodStart();
        $end = $run->periodEnd();
        $daysInMonth = $start->daysInMonth;

        $from = $employee->joining_date->greaterThan($start) ? $employee->joining_date : $start;
        $to = $employee->leaving_date !== null && $employee->leaving_date->lessThan($end) ? $employee->leaving_date : $end;
        $employedDays = $from->lessThanOrEqualTo($to) ? (int) round($from->diffInDays($to)) + 1 : 0;

        [$basis, $employedOnBasis] = $settings->daysBasis() === 'thirty'
            ? [30, $employedDays >= $daysInMonth ? 30 : min(30, $employedDays)]
            : [$daysInMonth, $employedDays];

        $payable = BigDecimal::of($employedOnBasis)->minus(BigDecimal::of($payslip->unpaid_days));

        if ($payable->isNegative()) {
            $payable = BigDecimal::zero();
        }

        $factor = $payable->dividedBy($basis, 10, RoundingMode::HalfUp);

        if ($factor->isGreaterThan(1)) {
            $factor = BigDecimal::one();
        }

        return [$employedDays, $payable->toScale(2, RoundingMode::HalfUp), $factor];
    }

    /**
     * Work out the income tax of the month.
     *
     * @return array{0: BigDecimal, 1: array<string, mixed>}
     */
    private function incomeTax(Payslip $payslip, Employee $employee, PayrollRun $run, BigDecimal $regularTaxable, BigDecimal $oneOffTaxable, BigDecimal $fullMonthTaxable, PayrollSettings $settings): array
    {
        $taxYear = TaxYear::for($run->period);
        $details = ['tax_year' => $taxYear];

        if ($payslip->tax_override !== null) {
            return [$settings->round(BigDecimal::of($payslip->tax_override)), [...$details, 'method' => 'manual']];
        }

        if ($employee->tax_mode === SalaryTaxMode::None) {
            return [BigDecimal::zero()->toScale(2), [...$details, 'method' => 'none']];
        }

        if ($employee->tax_mode === SalaryTaxMode::Fixed) {
            return [$settings->round(BigDecimal::of($employee->fixed_tax ?? '0')), [...$details, 'method' => 'fixed']];
        }

        $table = SalaryTaxTable::forTaxYear($payslip->team_id, $taxYear);

        if ($table === null) {
            return [BigDecimal::zero()->toScale(2), [...$details, 'method' => 'auto', 'table_year' => null]];
        }

        // What was paid and deducted in the earlier months of the tax year.
        $prior = Payslip::query()
            ->withoutGlobalScopes()
            ->where('team_id', $payslip->team_id)
            ->where('employee_id', $employee->id)
            ->whereKeyNot($payslip->id)
            ->whereDate('period_start', '>=', TaxYear::start($taxYear))
            ->whereDate('period_start', '<', $run->periodStart())
            ->get(['taxable_income', 'income_tax']);

        $priorTaxable = $prior->reduce(fn (BigDecimal $sum, Payslip $earlier) => $sum->plus(BigDecimal::of($earlier->taxable_income)), BigDecimal::zero());
        $priorTax = $prior->reduce(fn (BigDecimal $sum, Payslip $earlier) => $sum->plus(BigDecimal::of($earlier->income_tax)), BigDecimal::zero());

        // The salary paid and the tax deducted in this tax year before the payroll was used.
        $openingTaxable = BigDecimal::zero();
        $openingTax = BigDecimal::zero();

        if ($employee->opening_tax_year === $taxYear) {
            $openingTaxable = BigDecimal::of($employee->opening_taxable ?? '0');
            $openingTax = BigDecimal::of($employee->opening_tax ?? '0');
            $priorTaxable = $priorTaxable->plus($openingTaxable);
            $priorTax = $priorTax->plus($openingTax);
        }

        // The months after this one until the end of the tax year (or the month the employee leaves).
        $lastMonth = TaxYear::end($taxYear)->startOfMonth();

        if ($employee->leaving_date !== null && $employee->leaving_date->lessThan($lastMonth)) {
            $lastMonth = $employee->leaving_date->startOfMonth();
        }

        $futureMonths = max(0, (int) round($run->periodStart()->diffInMonths($lastMonth, false)));

        $expected = $priorTaxable->plus($regularTaxable)->plus($fullMonthTaxable->multipliedBy($futureMonths));
        $yearTax = $table->annualTax($expected);
        $yearTaxWithOneOffs = $table->annualTax($expected->plus($oneOffTaxable));

        $monthsLeft = $futureMonths + 1;
        $regularPart = $yearTax->minus($priorTax);
        $regularPart = $regularPart->isNegative() ? BigDecimal::zero() : $regularPart->dividedBy($monthsLeft, 2, RoundingMode::HalfUp);

        $tax = $settings->round($regularPart->plus($yearTaxWithOneOffs->minus($yearTax)));

        return [$tax->isNegative() ? BigDecimal::zero()->toScale(2) : $tax, [
            ...$details,
            'method' => 'auto',
            'table_year' => $table->tax_year,
            'earlier_taxable' => (string) $priorTaxable->toScale(2),
            'earlier_tax' => (string) $priorTax->toScale(2),
            'opening_taxable' => (string) $openingTaxable->toScale(2),
            'opening_tax' => (string) $openingTax->toScale(2),
            'month_taxable' => (string) $regularTaxable->plus($oneOffTaxable)->toScale(2),
            'one_off_taxable' => (string) $oneOffTaxable->toScale(2),
            'monthly_taxable' => (string) $fullMonthTaxable->toScale(2),
            'months_left' => $monthsLeft,
            'expected_income' => (string) $expected->plus($oneOffTaxable)->toScale(2),
            'year_tax' => (string) $yearTaxWithOneOffs,
        ]];
    }

    /**
     * Work out a percentage of an amount.
     */
    private function percent(BigDecimal $amount, BigDecimal $percent, PayrollSettings $settings): BigDecimal
    {
        return $settings->round($amount->multipliedBy($percent)->dividedBy(100, 4, RoundingMode::HalfUp));
    }

    /**
     * A payslip line before it is saved.
     *
     * @return array{source: PayslipLineSource, kind: PayComponentKind, name: string, amount: BigDecimal, taxable_amount: BigDecimal, pay_component_id: int|null, employee_advance_id: int|null}
     */
    private function line(PayslipLineSource $source, PayComponentKind $kind, string $name, BigDecimal $amount, BigDecimal $taxable, ?int $componentId = null, ?int $advanceId = null): array
    {
        return [
            'source' => $source,
            'kind' => $kind,
            'name' => $name,
            'amount' => $amount,
            'taxable_amount' => $taxable,
            'pay_component_id' => $componentId,
            'employee_advance_id' => $advanceId,
        ];
    }

    /**
     * Get the payroll settings of a team.
     */
    private function settings(int $teamId): PayrollSettings
    {
        return $this->settings[$teamId] ??= PayrollSettings::for(Team::query()->findOrFail($teamId));
    }

    /**
     * Forget the cached settings (after they were changed).
     */
    public function flush(): void
    {
        $this->settings = [];
    }
}
