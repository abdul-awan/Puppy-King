<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * The state of a month's payroll: payslips can be changed while it is a draft, are locked once
 * approved, and the payroll is paid when every payslip is paid.
 */
enum PayrollRunStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'To pay',
            self::Paid => 'Paid',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'blue',
            self::Paid => 'green',
        };
    }
}
