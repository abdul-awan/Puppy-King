<?php

namespace App\Support;

use App\Enums\CashDirection;
use App\Enums\PaymentMethod;
use App\Models\DailyExpense;
use App\Models\PettyCashEntry;
use App\Support\Reports\Sql;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The cash box (petty cash) of the current team: cash added and taken out, and the daily
 * expenses paid in cash.
 */
final class PettyCash
{
    /**
     * Get the cash in hand at the end of a day (or now).
     */
    public static function balance(?CarbonImmutable $upTo = null): BigDecimal
    {
        $added = self::entries(CashDirection::In);
        $takenOut = self::entries(CashDirection::Out);
        $spent = self::cashExpenses();

        if ($upTo !== null) {
            $added->whereDate('date', '<=', $upTo);
            $takenOut->whereDate('date', '<=', $upTo);
            $spent->whereDate('date', '<=', $upTo);
        }

        return self::sum($added)->minus(self::sum($takenOut))->minus(self::sum($spent));
    }

    /**
     * Get the cash in hand at the start of a day.
     */
    public static function openingBalance(CarbonImmutable $date): BigDecimal
    {
        return self::balance($date->subDay());
    }

    /**
     * Get the cash added, the cash taken out and the cash expenses of a period.
     *
     * @return array{opening: string, added: string, taken_out: string, expenses: string, closing: string}
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $opening = self::openingBalance($from);
        $added = self::sum(self::between(self::entries(CashDirection::In), $from, $to));
        $takenOut = self::sum(self::between(self::entries(CashDirection::Out), $from, $to));
        $expenses = self::sum(self::between(self::cashExpenses(), $from, $to));

        return [
            'opening' => (string) $opening,
            'added' => (string) $added,
            'taken_out' => (string) $takenOut,
            'expenses' => (string) $expenses,
            'closing' => (string) $opening->plus($added)->minus($takenOut)->minus($expenses),
        ];
    }

    /**
     * Get every movement of the cash box in a period, oldest first, with the balance after each.
     *
     * @return list<array{key: string, type: string, id: int, date: string, number: string|null, description: string, person: string|null, in: string|null, out: string|null, balance: string}>
     */
    public static function movements(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];

        foreach (self::between(PettyCashEntry::query(), $from, $to)->get() as $entry) {
            $rows[] = [
                'key' => 'entry-'.$entry->id,
                'type' => 'entry',
                'id' => $entry->id,
                'date' => $entry->date->toDateString(),
                'number' => null,
                'description' => $entry->description,
                'person' => $entry->person,
                'in' => $entry->direction === CashDirection::In ? $entry->amount : null,
                'out' => $entry->direction === CashDirection::Out ? $entry->amount : null,
                'order' => [$entry->direction === CashDirection::In ? 0 : 1, $entry->created_at?->getTimestamp() ?? 0, $entry->id],
            ];
        }

        foreach (self::between(self::cashExpenses(), $from, $to)->with('category:id,name')->get() as $expense) {
            $rows[] = [
                'key' => 'expense-'.$expense->id,
                'type' => 'expense',
                'id' => $expense->id,
                'date' => $expense->date->toDateString(),
                'number' => $expense->number,
                'description' => trim(($expense->category ? $expense->category->name.': ' : '').$expense->description),
                'person' => $expense->paid_by,
                'in' => null,
                'out' => $expense->amount,
                'order' => [1, $expense->created_at?->getTimestamp() ?? 0, $expense->id],
            ];
        }

        // Day by day; on a day the cash added comes before what was spent.
        usort($rows, fn (array $a, array $b) => [$a['date'], ...$a['order']] <=> [$b['date'], ...$b['order']]);

        $balance = self::openingBalance($from);
        $movements = [];

        foreach ($rows as $row) {
            $balance = $balance->plus(BigDecimal::of($row['in'] ?? '0'))->minus(BigDecimal::of($row['out'] ?? '0'));
            unset($row['order']);
            $movements[] = [...$row, 'balance' => (string) $balance];
        }

        return $movements;
    }

    /**
     * Determine if the team keeps a cash box (it added cash or paid expenses in cash).
     */
    public static function inUse(): bool
    {
        return PettyCashEntry::query()->exists() || self::cashExpenses()->exists();
    }

    /**
     * Get the cash added to or taken out of the cash box.
     *
     * @return Builder<PettyCashEntry>
     */
    private static function entries(CashDirection $direction): Builder
    {
        return PettyCashEntry::query()->where('direction', $direction);
    }

    /**
     * Get the daily expenses paid in cash.
     *
     * @return Builder<DailyExpense>
     */
    private static function cashExpenses(): Builder
    {
        return DailyExpense::query()->where('payment_method', PaymentMethod::Cash);
    }

    /**
     * Limit a query to the days of a period.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function between(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }

    /**
     * Add up the amounts of a query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function sum(Builder $query): BigDecimal
    {
        return BigDecimal::of(Sql::money($query->sum('amount')));
    }
}
