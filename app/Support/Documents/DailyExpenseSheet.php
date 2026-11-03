<?php

namespace App\Support\Documents;

use App\Models\DailyExpense;
use App\Support\CurrentTeam;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\PettyCash;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders the daily expenses of a period as an A4 sheet to print and sign: the expenses per day
 * with the day totals, the totals per category and the cash box.
 */
class DailyExpenseSheet
{
    /**
     * Render the sheet and return the PDF contents.
     *
     * @param  Collection<int, DailyExpense>  $expenses
     */
    public function render(Collection $expenses, CarbonImmutable $from, CarbonImmutable $to, ?string $filter = null): string
    {
        return Pdf::loadView('pdf.daily-expenses', $this->data($expenses, $from, $to, $filter))
            ->setPaper('a4')
            // Embed only the characters used instead of whole font files.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * Get the file name of the sheet.
     */
    public function filename(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return 'daily-expenses-'.$from->toDateString().($from->isSameDay($to) ? '' : '-to-'.$to->toDateString()).'.pdf';
    }

    /**
     * Get the data used by the PDF view.
     *
     * @param  Collection<int, DailyExpense>  $expenses
     * @return array<string, mixed>
     */
    public function data(Collection $expenses, CarbonImmutable $from, CarbonImmutable $to, ?string $filter = null): array
    {
        $team = app(CurrentTeam::class)->team();
        $department = $team->defaultDepartment();
        $options = DocumentOptions::forTeam($team);

        $days = [];
        $categories = [];
        $total = BigDecimal::zero();

        foreach ($expenses as $expense) {
            $day = $expense->date->toDateString();
            $days[$day] ??= ['date' => $expense->date, 'expenses' => [], 'total' => BigDecimal::zero()];
            $days[$day]['expenses'][] = $expense;
            $days[$day]['total'] = $days[$day]['total']->plus(BigDecimal::of($expense->amount));

            $category = $expense->category->name ?? 'No category';
            $categories[$category] ??= ['name' => $category, 'count' => 0, 'total' => BigDecimal::zero()];
            $categories[$category]['count']++;
            $categories[$category]['total'] = $categories[$category]['total']->plus(BigDecimal::of($expense->amount));

            $total = $total->plus(BigDecimal::of($expense->amount));
        }

        usort($categories, fn (array $a, array $b) => $b['total']->compareTo($a['total']));

        return [
            'department' => $department,
            'logo' => $department?->logoDataUri(),
            'options' => $options,
            'currency' => $team->teamSettings()->currency(),
            'period' => $from->isSameDay($to)
                ? $options->date($from)
                : $options->date($from).' – '.$options->date($to),
            'filter' => $filter,
            'days' => array_values($days),
            'categories' => $categories,
            'count' => $expenses->count(),
            'total' => (string) $total->toScale(2),
            // The cash box only adds up when nothing is filtered out.
            'cash' => $filter === null && PettyCash::inUse() ? PettyCash::summary($from, $to) : null,
            'printedAt' => now(),
        ];
    }
}
