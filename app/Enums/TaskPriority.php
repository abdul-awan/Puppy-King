<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum TaskPriority: string
{
    use EnumOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Get the display label for the priority.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * Get the badge colour for the priority.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'blue',
            self::High => 'orange',
            self::Urgent => 'red',
        };
    }

    /**
     * Get the sort weight (urgent first).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Urgent => 0,
            self::High => 1,
            self::Normal => 2,
            self::Low => 3,
        };
    }
}
