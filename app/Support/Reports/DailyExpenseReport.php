<?php

namespace App\Support\Reports;

use App\Enums\PaymentMethod;
use App\Models\DailyExpense;
use App\Models\DailyExpenseCategory;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * The daily expenses of a period per category, per way of paying, per person and over time.
 */
final class DailyExpenseReport
{
    /**
     * Get the report for the period.
     *
     * @return array{
     *     categories: list<array{id: int|null, name: string, count: int, total: string}>,
     *     methods: list<array{method: string, label: string, count: int, total: string}>,
     *     people: list<array{name: string, count: int, total: string}>,
     *     timeline: array{unit: string, items: list<array{key: string, total: string}>},
     *     totals: array{count: int, total: string, cash: string, per_day: string}
     * }
     */
    public static function for(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $query = fn () => DateRange::apply(DailyExpense::query(), 'date', [$start, $end])->toBase();

        $perCategory = $query()
            ->groupBy('category_id')
            ->select('category_id')
            ->selectRaw('COUNT(*) as expenses, SUM(amount) as total')
            ->get();

        $names = DailyExpenseCategory::query()->whereKey($perCategory->pluck('category_id')->filter()->all())->pluck('name', 'id');

        $categories = $perCategory
            ->map(fn (object $row) => [
                'id' => $row->category_id !== null ? (int) $row->category_id : null,
                'name' => $row->category_id !== null ? (string) ($names[(int) $row->category_id] ?? 'Deleted category') : 'No category',
                'count' => (int) $row->expenses,
                'total' => Sql::money($row->total),
            ])
            ->sortByDesc(fn (array $row) => (float) $row['total'])
            ->values()
            ->all();

        $methods = $query()
            ->groupBy('payment_method')
            ->select('payment_method')
            ->selectRaw('COUNT(*) as expenses, SUM(amount) as total')
            ->get()
            ->map(fn (object $row) => [
                'method' => (string) $row->payment_method,
                'label' => PaymentMethod::tryFrom((string) $row->payment_method)?->label() ?? (string) $row->payment_method,
                'count' => (int) $row->expenses,
                'total' => Sql::money($row->total),
            ])
            ->sortByDesc(fn (array $row) => (float) $row['total'])
            ->values()
            ->all();

        $people = $query()
            ->groupBy('paid_by')
            ->select('paid_by')
            ->selectRaw('COUNT(*) as expenses, SUM(amount) as total')
            ->get()
            ->map(fn (object $row) => [
                'name' => filled($row->paid_by) ? (string) $row->paid_by : 'Not filled in',
                'count' => (int) $row->expenses,
                'total' => Sql::money($row->total),
            ])
            ->sortByDesc(fn (array $row) => (float) $row['total'])
            ->take(10)
            ->values()
            ->all();

        // Per day for up to two months, per month for longer periods.
        $perDay = $start->diffInDays($end) <= 62;
        $bucket = $perDay ? Sql::day('date') : Sql::month('date');

        $timeline = $query()
            ->selectRaw("{$bucket} as bucket, SUM(amount) as total")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(fn (object $row) => ['key' => (string) $row->bucket, 'total' => Sql::money($row->total)])
            ->values()
            ->all();

        // The average per day counts the days up to today.
        $today = CarbonImmutable::today()->endOfDay();
        $total = (float) $query()->sum('amount');
        $days = max(1, (int) round($start->diffInDays($end->isAfter($today) ? $today : $end)) + 1);

        return [
            'categories' => array_values($categories),
            'methods' => array_values($methods),
            'people' => array_values($people),
            'timeline' => ['unit' => $perDay ? 'day' : 'month', 'items' => array_values($timeline)],
            'totals' => [
                'count' => (int) $query()->count(),
                'total' => Sql::money($total),
                'cash' => Sql::money($query()->where('payment_method', PaymentMethod::Cash->value)->sum('amount')),
                'per_day' => Sql::money($total / $days),
            ],
        ];
    }
}
