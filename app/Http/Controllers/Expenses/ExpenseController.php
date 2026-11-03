<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\ExpenseResource;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\DocumentNumberer;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    /**
     * The status filters of the expense list.
     */
    private const STATUS_FILTERS = ['pending', 'to_pay', 'overdue', 'paid', 'rejected'];

    /**
     * Display a listing of the expenses.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $today = CarbonImmutable::today();
        $thisMonth = [$today->startOfMonth(), $today->endOfMonth()];
        $balance = 'SUM((total - income_tax_withheld - sales_tax_withheld - amount_paid) * exchange_rate)';

        return Inertia::render('expenses/Index', [
            'expenses' => ExpenseResource::collection(
                $this->filteredQuery($filters)
                    ->with('category:id,name')
                    ->latest('date')
                    ->latest('id')
                    ->paginate(25)
                    ->withQueryString(),
            ),
            'filters' => $filters,
            'periods' => DateRange::options(),
            'categories' => $this->categories(),
            'summary' => [
                'to_approve_count' => Expense::query()->where('status', ExpenseStatus::Pending)->count(),
                'to_pay_total' => (string) (Expense::query()->unpaid()->selectRaw("{$balance} as balance")->value('balance') ?? 0),
                'overdue_total' => (string) (Expense::query()->unpaid()->whereDate('due_date', '<', $today)->selectRaw("{$balance} as balance")->value('balance') ?? 0),
                'month_total' => (string) (DateRange::apply(Expense::query(), 'date', $thisMonth)
                    ->where('status', '!=', ExpenseStatus::Rejected)
                    ->selectRaw('SUM(total * exchange_rate) as total')
                    ->value('total') ?? 0),
                'month_input_tax' => (string) (DateRange::apply(Expense::query(), 'date', $thisMonth)
                    ->where('status', ExpenseStatus::Approved)
                    ->where('tax_claimable', true)
                    ->selectRaw('SUM(tax_amount * exchange_rate) as tax')
                    ->value('tax') ?? 0),
            ],
        ]);
    }

    /**
     * Show the form for entering a new expense.
     */
    public function create(Request $request): Response
    {
        $company = $request->integer('company') ? Company::query()->find($request->integer('company')) : null;

        return Inertia::render('expenses/Create', [
            'prefill' => [
                'company' => $company?->only(['id', 'name']),
                'supplier_name' => $company?->name,
                'supplier_ntn' => $company?->ntn,
                'supplier_strn' => $company?->strn,
                'currency' => $company->currency ?? app(CurrentTeam::class)->team()->teamSettings()->currency(),
                'date' => CarbonImmutable::today()->toDateString(),
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a new expense (with the scanned bill, if uploaded).
     */
    public function store(ExpenseRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $expense = DB::transaction(function () use ($request, $numberer, $team) {
            $expense = new Expense([...$request->expense(), 'created_by' => $request->user()->id]);
            $expense->team_id = $team->id;
            $this->prepare($expense, $request);
            $expense->number = $numberer->next($team, DocumentType::Expense, $expense->date);

            if ($request->boolean('approve_now')) {
                $expense->status = ExpenseStatus::Approved;
                $expense->approved_by = $request->user()->id;
                $expense->approved_at = now();
            }

            $expense->save();

            return $expense;
        });

        $this->attachFiles($expense, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense :number saved.', ['number' => $expense->number])]);

        return to_route('expenses.show', $expense);
    }

    /**
     * Display the expense.
     */
    public function show(Expense $expense): Response
    {
        $expense->load(['supplier:id,name', 'category:id,name', 'taxRate:id,name', 'approver:id,name', 'creator:id,name', 'payments.user:id,name']);

        return Inertia::render('expenses/Show', [
            'expense' => ExpenseResource::make($expense),
            'timeline' => Timeline::for($expense),
            'attachments' => AttachmentResource::collection($expense->attachments()->with('user:id,name')->get()),
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Show the form for editing the expense.
     */
    public function edit(Expense $expense): Response
    {
        $this->ensureNoPayments($expense);

        $expense->load('supplier:id,name');

        return Inertia::render('expenses/Edit', [
            'expense' => ExpenseResource::make($expense),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the expense. A changed approved expense needs to be approved again.
     */
    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->ensureNoPayments($expense);

        DB::transaction(function () use ($request, $expense) {
            $expense->fill($request->expense());
            $this->prepare($expense, $request);

            if ($expense->status === ExpenseStatus::Approved && $expense->isDirty(['net_amount', 'tax_amount', 'total', 'income_tax_withheld', 'sales_tax_withheld', 'company_id', 'supplier_name'])) {
                $expense->status = ExpenseStatus::Pending;
                $expense->approved_by = null;
                $expense->approved_at = null;
                $expense->logActivity('reset', 'Changed after approval: needs to be approved again');
            }

            $expense->save();
        });

        $this->attachFiles($expense, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return to_route('expenses.show', $expense);
    }

    /**
     * Delete the expense (only when nothing was paid on it).
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->ensureNoPayments($expense);

        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);

        return to_route('expenses.index');
    }

    /**
     * Approve the expense for payment.
     */
    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $expense->forceFill([
            'status' => ExpenseStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $expense->logActivity('approved', 'Approved for payment');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense approved.')]);

        return back();
    }

    /**
     * Reject the expense (e.g. a wrong or duplicate bill).
     */
    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $this->ensureNoPayments($expense);

        $expense->forceFill([
            'status' => ExpenseStatus::Rejected,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $validated['reason'] ?? null,
        ])->save();

        $expense->logActivity('rejected', 'Rejected'.(filled($validated['reason'] ?? null) ? ': '.$validated['reason'] : ''));

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Expense rejected.')]);

        return back();
    }

    /**
     * Download the purchase register (approved expenses) as CSV, e.g. for the sales tax return.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($this->filters($request))
            ->with('category:id,name')
            ->where('status', ExpenseStatus::Approved)
            ->orderBy('date')
            ->orderBy('number');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Number', 'Date', 'Supplier', 'NTN', 'STRN', 'Bill no.', 'Category', 'Currency', 'Value excl. tax',
                'Sales tax', 'Input tax claimable', 'Total', 'Income tax withheld', 'Sales tax withheld', 'Paid', 'Status',
            ], ',', '"', '');

            $query->chunk(500, function (Collection $expenses) use ($out) {
                foreach ($expenses as $expense) {
                    /** @var Expense $expense */
                    fputcsv($out, [
                        $expense->number,
                        $expense->date->toDateString(),
                        $expense->supplier_name,
                        $expense->supplier_ntn,
                        $expense->supplier_strn,
                        $expense->bill_number,
                        $expense->category?->name,
                        $expense->currency,
                        $expense->net_amount,
                        $expense->tax_amount,
                        $expense->tax_claimable ? 'Yes' : 'No',
                        $expense->total,
                        $expense->income_tax_withheld,
                        $expense->sales_tax_withheld,
                        $expense->amount_paid,
                        $expense->displayStatus()['label'],
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'purchase-register-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Read the list filters from the request.
     *
     * @return array{search: string, status: string|null, category: int|null, company: int|null, period: string|null, from: string|null, to: string|null}
     */
    protected function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($request->input('status'), self::STATUS_FILTERS, true) ? (string) $request->input('status') : null,
            'category' => $request->integer('category') ?: null,
            'company' => $request->integer('company') ?: null,
            ...DateRange::filters($request),
        ];
    }

    /**
     * Get the expenses matching the filters.
     *
     * @param  array{search: string, status: string|null, category: int|null, company: int|null, period: string|null, from: string|null, to: string|null}  $filters
     * @return Builder<Expense>
     */
    protected function filteredQuery(array $filters): Builder
    {
        $range = DateRange::resolve($filters);

        return Expense::query()
            ->search($filters['search'])
            ->when($filters['status'], fn (Builder $query, string $status) => match ($status) {
                'to_pay' => $query->unpaid(),
                'overdue' => $query->unpaid()->whereDate('due_date', '<', today()),
                'paid' => $query->where('status', ExpenseStatus::Approved)->whereRaw('total - income_tax_withheld - sales_tax_withheld - amount_paid <= 0'),
                default => $query->where('status', $status),
            })
            ->when($filters['category'], fn (Builder $query, int $category) => $query->where('category_id', $category))
            ->when($filters['company'], fn (Builder $query, int $company) => $query->where('company_id', $company))
            ->when($range, fn (Builder $query, array $range) => DateRange::apply($query, 'date', $range));
    }

    /**
     * Fill in the supplier details from the CRM and calculate the amounts.
     */
    protected function prepare(Expense $expense, ExpenseRequest $request): void
    {
        $supplier = $expense->company_id ? Company::query()->find($expense->company_id) : null;

        if ($supplier !== null) {
            $expense->supplier_name = $supplier->name;
            $expense->supplier_ntn = $expense->supplier_ntn ?: $supplier->ntn;
            $expense->supplier_strn = $expense->supplier_strn ?: $supplier->strn;
        }

        $rate = $request->validated('tax_rate_id') ? TaxRate::query()->find($request->integer('tax_rate_id')) : null;
        $taxAmount = $request->validated('tax_amount');

        $expense->applyAmounts($rate, $taxAmount === null ? null : (string) $taxAmount);
    }

    /**
     * Store the uploaded bill scans.
     */
    protected function attachFiles(Expense $expense, ExpenseRequest $request): void
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files', []);

        foreach ($files as $file) {
            $expense->attachFile($file, $request->user());
        }
    }

    /**
     * Paid expenses cannot be changed or rejected; remove the payments first.
     */
    protected function ensureNoPayments(Expense $expense): void
    {
        if ($expense->payments()->exists()) {
            throw ValidationException::withMessages(['expense' => 'Payments were recorded on this expense. Remove them first.']);
        }
    }

    /**
     * Get the expense categories.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function categories(): array
    {
        return ExpenseCategory::query()->orderBy('sort')->orderBy('name')->get(['id', 'name'])
            ->map(fn (ExpenseCategory $category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'categories' => $this->categories(),
            'taxRates' => TeamOptions::taxRates(),
            'currencies' => TeamOptions::currencies(),
        ];
    }
}
