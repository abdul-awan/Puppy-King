<?php

namespace App\Concerns;

/**
 * Adds an options() helper to backed enums that define a label() method.
 */
trait EnumOptions
{
    /**
     * Get the enum cases as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
