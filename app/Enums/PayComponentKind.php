<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * What a pay component does on a payslip: add to the pay, take from it, or cost the employer extra.
 */
enum PayComponentKind: string
{
    use EnumOptions;

    case Earning = 'earning';
    case Deduction = 'deduction';
    case Employer = 'employer';

    /**
     * Get the display label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Earning (allowance)',
            self::Deduction => 'Deduction',
            self::Employer => 'Employer contribution',
        };
    }
}
