<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum EventType: string
{
    use EnumOptions;

    case Meeting = 'meeting';
    case Call = 'call';
    case Appointment = 'appointment';
    case Other = 'other';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Meeting',
            self::Call => 'Call',
            self::Appointment => 'Appointment',
            self::Other => 'Other',
        };
    }

    /**
     * Get the colour used in the calendar.
     */
    public function color(): string
    {
        return match ($this) {
            self::Meeting => 'blue',
            self::Call => 'green',
            self::Appointment => 'purple',
            self::Other => 'gray',
        };
    }
}
