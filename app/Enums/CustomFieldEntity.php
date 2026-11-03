<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * Record types that support team-defined custom fields.
 */
enum CustomFieldEntity: string
{
    use EnumOptions;

    case Company = 'company';
    case Contact = 'contact';
    case Deal = 'deal';
    case Product = 'product';
    case Project = 'project';
    case Ticket = 'ticket';

    /**
     * Get the display label for the entity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Company => 'Companies',
            self::Contact => 'Contacts',
            self::Deal => 'Deals',
            self::Product => 'Products',
            self::Project => 'Projects',
            self::Ticket => 'Tickets',
        };
    }
}
