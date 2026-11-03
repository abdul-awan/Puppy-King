<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * How the income tax on an employee's salary is withheld (section 149 of the Income Tax Ordinance).
 */
enum SalaryTaxMode: string
{
    use EnumOptions;

    case Auto = 'auto';
    case Fixed = 'fixed';
    case None = 'none';

    /**
     * Get the display label for the mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Calculate with the salary tax slabs',
            self::Fixed => 'Fixed amount every month',
            self::None => 'No income tax',
        };
    }
}
