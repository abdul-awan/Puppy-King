<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum WorkOrderStatus: string
{
    use EnumOptions;

    case Open = 'open';
    case Completed = 'completed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Completed => 'green',
        };
    }
}
