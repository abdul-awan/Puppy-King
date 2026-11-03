<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum CustomFieldType: string
{
    use EnumOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Email = 'email';
    case Url = 'url';
    case Phone = 'phone';

    /**
     * Get the display label for the field type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => 'Single line text',
            self::Textarea => 'Multi-line text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Dropdown',
            self::Checkbox => 'Yes / no',
            self::Email => 'E-mail address',
            self::Url => 'Website',
            self::Phone => 'Phone number',
        };
    }
}
