<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum AccessLevel: string
{
    use EnumOptions;

    case None = 'none';
    case Read = 'read';
    case Write = 'write';

    /**
     * Get the display label for the access level.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No access',
            self::Read => 'View only',
            self::Write => 'Full access',
        };
    }

    /**
     * Determine if this level grants at least the required level.
     */
    public function allows(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    /**
     * Get the numeric rank of the level.
     */
    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Read => 1,
            self::Write => 2,
        };
    }
}
