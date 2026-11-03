<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum TaxKind: string
{
    use EnumOptions;

    case Standard = 'standard';
    case Reduced = 'reduced';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';

    /**
     * Get the display label for the tax kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard rate',
            self::Reduced => 'Reduced rate',
            self::ZeroRated => 'Zero-rated',
            self::Exempt => 'Exempt',
        };
    }
}
