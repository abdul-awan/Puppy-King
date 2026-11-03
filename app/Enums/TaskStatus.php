<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum TaskStatus: string
{
    use EnumOptions;

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::Done => 'Done',
        };
    }

    /**
     * Get the badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'blue',
            self::Done => 'green',
        };
    }
}
