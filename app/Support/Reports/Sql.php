<?php

namespace App\Support\Reports;

use Illuminate\Support\Facades\DB;

/**
 * SQL snippets that differ between the databases the app runs on (MariaDB/MySQL, SQLite in tests).
 */
final class Sql
{
    /**
     * An expression giving the year and month (YYYY-MM) of a date column.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function month(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * An expression giving the day (YYYY-MM-DD) of a date column (SQLite keeps a time in date columns).
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function day(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "date({$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /**
     * Format a summed amount with two decimals.
     */
    public static function money(mixed $value): string
    {
        return number_format(is_numeric($value) ? (float) $value : 0.0, 2, '.', '');
    }
}
