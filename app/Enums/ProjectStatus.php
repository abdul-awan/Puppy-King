<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum ProjectStatus: string
{
    use EnumOptions;

    case Planned = 'planned';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Done = 'done';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Active => 'blue',
            self::OnHold => 'amber',
            self::Done => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * Determine if work is still expected on the project.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::Active, self::OnHold], true);
    }
}
