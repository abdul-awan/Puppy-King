<?php

namespace App\Support\Reports;

use App\Models\Company;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkType;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * The hours worked in a period, grouped per person, project, customer or work type,
 * with their billable value and how much of it is invoiced.
 */
final class TimeReport
{
    /**
     * The ways the hours can be grouped.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'user' => 'Person',
        'project' => 'Project',
        'customer' => 'Customer',
        'work_type' => 'Work type',
    ];

    /**
     * The column behind every grouping.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'user' => 'user_id',
        'project' => 'project_id',
        'customer' => 'company_id',
        'work_type' => 'work_type_id',
    ];

    /**
     * Get the report (only the hours of one person when a user id is given).
     *
     * @return array{
     *     rows: list<array{id: int|null, name: string, minutes: int, billable_minutes: int, value: string, invoiced: string, unbilled: string}>,
     *     totals: array{minutes: int, billable_minutes: int, value: string, invoiced: string, unbilled: string}
     * }
     */
    public static function for(string $groupBy, CarbonImmutable $start, CarbonImmutable $end, ?int $userId = null): array
    {
        $column = self::COLUMNS[$groupBy] ?? self::COLUMNS['user'];

        $groups = DateRange::apply(TimeEntry::query(), 'date', [$start, $end])
            ->when($userId, fn ($query, int $id) => $query->where('user_id', $id))
            ->toBase()
            ->groupBy($column)
            ->selectRaw("{$column} as group_id")
            ->selectRaw('SUM(duration_minutes) as minutes')
            ->selectRaw('SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END) as billable_minutes')
            ->selectRaw('SUM(CASE WHEN is_billable = 1 THEN duration_minutes * hourly_rate / 60 ELSE 0 END) as value')
            ->selectRaw('SUM(CASE WHEN is_billable = 1 AND invoice_id IS NOT NULL THEN duration_minutes * hourly_rate / 60 ELSE 0 END) as invoiced')
            ->get();

        $names = self::names($groupBy, $groups->pluck('group_id')->filter()->map(fn ($id) => (int) $id)->all());
        $rows = [];
        $totals = ['minutes' => 0, 'billable_minutes' => 0, 'value' => 0.0, 'invoiced' => 0.0];

        foreach ($groups as $group) {
            $id = $group->group_id !== null ? (int) $group->group_id : null;
            $value = (float) $group->value;
            $invoiced = (float) $group->invoiced;

            $rows[] = [
                'id' => $id,
                'name' => $id !== null ? ($names[$id] ?? 'Deleted') : self::emptyLabel($groupBy),
                'minutes' => (int) $group->minutes,
                'billable_minutes' => (int) $group->billable_minutes,
                'value' => Sql::money($value),
                'invoiced' => Sql::money($invoiced),
                'unbilled' => Sql::money($value - $invoiced),
            ];

            $totals['minutes'] += (int) $group->minutes;
            $totals['billable_minutes'] += (int) $group->billable_minutes;
            $totals['value'] += $value;
            $totals['invoiced'] += $invoiced;
        }

        usort($rows, fn (array $a, array $b) => $b['minutes'] <=> $a['minutes']);

        return [
            'rows' => $rows,
            'totals' => [
                'minutes' => $totals['minutes'],
                'billable_minutes' => $totals['billable_minutes'],
                'value' => Sql::money($totals['value']),
                'invoiced' => Sql::money($totals['invoiced']),
                'unbilled' => Sql::money($totals['value'] - $totals['invoiced']),
            ],
        ];
    }

    /**
     * The names of the grouped records.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private static function names(string $groupBy, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return match ($groupBy) {
            'project' => Project::query()->withTrashed()->whereKey($ids)->get(['id', 'number', 'title'])
                ->mapWithKeys(fn (Project $project) => [$project->id => trim(($project->number ?? '').' '.$project->title)])->all(),
            'customer' => Company::query()->withTrashed()->whereKey($ids)->pluck('name', 'id')->all(),
            'work_type' => WorkType::query()->whereKey($ids)->pluck('name', 'id')->all(),
            default => User::query()->whereKey($ids)->pluck('name', 'id')->all(),
        };
    }

    /**
     * The label of hours without a project, customer or work type.
     */
    private static function emptyLabel(string $groupBy): string
    {
        return match ($groupBy) {
            'project' => 'No project',
            'customer' => 'No customer',
            'work_type' => 'No work type',
            default => 'Unknown',
        };
    }
}
