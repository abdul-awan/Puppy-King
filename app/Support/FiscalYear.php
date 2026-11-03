<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A fiscal year that starts on the first day of a given month (July in Pakistan).
 */
final class FiscalYear
{
    public function __construct(
        public readonly int $startYear,
        public readonly int $startMonth = 7,
    ) {
        //
    }

    /**
     * Get the fiscal year that contains the given date.
     */
    public static function for(DateTimeInterface $date, int $startMonth = 7): self
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        return new self($month >= $startMonth ? $year : $year - 1, $startMonth);
    }

    /**
     * Get the label of the fiscal year, e.g. "2026-27" (or "2026" for calendar years).
     */
    public function label(): string
    {
        if ($this->startMonth === 1) {
            return (string) $this->startYear;
        }

        return sprintf('%d-%02d', $this->startYear, ($this->startYear + 1) % 100);
    }

    /**
     * Get the short label of the fiscal year, e.g. "2627" (or "26" for calendar years).
     */
    public function short(): string
    {
        if ($this->startMonth === 1) {
            return sprintf('%02d', $this->startYear % 100);
        }

        return sprintf('%02d%02d', $this->startYear % 100, ($this->startYear + 1) % 100);
    }

    /**
     * Get the first day of the fiscal year.
     */
    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->startYear, $this->startMonth, 1)->startOfDay();
    }

    /**
     * Get the last moment of the fiscal year.
     */
    public function end(): CarbonImmutable
    {
        return $this->start()->addYear()->subDay()->endOfDay();
    }

    /**
     * Get the previous fiscal year.
     */
    public function previous(): self
    {
        return new self($this->startYear - 1, $this->startMonth);
    }
}
