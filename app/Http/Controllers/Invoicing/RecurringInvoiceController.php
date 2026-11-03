<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Invoicing\GenerateRecurringInvoice;
use App\Enums\RecurringFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\RecurringInvoiceRequest;
use App\Http\Resources\RecurringInvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\RecurringInvoice;
use App\Support\CurrentTeam;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecurringInvoiceController extends Controller
{
    /**
     * How many times per month each frequency invoices (to show the monthly recurring revenue).
     */
    private const PER_MONTH = [
        'weekly' => 52 / 12,
        'monthly' => 1,
        'quarterly' => 1 / 3,
        'half_yearly' => 1 / 6,
        'yearly' => 1 / 12,
    ];

    /**
     * Display a listing of the recurring invoices.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($request->input('status'), ['active', 'paused', 'ended'], true) ? (string) $request->input('status') : null,
        ];

        $profiles = RecurringInvoice::query()
            ->with(['company:id,name', 'contact:id,first_name,last_name'])
            ->search($filters['search'])
            ->when($filters['status'], fn (Builder $query, string $status) => match ($status) {
                'active' => $query->where('is_active', true)->whereNotNull('next_date'),
                'paused' => $query->where('is_active', false)->whereNotNull('next_date'),
                default => $query->whereNull('next_date'),
            })
            ->orderByRaw('next_date IS NULL')
            ->orderBy('next_date')
            ->paginate(25)
            ->withQueryString();

        $active = RecurringInvoice::query()->where('is_active', true)->whereNotNull('next_date')->get(['frequency', 'total', 'exchange_rate']);

        return Inertia::render('recurring-invoices/Index', [
            'profiles' => RecurringInvoiceResource::collection($profiles),
            'filters' => $filters,
            'summary' => [
                'active_count' => $active->count(),
                'monthly_value' => number_format($active->sum(
                    fn (RecurringInvoice $profile) => (float) $profile->total * (float) $profile->exchange_rate * self::PER_MONTH[$profile->frequency->value],
                ), 2, '.', ''),
            ],
        ]);
    }

    /**
     * Show the form for creating a new recurring invoice.
     */
    public function create(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();
        $company = $request->integer('company') ? Company::query()->find($request->integer('company')) : null;

        return Inertia::render('recurring-invoices/Create', [
            'prefill' => [
                'company' => $company?->only(['id', 'name']),
                'apply_further_tax' => $company !== null && ! $company->sales_tax_registered,
                'currency' => $company->currency ?? $team->teamSettings()->currency(),
                'start_date' => CarbonImmutable::today()->addMonthNoOverflow()->startOfMonth()->toDateString(),
                'payment_term_id' => $company->payment_term_id ?? PaymentTerm::query()->where('is_default', true)->value('id'),
                'department_id' => $team->defaultDepartment()?->id,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created recurring invoice.
     */
    public function store(RecurringInvoiceRequest $request): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $profile = DB::transaction(function () use ($request, $team) {
            $profile = new RecurringInvoice([...$this->attributes($request), 'created_by' => $request->user()->id]);
            $profile->team_id = $team->id;
            $profile->department_id ??= $team->defaultDepartment()?->id;
            $profile->applyFurtherTax($request->boolean('apply_further_tax'));
            $profile->scheduleNext();
            $profile->save();

            $profile->replaceLines($request->validated('lines'));

            return $profile;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring invoice created.')]);

        return to_route('recurring-invoices.show', $profile);
    }

    /**
     * Display the recurring invoice with the invoices it created.
     */
    public function show(RecurringInvoice $recurringInvoice): Response
    {
        $recurringInvoice->load(['lines', 'company:id,name', 'contact', 'paymentTerm:id,name', 'department:id,name']);

        return Inertia::render('recurring-invoices/Show', [
            'profile' => RecurringInvoiceResource::make($recurringInvoice),
            'invoices' => $recurringInvoice->invoices()->latest('date')->latest('id')->limit(24)->get()
                ->map(fn (Invoice $invoice) => $invoice->toSummary())
                ->values(),
            'upcoming' => $this->upcoming($recurringInvoice),
            'timeline' => Timeline::for($recurringInvoice),
        ]);
    }

    /**
     * Show the form for editing the recurring invoice.
     */
    public function edit(RecurringInvoice $recurringInvoice): Response
    {
        $recurringInvoice->load(['lines', 'company:id,name', 'contact']);

        return Inertia::render('recurring-invoices/Edit', [
            'profile' => RecurringInvoiceResource::make($recurringInvoice),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the recurring invoice. Invoices created before are not changed.
     */
    public function update(RecurringInvoiceRequest $request, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        DB::transaction(function () use ($request, $recurringInvoice) {
            $recurringInvoice->fill($this->attributes($request));
            $recurringInvoice->applyFurtherTax($request->boolean('apply_further_tax'));
            $recurringInvoice->scheduleNext();
            $recurringInvoice->save();

            $recurringInvoice->replaceLines($request->validated('lines'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring invoice updated.')]);

        return to_route('recurring-invoices.show', $recurringInvoice);
    }

    /**
     * Delete the recurring invoice (its invoices stay).
     */
    public function destroy(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $recurringInvoice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring invoice deleted.')]);

        return to_route('recurring-invoices.index');
    }

    /**
     * Create the next invoice now instead of waiting for its date.
     */
    public function generate(RecurringInvoice $recurringInvoice, GenerateRecurringInvoice $generate): RedirectResponse
    {
        $invoice = $generate->handle($recurringInvoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => $invoice->number
            ? __('Invoice :number created.', ['number' => $invoice->number])
            : __('Draft invoice created.')]);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Pause or resume the recurring invoice. Periods missed while paused are skipped.
     */
    public function toggle(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $recurringInvoice->is_active = ! $recurringInvoice->is_active;

        if ($recurringInvoice->is_active) {
            $today = CarbonImmutable::today();

            while ($recurringInvoice->next_date !== null && $recurringInvoice->next_date->lessThan($today)) {
                $recurringInvoice->generated_count++;
                $recurringInvoice->scheduleNext();
            }
        }

        $recurringInvoice->save();
        $recurringInvoice->logActivity($recurringInvoice->is_active ? 'resumed' : 'paused', $recurringInvoice->is_active ? 'Resumed' : 'Paused');

        return back();
    }

    /**
     * Get the dates of the next few invoices.
     *
     * @return array<int, string>
     */
    protected function upcoming(RecurringInvoice $profile): array
    {
        if (! $profile->is_active || $profile->next_date === null) {
            return [];
        }

        $dates = [];

        for ($index = $profile->generated_count; count($dates) < 6; $index++) {
            $date = $profile->frequency->occurrence($profile->start_date, $index);

            if ($profile->end_date !== null && $date->greaterThan($profile->end_date)) {
                break;
            }

            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * Get the profile attributes from the request.
     *
     * @return array<string, mixed>
     */
    protected function attributes(RecurringInvoiceRequest $request): array
    {
        return [
            ...$request->safe()->except(['lines', 'apply_further_tax']),
            'exchange_rate' => $request->validated('exchange_rate') ?? 1,
            'discount_percent' => $request->validated('discount_percent') ?? 0,
            'auto_issue' => $request->boolean('auto_issue'),
            'auto_send' => $request->boolean('auto_issue') && $request->boolean('auto_send'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'taxRates' => TeamOptions::taxRates(),
            'departments' => TeamOptions::departments(),
            'currencies' => TeamOptions::currencies(),
            'paymentTerms' => TeamOptions::paymentTerms(),
            'frequencies' => RecurringFrequency::options(),
            'furtherTaxRate' => app(CurrentTeam::class)->team()->teamSettings()->furtherTaxRate(),
        ];
    }
}
