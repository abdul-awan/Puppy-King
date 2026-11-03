<?php

namespace App\Enums;

use App\Concerns\EnumOptions;
use Carbon\CarbonImmutable;

enum RecurringFrequency: string
{
    use EnumOptions;

    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';

    /**
     * Get the display label for the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Every week',
            self::Monthly => 'Every month',
            self::Quarterly => 'Every 3 months',
            self::HalfYearly => 'Every 6 months',
            self::Yearly => 'Every year',
        };
    }

    /**
     * Describe the period that starts on the given date, e.g. "October 2026" or "Oct 2026 – Dec 2026".
     */
    public function periodLabel(CarbonImmutable $date): string
    {
        $end = $this->occurrence($date, 1)->subDay();

        return match ($this) {
            self::Weekly => $date->format('d M').' – '.$end->format('d M Y'),
            self::Monthly => $date->day === 1 ? $date->format('F Y') : $date->format('d M').' – '.$end->format('d M Y'),
            default => $date->format('M Y').' – '.$end->format('M Y'),
        };
    }

    /**
     * Get the date of the given occurrence (0 = the start date).
     *
     * Each date is counted from the start, so a profile starting on 31 January
     * runs on 28/29 February and then on 31 March again (no drift).
     */
    public function occurrence(CarbonImmutable $start, int $index): CarbonImmutable
    {
        return match ($this) {
            self::Weekly => $start->addWeeks($index),
            self::Monthly => $start->addMonthsNoOverflow($index),
            self::Quarterly => $start->addMonthsNoOverflow(3 * $index),
            self::HalfYearly => $start->addMonthsNoOverflow(6 * $index),
            self::Yearly => $start->addYearsNoOverflow($index),
        };
    }
}
