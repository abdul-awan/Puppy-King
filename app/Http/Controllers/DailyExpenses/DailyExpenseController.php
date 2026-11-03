<?php

namespace App\Http\Controllers\DailyExpenses;

use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\DailyExpenses\DailyExpenseRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\DailyExpenseResource;
use App\Models\DailyExpense;
use App\Models\DailyExpenseCategory;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\DocumentNumberer;
use App\Support\Documents\DailyExpenseSheet;
use App\Support\PettyCash;
use App\Support\Reports\Sql;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Day-to-day spending (tea, fuel, stationery…), mostly paid from the cash box.
 */
class DailyExpenseController extends Controller
{
    /**
     * Display the daily expenses, newest day first, with the total of each day.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $today = CarbonImmutable::today();

        $expenses = $this->filteredQuery($filters)
            ->with('category:id,name')
            ->withCount('attachments')
            ->latest('date')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        // The totals of the days on this page (of all their expenses matching the filters).
        $dates = collect($expenses->items())->map(fn (DailyExpense $expense) => $expense->date);
        $dayTotals = $dates->isEmpty() ? [] : $this->filteredQuery($filters)
            ->whereDate('date', '>=', $dates->min())
            ->whereDate('date', '<=', $dates->max())
            ->toBase()
            ->selectRaw(Sql::day('date').' as day, SUM(amount) as total, COUNT(*) as expenses')
            ->groupByRaw(Sql::day('date'))
            ->get()
            ->mapWithKeys(fn (object $row) => [(string) $row->day => ['total' => Sql::money($row->total), 'count' => (int) $row->expenses]])
            ->all();

        $month = DateRange::apply(DailyExpense::query(), 'date', [$today->startOfMonth(), $today->endOfMonth()]);
        $cash = PettyCash::inUse();

        return Inertia::render('daily-expenses/Index', [
            'expenses' => DailyExpenseResource::collection($expenses),
            'dayTotals' => (object) $dayTotals,
            'filters' => $filters,
            'periods' => DateRange::options(),
            'summary' => [
                'today' => Sql::money(DailyExpense::query()->whereDate('date', $today)->sum('amount')),
                'today_count' => DailyExpense::query()->whereDate('date', $today)->count(),
                'month' => Sql::money((clone $month)->sum('amount')),
                'month_count' => (clone $month)->count(),
                'month_cash' => Sql::money((clone $month)->where('payment_method', PaymentMethod::Cash)->sum('amount')),
                'cash_in_hand' => $cash ? (string) PettyCash::balance() : null,
            ],
            'filtered' => $this->isFiltered($filters) ? [
                'total' => Sql::money($this->filteredQuery($filters)->sum('amount')),
                'count' => $this->filteredQuery($filters)->count(),
            ] : null,
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a new daily expense (with the receipts, if uploaded).
     */
    public function store(DailyExpenseRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $expense = DB::transaction(function () use ($request, $numberer, $team) {
            $expense = new DailyExpense([...$request->expense(), 'created_by' => $request->user()->id]);
            $expense->team_id = $team->id;
            $expense->number = $numberer->next($team, DocumentType::DailyExpense, $expense->date);
            $expense->save();

            return $expense;
        });

        $this->attachFiles($expense, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense :number saved.', ['number' => $expense->number])]);

        return back();
    }

    /**
     * Display the daily expense with its receipts and history.
     */
    public function show(DailyExpense $dailyExpense): Response
    {
        $dailyExpense->load(['category:id,name', 'creator:id,name']);

        return Inertia::render('daily-expenses/Show', [
            'expense' => DailyExpenseResource::make($dailyExpense),
            'attachments' => AttachmentResource::collection($dailyExpense->attachments()->with('user:id,name')->get()),
            'timeline' => Timeline::for($dailyExpense),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the daily expense (and add receipts).
     */
    public function update(DailyExpenseRequest $request, DailyExpense $dailyExpense): RedirectResponse
    {
        $dailyExpense->update($request->expense());

        $this->attachFiles($dailyExpense, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return back();
    }

    /**
     * Delete the daily expense.
     */
    public function destroy(Request $request, DailyExpense $dailyExpense): RedirectResponse
    {
        $dailyExpense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense :number deleted.', ['number' => $dailyExpense->number])]);

        // From its own page, go back to the list (the page no longer exists).
        return $request->boolean('to_list') ? to_route('daily-expenses.index') : back();
    }

    /**
     * Download the daily expenses matching the filters as CSV (for Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($this->filters($request))
            ->with('category:id,name')
            ->orderBy('date')
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Number', 'Date', 'Category', 'Description', 'Amount', 'Paid with', 'Paid by', 'Paid to', 'Receipt no.', 'Notes'], ',', '"', '');

            $query->chunk(500, function (Collection $expenses) use ($out) {
                foreach ($expenses as $expense) {
                    /** @var DailyExpense $expense */
                    fputcsv($out, [
                        $expense->number,
                        $expense->date->toDateString(),
                        $expense->category?->name,
                        $expense->description,
                        $expense->amount,
                        $expense->payment_method->label(),
                        $expense->paid_by,
                        $expense->paid_to,
                        $expense->reference,
                        $expense->notes,
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'daily-expenses-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Show the sheet of the daily expenses matching the filters as PDF (this month when no period is chosen).
     */
    public function pdf(Request $request, DailyExpenseSheet $sheet): HttpResponse
    {
        $filters = $this->filters($request);
        $today = CarbonImmutable::today();

        if (DateRange::resolve($filters) === null) {
            $filters = [...$filters, 'period' => 'this_month', 'from' => null, 'to' => null];
        }

        [$from, $to] = DateRange::resolve($filters) ?? [$today->startOfMonth(), $today->endOfMonth()];

        // An open-ended custom period is printed up to today.
        $to = $to->isAfter($today->addYear()) ? $today : $to->startOfDay();

        $expenses = $this->filteredQuery($filters)
            ->with('category:id,name')
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $category = $filters['category'] !== null ? DailyExpenseCategory::query()->find($filters['category']) : null;
        $method = $filters['method'] !== null ? PaymentMethod::tryFrom($filters['method']) : null;
        $filter = implode(' · ', array_filter([
            $category !== null ? 'Category: '.$category->name : null,
            $method !== null ? 'Paid with: '.$method->label() : null,
            filled($filters['search']) ? 'Search: '.$filters['search'] : null,
        ])) ?: null;

        return response($sheet->render($expenses, $from->startOfDay(), $to, $filter), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$sheet->filename($from, $to).'"',
        ]);
    }

    /**
     * Read the list filters from the request.
     *
     * @return array{search: string, category: int|null, method: string|null, period: string|null, from: string|null, to: string|null}
     */
    protected function filters(Request $request): array
    {
        $method = $request->string('method')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'category' => $request->integer('category') ?: null,
            'method' => PaymentMethod::tryFrom($method) !== null ? $method : null,
            ...DateRange::filters($request),
        ];
    }

    /**
     * Determine if any filter is set.
     *
     * @param  array{search: string, category: int|null, method: string|null, period: string|null, from: string|null, to: string|null}  $filters
     */
    protected function isFiltered(array $filters): bool
    {
        return filled($filters['search']) || $filters['category'] !== null || $filters['method'] !== null || DateRange::resolve($filters) !== null;
    }

    /**
     * Get the daily expenses matching the filters.
     *
     * @param  array{search: string, category: int|null, method: string|null, period: string|null, from: string|null, to: string|null}  $filters
     * @return Builder<DailyExpense>
     */
    protected function filteredQuery(array $filters): Builder
    {
        $range = DateRange::resolve($filters);

        return DailyExpense::query()
            ->search($filters['search'])
            ->when($filters['category'], fn (Builder $query, int $category) => $query->where('category_id', $category))
            ->when($filters['method'], fn (Builder $query, string $method) => $query->where('payment_method', $method))
            ->when($range, fn (Builder $query, array $range) => DateRange::apply($query, 'date', $range));
    }

    /**
     * Store the uploaded receipts.
     */
    protected function attachFiles(DailyExpense $expense, DailyExpenseRequest $request): void
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        foreach ($files as $file) {
            $expense->attachFile($file, $request->user());
        }
    }

    /**
     * Get the option lists of the expense form.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $names = DailyExpense::query()
            ->whereNotNull('paid_by')
            ->where('paid_by', '!=', '')
            ->latest('id')
            ->limit(200)
            ->pluck('paid_by')
            ->merge(array_column(TeamOptions::users(), 'name'))
            ->map(fn (mixed $name) => trim((string) $name))
            ->unique()
            ->sort()
            ->values();

        return [
            'categories' => DailyExpenseCategory::query()->orderBy('sort')->orderBy('name')->get(['id', 'name'])
                ->map(fn (DailyExpenseCategory $category) => ['id' => $category->id, 'name' => $category->name])
                ->values(),
            'paymentMethods' => PaymentMethod::options(),
            'people' => $names,
            'payees' => DailyExpense::query()
                ->whereNotNull('paid_to')
                ->where('paid_to', '!=', '')
                ->latest('id')
                ->limit(300)
                ->pluck('paid_to')
                ->map(fn (mixed $name) => trim((string) $name))
                ->unique()
                ->sort()
                ->values(),
        ];
    }
}
