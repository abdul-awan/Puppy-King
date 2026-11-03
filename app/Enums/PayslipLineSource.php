<?php

namespace App\Enums;

/**
 * Where a payslip line comes from. Lines from the salary, the income tax and EOBI are worked out
 * again whenever a draft payslip is recalculated; adjustments and advance recoveries are kept.
 */
enum PayslipLineSource: string
{
    case Salary = 'salary';
    case Adjustment = 'adjustment';
    case Advance = 'advance';
    case Tax = 'tax';
    case Eobi = 'eobi';

    /**
     * Determine if the line is worked out by the payroll calculation (and replaced on recalculation).
     */
    public function isCalculated(): bool
    {
        return in_array($this, [self::Salary, self::Tax, self::Eobi], true);
    }
}
