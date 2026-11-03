<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum TicketStatus: string
{
    use EnumOptions;

    case New = 'new';
    case Open = 'open';
    case Waiting = 'waiting';
    case Closed = 'closed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Open => 'Open',
            self::Waiting => 'Waiting for customer',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'purple',
            self::Open => 'blue',
            self::Waiting => 'amber',
            self::Closed => 'gray',
        };
    }

    /**
     * Determine if the ticket still needs work.
     */
    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }
}
