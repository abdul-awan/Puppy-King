<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum ProductType: string
{
    use EnumOptions;

    case Product = 'product';
    case Service = 'service';

    /**
     * Get the display label for the product type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Service => 'Service',
        };
    }
}
