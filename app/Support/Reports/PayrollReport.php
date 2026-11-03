<?php

namespace App\Support\Reports;

use App\Enums\PayComponentKind;
use App\Enums\PayrollRunStatus;
use App\Enums\PayslipLineSource;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipLine;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * The approved payrolls of a period: salaries, income tax withheld and EOBI per month and per employee.
 */
final class PayrollReport
{
    /**
     * Get the report for the months of the period.
     *
     * @return array{
     *     months: list<array{id: int, month: string, label: string, status_label: string, status_color: string, employees: int, gross: string, income_tax: string, deductions: string, net: string, employer_cost: string}>,
     *     employees: list<array{id: int, name: string, code: string, payslips: int, gross: string, taxable: string, income_tax: string, deductions: string, net: string}>,
     *     totals: array{payslips: int, gross: string, income_tax: string, deductions: string, net: string, employer_cost: string, eobi_employee: string, eobi_employer: string},
     *     drafts: int
     * }
     */
    public static function for(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $runs = PayrollRun::query()
            ->where('status', '!=', PayrollRunStatus::Draft)
            ->whereDate('period', '>=', $start->startOfMonth())
            ->whereDate('period', '<=', $end)
            ->orderBy('period')
            ->get();

        $months = array_values($runs->map(function (PayrollRun $run) {
            $status = $run->displayStatus();

            return [
                'id' => $run->id,
                'month' => $run->period->format('Y-m'),
                'label' => $run->label(),
                'status_label' => $status['label'],
                'status_color' => $status['color'],
                'employees' => $run->employees_count,
                'gross' => $run->gross,
                'income_tax' => $run->income_tax,
                'deductions' => $run->deductions,
                'net' => $run->net,
                'employer_cost' => $run->employer_cost,
            ];
        })->all());

        $payslips = Payslip::query()->whereIn('payroll_run_id', $runs->modelKeys())->get();
        $employees = [];

        foreach ($payslips as $payslip) {
            $row = $employees[$payslip->employee_id] ?? [
                'id' => $payslip->employee_id,
                'name' => $payslip->employee_name,
                'code' => $payslip->employee_code,
                'payslips' => 0,
                'gross' => BigDecimal::zero(),
                'taxable' => BigDecimal::zero(),
                'income_tax' => BigDecimal::zero(),
                'deductions' => BigDecimal::zero(),
                'net' => BigDecimal::zero(),
            ];

            $row['payslips']++;
            $row['gross'] = $row['gross']->plus(BigDecimal::of($payslip->gross));
            $row['taxable'] = $row['taxable']->plus(BigDecimal::of($payslip->taxable_income));
            $row['income_tax'] = $row['income_tax']->plus(BigDecimal::of($payslip->income_tax));
            $row['deductions'] = $row['deductions']->plus(BigDecimal::of($payslip->deductions));
            $row['net'] = $row['net']->plus(BigDecimal::of($payslip->net));

            $employees[$payslip->employee_id] = $row;
        }

        usort($employees, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        $eobi = PayslipLine::query()
            ->whereIn('payslip_id', $payslips->modelKeys())
            ->where('source', PayslipLineSource::Eobi)
            ->get(['kind', 'amount']);

        $sum = fn (iterable $values): string => (string) collect($values)->reduce(
            fn (BigDecimal $total, mixed $value) => $total->plus(BigDecimal::of((string) $value)),
            BigDecimal::zero(),
        )->toScale(2);

        return [
            'months' => $months,
            'employees' => array_map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'code' => $row['code'],
                'payslips' => $row['payslips'],
                'gross' => (string) $row['gross']->toScale(2),
                'taxable' => (string) $row['taxable']->toScale(2),
                'income_tax' => (string) $row['income_tax']->toScale(2),
                'deductions' => (string) $row['deductions']->toScale(2),
                'net' => (string) $row['net']->toScale(2),
            ], $employees),
            'totals' => [
                'payslips' => $payslips->count(),
                'gross' => $sum($runs->pluck('gross')),
                'income_tax' => $sum($runs->pluck('income_tax')),
                'deductions' => $sum($runs->pluck('deductions')),
                'net' => $sum($runs->pluck('net')),
                'employer_cost' => $sum($runs->pluck('employer_cost')),
                'eobi_employee' => $sum($eobi->filter(fn (PayslipLine $line) => $line->kind === PayComponentKind::Deduction)->pluck('amount')),
                'eobi_employer' => $sum($eobi->filter(fn (PayslipLine $line) => $line->kind === PayComponentKind::Employer)->pluck('amount')),
            ],
            'drafts' => PayrollRun::query()
                ->where('status', PayrollRunStatus::Draft)
                ->whereDate('period', '>=', $start->startOfMonth())
                ->whereDate('period', '<=', $end)
                ->count(),
        ];
    }
}
