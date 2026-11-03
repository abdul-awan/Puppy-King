<?php

namespace App\Support\Payroll;

use App\Enums\PayAmountType;
use App\Enums\PayComponentKind;
use App\Enums\SalaryTaxMode;
use App\Models\Employee;
use App\Models\SalaryTaxTable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Estimates an employee's pay for a full month with the current salary and tax rates (the payroll
 * itself works out the exact payslip, with the days paid and the tax of the earlier months).
 */
final class SalaryEstimate
{
    /**
     * Estimate a full month's pay.
     *
     * @return array{basic: string, gross: string, income_tax: string, deductions: string, net: string, employer: string, cost: string, yearly_taxable: string, yearly_tax: string, tax_year: int, table_year: int|null}
     */
    public static function for(Employee $employee, PayrollSettings $settings, ?CarbonImmutable $month = null): array
    {
        $employee->loadMissing('payItems.component');

        $taxYear = TaxYear::for($month ?? CarbonImmutable::today());
        $basic = BigDecimal::of($employee->basic_salary);
        $gross = $basic;
        $taxable = $basic;

        foreach ($employee->payItems as $item) {
            if ($item->component->kind !== PayComponentKind::Earning) {
                continue;
            }

            $amount = $item->component->amount_type === PayAmountType::Fixed
                ? BigDecimal::of($item->value)
                : $settings->round($basic->multipliedBy(BigDecimal::of($item->value))->dividedBy(100, 4, RoundingMode::HalfUp));

            $gross = $gross->plus($amount);
            $taxable = $taxable->plus($item->component->taxablePart($amount, $basic));
        }

        $deductions = BigDecimal::zero();
        $employer = BigDecimal::zero();

        foreach ($employee->payItems as $item) {
            $component = $item->component;

            if ($component->kind === PayComponentKind::Earning) {
                continue;
            }

            $value = BigDecimal::of($item->value);
            $amount = match ($component->amount_type) {
                PayAmountType::Fixed => $value,
                PayAmountType::PercentBasic => $settings->round($basic->multipliedBy($value)->dividedBy(100, 4, RoundingMode::HalfUp)),
                PayAmountType::PercentGross => $settings->round($gross->multipliedBy($value)->dividedBy(100, 4, RoundingMode::HalfUp)),
            };

            if ($component->kind === PayComponentKind::Deduction) {
                $deductions = $deductions->plus($amount);
            } else {
                $employer = $employer->plus($amount);
            }
        }

        if ($employee->eobi) {
            $deductions = $deductions->plus($settings->eobiEmployeeShare());
            $employer = $employer->plus($settings->eobiEmployerShare());
        }

        $table = $employee->tax_mode === SalaryTaxMode::Auto ? SalaryTaxTable::forTaxYear($employee->team_id, $taxYear) : null;
        $yearlyTax = $table?->annualTax($taxable->multipliedBy(12)) ?? BigDecimal::zero();

        $tax = match ($employee->tax_mode) {
            SalaryTaxMode::None => BigDecimal::zero(),
            SalaryTaxMode::Fixed => BigDecimal::of($employee->fixed_tax ?? '0'),
            SalaryTaxMode::Auto => $settings->round($yearlyTax->dividedBy(12, 2, RoundingMode::HalfUp)),
        };

        $deductions = $deductions->plus($tax);

        return [
            'basic' => (string) $basic->toScale(2),
            'gross' => (string) $gross->toScale(2),
            'income_tax' => (string) $tax->toScale(2),
            'deductions' => (string) $deductions->toScale(2),
            'net' => (string) $gross->minus($deductions)->toScale(2),
            'employer' => (string) $employer->toScale(2),
            'cost' => (string) $gross->plus($employer)->toScale(2),
            'yearly_taxable' => (string) $taxable->multipliedBy(12)->toScale(2),
            'yearly_tax' => (string) $yearlyTax->toScale(2),
            'tax_year' => $taxYear,
            'table_year' => $table?->tax_year,
        ];
    }
}
