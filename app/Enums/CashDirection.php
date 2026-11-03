<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * Whether cash was put into the cash box (petty cash) or taken out of it other than for an expense.
 */
enum CashDirection: string
{
    use EnumOptions;

    case In = 'in';
    case Out = 'out';

    /**
     * Get the display label for the direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::In => 'Cash added',
            self::Out => 'Cash taken out',
        };
    }
}
