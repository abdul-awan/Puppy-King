<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * The approval state of an expense. Whether it is paid follows from its payments.
 */
enum ExpenseStatus: string
{
    use EnumOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'To approve',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'blue',
            self::Rejected => 'red',
        };
    }
}
