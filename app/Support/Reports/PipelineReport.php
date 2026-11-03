<?php

namespace App\Support\Reports;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\DealPipeline;
use App\Support\CurrentTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The sales pipeline: open deals per phase and the deals won and lost in a period.
 * Amounts only include deals in the team's currency; others are counted separately.
 */
final class PipelineReport
{
    /**
     * Get the report for a pipeline (the default one when none is given).
     *
     * @return array{
     *     pipelines: list<array{id: int, name: string}>,
     *     pipeline_id: int|null,
     *     currency: string,
     *     phases: list<array{id: int, name: string, probability: int, count: int, value: string, weighted: string}>,
     *     open: array{count: int, value: string, weighted: string},
     *     won: array{count: int, value: string},
     *     lost: array{count: int, value: string},
     *     win_rate: int|null,
     *     average_won: string,
     *     lost_reasons: list<array{name: string, count: int, value: string}>,
     *     sources: list<array{name: string, count: int, value: string}>,
     *     people: list<array{name: string, count: int, value: string}>,
     *     other_currency: int
     * }
     */
    public static function for(?int $pipelineId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $currency = app(CurrentTeam::class)->team()->teamSettings()->currency();
        $pipelines = DealPipeline::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get(['id', 'name']);
        $pipeline = $pipelines->firstWhere('id', $pipelineId) ?? $pipelines->first();
        $id = $pipeline?->id;

        $open = Deal::query()
            ->where('deals.pipeline_id', $id)
            ->where('deals.status', DealStatus::Open)
            ->where('deals.currency', $currency)
            ->join('deal_phases', 'deal_phases.id', '=', 'deals.phase_id')
            ->toBase()
            ->groupBy('deals.phase_id')
            ->select('deals.phase_id')
            ->selectRaw('COUNT(*) as deals')
            ->selectRaw('SUM(deals.value) as value')
            ->selectRaw('SUM(deals.value * COALESCE(deals.probability, deal_phases.probability) / 100) as weighted')
            ->get()
            ->keyBy('phase_id');

        $phases = [];
        $totals = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0];

        foreach (DealPhase::query()->where('pipeline_id', $id)->orderBy('sort')->orderBy('id')->get() as $phase) {
            $row = $open->get($phase->id);
            $count = (int) ($row->deals ?? 0);
            $value = (float) ($row->value ?? 0);
            $weighted = (float) ($row->weighted ?? 0);

            $phases[] = [
                'id' => $phase->id,
                'name' => $phase->name,
                'probability' => $phase->probability,
                'count' => $count,
                'value' => Sql::money($value),
                'weighted' => Sql::money($weighted),
            ];

            $totals['count'] += $count;
            $totals['value'] += $value;
            $totals['weighted'] += $weighted;
        }

        $closed = fn (DealStatus $status): Builder => Deal::query()
            ->where('deals.pipeline_id', $id)
            ->where('deals.status', $status)
            ->where('deals.currency', $currency)
            ->whereBetween('deals.closed_at', [$start->startOfDay(), $end->endOfDay()]);

        $wonCount = $closed(DealStatus::Won)->count();
        $wonValue = (float) $closed(DealStatus::Won)->sum('deals.value');
        $lostCount = $closed(DealStatus::Lost)->count();

        return [
            'pipelines' => array_values($pipelines->map(fn (DealPipeline $item) => ['id' => $item->id, 'name' => $item->name])->all()),
            'pipeline_id' => $id,
            'currency' => $currency,
            'phases' => $phases,
            'open' => [
                'count' => $totals['count'],
                'value' => Sql::money($totals['value']),
                'weighted' => Sql::money($totals['weighted']),
            ],
            'won' => ['count' => $wonCount, 'value' => Sql::money($wonValue)],
            'lost' => ['count' => $lostCount, 'value' => Sql::money($closed(DealStatus::Lost)->sum('deals.value'))],
            'win_rate' => $wonCount + $lostCount > 0 ? (int) round($wonCount * 100 / ($wonCount + $lostCount)) : null,
            'average_won' => Sql::money($wonCount > 0 ? $wonValue / $wonCount : 0),
            'lost_reasons' => self::grouped($closed(DealStatus::Lost), 'lost_reasons', 'deals.lost_reason_id', 'No reason given'),
            'sources' => self::grouped($closed(DealStatus::Won), 'deal_sources', 'deals.source_id', 'Unknown source'),
            'people' => self::grouped($closed(DealStatus::Won), 'users', 'deals.responsible_user_id', 'Nobody'),
            'other_currency' => Deal::query()->where('deals.status', DealStatus::Open)->where('deals.currency', '!=', $currency)->count(),
        ];
    }

    /**
     * Count and sum deals per name of a related table (reasons, sources, people), largest first.
     *
     * @param  Builder<Deal>  $deals
     * @return list<array{name: string, count: int, value: string}>
     */
    private static function grouped(Builder $deals, string $table, string $foreignKey, string $fallback): array
    {
        return array_values($deals
            ->leftJoin($table, "{$table}.id", '=', $foreignKey)
            ->toBase()
            ->groupBy("{$table}.id", "{$table}.name")
            ->select("{$table}.name")
            ->selectRaw('COUNT(*) as deals')
            ->selectRaw('SUM(deals.value) as value')
            ->orderByRaw('SUM(deals.value) DESC')
            ->get()
            ->map(fn (object $row) => [
                'name' => (string) ($row->name ?? $fallback),
                'count' => (int) $row->deals,
                'value' => Sql::money($row->value),
            ])
            ->all());
    }
}
