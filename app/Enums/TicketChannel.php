<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

/**
 * How a ticket came in.
 */
enum TicketChannel: string
{
    use EnumOptions;

    case Email = 'email';
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case WebForm = 'web_form';
    case InPerson = 'in_person';
    case Other = 'other';

    /**
     * Get the display label for the channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Phone => 'Phone',
            self::WhatsApp => 'WhatsApp',
            self::WebForm => 'Support form',
            self::InPerson => 'In person',
            self::Other => 'Other',
        };
    }
}
