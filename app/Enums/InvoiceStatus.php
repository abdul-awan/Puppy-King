<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * The state of an invoice. "Overdue" is not stored: it is an open invoice past its due date.
 */
enum InvoiceStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Credited = 'credited';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Paid => 'Paid',
            self::Credited => 'Credited',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'blue',
            self::Paid => 'green',
            self::Credited => 'purple',
        };
    }
}
