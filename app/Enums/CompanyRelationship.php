<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum CompanyRelationship: string
{
    use EnumOptions;

    case Lead = 'lead';
    case Prospect = 'prospect';
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Partner = 'partner';
    case Other = 'other';

    /**
     * Get the display label for the relationship.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Prospect => 'Prospect',
            self::Customer => 'Customer',
            self::Supplier => 'Supplier',
            self::Partner => 'Partner',
            self::Other => 'Other',
        };
    }

    /**
     * Get the badge colour for the relationship.
     */
    public function color(): string
    {
        return match ($this) {
            self::Lead => 'amber',
            self::Prospect => 'blue',
            self::Customer => 'green',
            self::Supplier => 'purple',
            self::Partner => 'teal',
            self::Other => 'gray',
        };
    }
}
