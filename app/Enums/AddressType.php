<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum AddressType: string
{
    use EnumOptions;

    case Primary = 'primary';
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Visiting = 'visiting';

    /**
     * Get the display label for the address type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary address',
            self::Billing => 'Billing address',
            self::Shipping => 'Delivery address',
            self::Visiting => 'Visiting address',
        };
    }
}
