<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

/**
 * Period filters for lists and reports, using the team's fiscal year (July–June by default).
 */
final class DateRange
{
    /**
     * The selectable periods.
     *
     * @var array<string, string>
     */
    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'this_fiscal_year' => 'This fiscal year',
        'last_fiscal_year' => 'Last fiscal year',
        'custom' => 'Custom dates',
    ];

    /**
     * Read the period filter (period, from, to) from the request.
     *
     * @return array{period: string|null, from: string|null, to: string|null}
     */
    public static function filters(Request $request): array
    {
        $period = $request->string('period')->toString();

        return [
            'period' => array_key_exists($period, self::PERIODS) ? $period : null,
            'from' => self::date($request->string('from')->toString())?->toDateString(),
            'to' => self::date($request->string('to')->toString())?->toDateString(),
        ];
    }

    /**
     * Resolve the period to a first and last day (null means no date filter).
     *
     * @param  array{period: string|null, from: string|null, to: string|null}  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public static function resolve(array $filters): ?array
    {
        $today = CarbonImmutable::today();
        $fiscalYear = FiscalYear::for($today, app(CurrentTeam::class)->team()->teamSettings()->fiscalYearStartMonth());

        return match ($filters['period']) {
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'this_fiscal_year' => [$fiscalYear->start(), $fiscalYear->end()],
            'last_fiscal_year' => [$fiscalYear->previous()->start(), $fiscalYear->previous()->end()],
            'custom' => $filters['from'] || $filters['to']
                ? [
                    self::date($filters['from']) ?? CarbonImmutable::create(2000),
                    self::date($filters['to'])?->endOfDay() ?? $today->addYears(10),
                ]
                : null,
            default => null,
        };
    }

    /**
     * Limit a query to the period. Whole days are compared (start of the first day to the end
     * of the last), so DATE and DATETIME columns behave the same on MariaDB and SQLite.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}|null  $range
     * @return TQuery
     */
    public static function apply(Builder $query, string $column, ?array $range): Builder
    {
        if ($range !== null) {
            $query->whereBetween($column, [$range[0]->startOfDay(), $range[1]->endOfDay()]);
        }

        return $query;
    }

    /**
     * Get the periods as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys(self::PERIODS),
            self::PERIODS,
        );
    }

    /**
     * Parse a Y-m-d date, ignoring invalid input.
     */
    private static function date(?string $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)?->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
