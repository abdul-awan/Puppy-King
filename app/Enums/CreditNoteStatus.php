<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum CreditNoteStatus: string
{
    use EnumOptions;

    case Draft = 'draft';
    case Issued = 'issued';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'purple',
        };
    }
}
