<?php

namespace App\Support\Payroll;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Pakistan's tax year for salaries: 1 July to 30 June, named after the year in which it ends
 * (tax year 2027 runs from 1 July 2026 to 30 June 2027), whatever the company's own fiscal year.
 */
final class TaxYear
{
    /**
     * Get the tax year a date belongs to.
     */
    public static function for(DateTimeInterface $date): int
    {
        $year = (int) $date->format('Y');

        return (int) $date->format('n') >= 7 ? $year + 1 : $year;
    }

    /**
     * Get the first day of the tax year (1 July of the year before).
     */
    public static function start(int $taxYear): CarbonImmutable
    {
        return CarbonImmutable::create($taxYear - 1, 7, 1)->startOfDay();
    }

    /**
     * Get the last day of the tax year (30 June).
     */
    public static function end(int $taxYear): CarbonImmutable
    {
        return CarbonImmutable::create($taxYear, 6, 30)->startOfDay();
    }

    /**
     * Get the label of the tax year, e.g. "Tax year 2027 (July 2026 – June 2027)".
     */
    public static function label(int $taxYear): string
    {
        return sprintf('Tax year %d (July %d – June %d)', $taxYear, $taxYear - 1, $taxYear);
    }
}
