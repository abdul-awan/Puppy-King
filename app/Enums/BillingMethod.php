<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * How a project is charged to the customer.
 */
enum BillingMethod: string
{
    use EnumOptions;

    case TimeAndMaterials = 'time_and_materials';
    case FixedPrice = 'fixed_price';
    case NonBillable = 'non_billable';

    /**
     * Get the display label for the billing method.
     */
    public function label(): string
    {
        return match ($this) {
            self::TimeAndMaterials => 'Time & materials',
            self::FixedPrice => 'Fixed price',
            self::NonBillable => 'Not billable',
        };
    }
}
