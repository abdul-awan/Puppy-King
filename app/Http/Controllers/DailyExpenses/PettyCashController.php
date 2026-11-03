<?php

namespace App\Http\Controllers\DailyExpenses;

use App\Enums\CashDirection;
use App\Http\Controllers\Controller;
use App\Models\PettyCashEntry;
use App\Support\DateRange;
use App\Support\PettyCash;
use App\Support\TeamOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cash book of the cash box (petty cash): cash added, cash taken out and the expenses paid in
 * cash, with the cash in hand after each.
 */
class PettyCashController extends Controller
{
    /**
     * Show the cash book of a period (this month by default).
     */
    public function index(Request $request): Response
    {
        [$filters, $from, $to] = $this->period($request);

        return Inertia::render('daily-expenses/CashBook', [
            'filters' => $filters,
            'periods' => array_values(array_filter(DateRange::options(), fn (array $period) => $period['value'] !== 'last_fiscal_year')),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => PettyCash::summary($from, $to),
            'movements' => PettyCash::movements($from, $to),
            'cashInHand' => (string) PettyCash::balance(),
            'entries' => PettyCashEntry::query()
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->get()
                ->mapWithKeys(fn (PettyCashEntry $entry) => [$entry->id => [
                    'id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'direction' => $entry->direction->value,
                    'amount' => $entry->amount,
                    'description' => $entry->description,
                    'person' => $entry->person,
                    'notes' => $entry->notes,
                ]]),
            'directions' => CashDirection::options(),
            'people' => array_column(TeamOptions::users(), 'name'),
        ]);
    }

    /**
     * Record cash added to or taken out of the cash box.
     */
    public function store(Request $request): RedirectResponse
    {
        PettyCashEntry::query()->create([...$this->validated($request), 'created_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved in the cash book.')]);

        return back();
    }

    /**
     * Change a cash book entry.
     */
    public function update(Request $request, PettyCashEntry $pettyCashEntry): RedirectResponse
    {
        $pettyCashEntry->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cash book entry updated.')]);

        return back();
    }

    /**
     * Delete a cash book entry.
     */
    public function destroy(PettyCashEntry $pettyCashEntry): RedirectResponse
    {
        $pettyCashEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cash book entry deleted.')]);

        return back();
    }

    /**
     * Download the cash book of the period as CSV (for Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        [, $from, $to] = $this->period($request);
        $summary = PettyCash::summary($from, $to);
        $movements = PettyCash::movements($from, $to);

        return response()->streamDownload(function () use ($summary, $movements, $from) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Number', 'Description', 'Person', 'Cash in', 'Cash out', 'Cash in hand'], ',', '"', '');
            fputcsv($out, [$from->toDateString(), '', 'Cash at the start', '', '', '', $summary['opening']], ',', '"', '');

            foreach ($movements as $movement) {
                fputcsv($out, [
                    $movement['date'], $movement['number'], $movement['description'], $movement['person'],
                    $movement['in'], $movement['out'], $movement['balance'],
                ], ',', '"', '');
            }

            fclose($out);
        }, 'cash-book-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The chosen period (this month when none is chosen; an open end stops today).
     *
     * @return array{0: array{period: string|null, from: string|null, to: string|null}, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    protected function period(Request $request): array
    {
        $today = CarbonImmutable::today();
        $filters = DateRange::filters($request);
        $filters['period'] ??= 'this_month';
        $range = DateRange::resolve($filters) ?? [$today->startOfMonth(), $today->endOfMonth()];

        $to = $range[1]->isAfter($today->addYear()) ? $today : $range[1]->startOfDay();

        return [$filters, $range[0]->startOfDay(), $to];
    }

    /**
     * Validate a cash book entry.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'direction' => ['required', Rule::enum(CashDirection::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'description' => ['required', 'string', 'max:255'],
            'person' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return [...$validated, 'amount' => number_format((float) $validated['amount'], 2, '.', '')];
    }
}
