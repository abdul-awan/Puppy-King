<?php

namespace App\Actions\Payroll;

use App\Enums\PayComponentKind;
use App\Enums\PayslipLineSource;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\Team;
use App\Models\User;
use App\Support\Payroll\PayrollSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts the payroll of a month: a draft payslip for every employee who works for the company in
 * that month, with the installments of their open advances.
 */
class CreatePayrollRun
{
    public function __construct(private CalculatePayslip $calculator)
    {
        //
    }

    /**
     * Create the payroll of the month with its payslips.
     */
    public function handle(Team $team, CarbonImmutable $month, ?CarbonImmutable $payDate, User $by): PayrollRun
    {
        return DB::transaction(function () use ($team, $month, $payDate, $by) {
            $run = new PayrollRun([
                'period' => $month->startOfMonth(),
                'pay_date' => $payDate ?? PayrollSettings::for($team)->payDateFor($month),
                'created_by' => $by->id,
            ]);
            $run->team_id = $team->id;
            $run->save();

            $employees = Employee::query()
                ->withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->employedDuring($run->periodStart(), $run->periodEnd())
                ->orderBy('name')
                ->get();

            foreach ($employees as $employee) {
                $this->addEmployee($run, $employee);
            }

            $run->refreshTotals();

            return $run;
        });
    }

    /**
     * Put an employee on the payroll (as a draft payslip).
     */
    public function addEmployee(PayrollRun $run, Employee $employee): Payslip
    {
        $payslip = new Payslip([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'period_start' => $run->periodStart(),
            'period_end' => $run->periodEnd(),
        ]);
        $payslip->team_id = $run->team_id;
        $payslip->forceFill([
            'employee_name' => $employee->name,
            'employee_code' => $employee->code,
            'payment_method' => $employee->payment_method,
        ])->save();

        $advances = EmployeeAdvance::query()
            ->withoutGlobalScopes()
            ->where('team_id', $run->team_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', '<=', $run->periodEnd())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($advances as $advance) {
            $due = $advance->dueOn($payslip->id);

            if (! $due->isPositive()) {
                continue;
            }

            $line = new PayslipLine([
                'employee_advance_id' => $advance->id,
                'source' => PayslipLineSource::Advance,
                'kind' => PayComponentKind::Deduction,
                'name' => self::advanceLineName($advance),
                'amount' => (string) $due,
                'taxable_amount' => '0.00',
            ]);
            $line->team_id = $run->team_id;
            $payslip->lines()->save($line);
        }

        $payslip->setRelation('run', $run);
        $this->calculator->handle($payslip);

        return $payslip;
    }

    /**
     * The name of an advance recovery on a payslip.
     */
    public static function advanceLineName(EmployeeAdvance $advance): string
    {
        return Str::limit('Advance recovery'.(filled($advance->reason) ? ': '.$advance->reason : ' ('.$advance->date->format('d M Y').')'), 100, '…');
    }
}
