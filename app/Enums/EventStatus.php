<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum EventStatus: string
{
    use EnumOptions;

    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }
}
