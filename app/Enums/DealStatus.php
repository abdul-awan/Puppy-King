<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum DealStatus: string
{
    use EnumOptions;

    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Won => 'green',
            self::Lost => 'red',
        };
    }
}
