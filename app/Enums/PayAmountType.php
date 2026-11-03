<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * How the amount of a pay component is worked out.
 */
enum PayAmountType: string
{
    use EnumOptions;

    case Fixed = 'fixed';
    case PercentBasic = 'percent_basic';
    case PercentGross = 'percent_gross';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::PercentBasic => '% of basic salary',
            self::PercentGross => '% of gross salary',
        };
    }

    /**
     * Determine if the value is a percentage instead of an amount.
     */
    public function isPercent(): bool
    {
        return $this !== self::Fixed;
    }
}
