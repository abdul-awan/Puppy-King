<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum EmploymentType: string
{
    use EnumOptions;

    case Permanent = 'permanent';
    case Probation = 'probation';
    case Contract = 'contract';
    case PartTime = 'part_time';
    case DailyWages = 'daily_wages';
    case Intern = 'intern';

    /**
     * Get the display label for the employment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Probation => 'Probation',
            self::Contract => 'Contract',
            self::PartTime => 'Part-time',
            self::DailyWages => 'Daily wages',
            self::Intern => 'Intern',
        };
    }
}
