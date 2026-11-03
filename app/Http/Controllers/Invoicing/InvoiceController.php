<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\SendPaymentReminder;
use App\Enums\CreditNoteStatus;
use App\Enums\EmailTemplateType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\SendDocumentRequest;
use App\Http\Requests\Invoicing\InvoiceRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CreditNote;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentTerm;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\DocumentPlaceholders;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * The status filters of the invoice list.
     */
    private const STATUS_FILTERS = ['draft', 'open', 'overdue', 'partial', 'paid', 'credited'];

    /**
     * Display a listing of the invoices.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $invoices = $this->filteredQuery($filters)
            ->with(['company:id,name', 'contact:id,first_name,last_name'])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [InvoiceStatus::Draft->value])
            ->latest('date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $open = Invoice::query()->where('status', InvoiceStatus::Open);
        $balance = 'SUM((total - amount_paid - amount_credited) * exchange_rate)';

        return Inertia::render('invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters' => $filters,
            'periods' => DateRange::options(),
            'summary' => [
                'outstanding_count' => (clone $open)->count(),
                'outstanding_total' => (string) ((clone $open)->selectRaw("{$balance} as balance")->value('balance') ?? 0),
                'overdue_count' => Invoice::query()->overdue()->count(),
                'overdue_total' => (string) (Invoice::query()->overdue()->selectRaw("{$balance} as balance")->value('balance') ?? 0),
                'received_this_month' => (string) (InvoicePayment::query()
                    ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
                    ->whereBetween('invoice_payments.date', [today()->startOfMonth(), today()->endOfMonth()])
                    ->selectRaw('SUM((invoice_payments.amount + invoice_payments.income_tax_withheld + invoice_payments.sales_tax_withheld) * invoices.exchange_rate) as received')
                    ->value('received') ?? 0),
                'draft_count' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();
        $settings = $team->teamSettings();

        $deal = $request->integer('deal') ? Deal::query()->with(['company', 'contact'])->find($request->integer('deal')) : null;
        $company = $deal->company ?? ($request->integer('company') ? Company::query()->find($request->integer('company')) : null);
        $contact = $deal->contact ?? ($request->integer('contact') ? Contact::query()->find($request->integer('contact')) : null);

        $term = ($company?->payment_term_id ? PaymentTerm::query()->find($company->payment_term_id) : null)
            ?? PaymentTerm::query()->where('is_default', true)->first();
        $today = CarbonImmutable::today();

        return Inertia::render('invoices/Create', [
            'prefill' => [
                'deal' => $deal?->only(['id', 'title']),
                'company' => $company?->only(['id', 'name']),
                'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
                'apply_further_tax' => $company !== null && ! $company->sales_tax_registered,
                'title' => $deal?->title,
                'currency' => $deal->currency ?? $company->currency ?? $settings->currency(),
                'date' => $today->toDateString(),
                'payment_term_id' => $term?->id,
                'due_date' => $term?->dueDate($today)->toDateString(),
                'department_id' => $deal->department_id ?? $team->defaultDepartment()?->id,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created (draft) invoice, and issue it straight away when asked.
     */
    public function store(InvoiceRequest $request, IssueInvoice $issue): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $invoice = DB::transaction(function () use ($request, $team) {
            $invoice = new Invoice([...$this->attributes($request), 'created_by' => $request->user()->id]);
            $invoice->team_id = $team->id;
            $invoice->status = InvoiceStatus::Draft;
            $invoice->department_id ??= $team->defaultDepartment()?->id;
            $this->prepare($invoice, $request);
            $invoice->save();

            $invoice->replaceLines($request->validated('lines'));

            return $invoice;
        });

        if ($request->boolean('issue_now')) {
            $invoice = $issue->handle($invoice);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number issued.', ['number' => $invoice->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft invoice saved.')]);
        }

        return to_route('invoices.show', $invoice);
    }

    /**
     * Display the invoice.
     */
    public function show(Request $request, Invoice $invoice, DocumentMailer $mailer, SendPaymentReminder $reminder): Response
    {
        $invoice->load([
            'lines', 'company:id,name', 'contact', 'deal:id,title', 'quotation:id,number', 'paymentTerm:id,name',
            'recurringInvoice:id,title', 'department:id,name', 'creator:id,name', 'payments.user:id,name',
            'creditNotes' => fn ($query) => $query->latest('id'),
        ]);

        return Inertia::render('invoices/Show', [
            'invoice' => InvoiceResource::make($invoice),
            'timeline' => Timeline::for($invoice),
            'attachments' => AttachmentResource::collection($invoice->attachments()->with('user:id,name')->get()),
            'emailDraft' => $invoice->isDraft() ? null : $mailer->draft($invoice, EmailTemplateType::Invoice, DocumentPlaceholders::for($invoice, $request->user())),
            'reminderDraft' => $invoice->status === InvoiceStatus::Open ? $reminder->draft($invoice, $request->user()) : null,
            'paymentMethods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Show the form for editing the (draft) invoice.
     */
    public function edit(Invoice $invoice): Response
    {
        $this->ensureDraft($invoice);

        $invoice->load(['lines', 'company:id,name', 'contact', 'deal:id,title', 'quotation:id,number']);

        return Inertia::render('invoices/Edit', [
            'invoice' => InvoiceResource::make($invoice),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the (draft) invoice, and issue it when asked.
     */
    public function update(InvoiceRequest $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $this->ensureDraft($invoice);

        DB::transaction(function () use ($request, $invoice) {
            $invoice->fill($this->attributes($request));
            $this->prepare($invoice, $request);
            $invoice->save();

            $invoice->replaceLines($request->validated('lines'));
        });

        if ($request->boolean('issue_now')) {
            $invoice = $issue->handle($invoice);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number issued.', ['number' => $invoice->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice updated.')]);
        }

        return to_route('invoices.show', $invoice);
    }

    /**
     * Delete a draft invoice. Issued invoices are corrected with a credit note instead.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Issued invoices cannot be deleted. Create a credit note to cancel it.']);
        }

        $invoice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft invoice deleted.')]);

        return to_route('invoices.index');
    }

    /**
     * Issue the draft invoice.
     */
    public function issue(Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $invoice = $issue->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number issued.', ['number' => $invoice->number])]);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Show or download the PDF.
     */
    public function pdf(Request $request, Invoice $invoice, DocumentPdf $pdf): HttpResponse
    {
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($invoice).'"',
        ]);
    }

    /**
     * E-mail the issued invoice to the customer.
     */
    public function send(SendDocumentRequest $request, Invoice $invoice, DocumentMailer $mailer): RedirectResponse
    {
        $this->ensureIssued($invoice);

        $sent = $mailer->send($invoice, $request->user(), $request->message());

        if ($sent->status !== 'sent') {
            throw ValidationException::withMessages(['to' => 'The e-mail could not be sent: '.$sent->error]);
        }

        $invoice->forceFill(['sent_at' => now()])->saveQuietly();
        $invoice->logActivity('sent', "Sent to {$sent->to}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice sent to :to.', ['to' => $sent->to])]);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Record that the issued invoice was handed over without e-mailing it.
     */
    public function markSent(Invoice $invoice): RedirectResponse
    {
        $this->ensureIssued($invoice);

        $invoice->forceFill(['sent_at' => now()])->saveQuietly();
        $invoice->logActivity('sent', 'Marked as sent');

        return back();
    }

    /**
     * E-mail a payment reminder for the open invoice.
     */
    public function remind(SendDocumentRequest $request, Invoice $invoice, SendPaymentReminder $reminder): RedirectResponse
    {
        if ($invoice->status !== InvoiceStatus::Open) {
            throw ValidationException::withMessages(['invoice' => 'Only open invoices can be reminded.']);
        }

        $sent = $reminder->send($invoice, $request->user(), $request->message());

        if ($sent->status !== 'sent') {
            throw ValidationException::withMessages(['to' => 'The e-mail could not be sent: '.$sent->error]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder sent to :to.', ['to' => $sent->to])]);

        return back();
    }

    /**
     * Store the invoice number FBR gave the (issued) invoice.
     */
    public function fbrNumber(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate(['fbr_invoice_number' => ['nullable', 'string', 'max:100']]);

        $invoice->update(['fbr_invoice_number' => $validated['fbr_invoice_number'] ?? null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('FBR invoice number saved.')]);

        return back();
    }

    /**
     * Copy the invoice into a new draft.
     */
    public function duplicate(Request $request, Invoice $invoice): RedirectResponse
    {
        $copy = DB::transaction(function () use ($request, $invoice) {
            $today = CarbonImmutable::today();

            $copy = $invoice->replicate([
                'number', 'public_token', 'status', 'issued_at', 'sent_at', 'paid_at', 'amount_paid', 'amount_credited',
                'reminder_count', 'last_reminder_at', 'fbr_invoice_number', 'quotation_id', 'recurring_invoice_id', 'created_by',
            ]);
            $copy->status = InvoiceStatus::Draft;
            $copy->created_by = $request->user()->id;
            $copy->date = $today;
            $copy->due_date = $invoice->paymentTerm?->dueDate($today);
            $copy->save();

            $copy->replaceLines($invoice->linesForCopy());

            return $copy;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A draft copy was created.')]);

        return to_route('invoices.edit', $copy);
    }

    /**
     * Start a credit note for the issued invoice (all lines, to be edited for a partial credit).
     */
    public function credit(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->ensureIssued($invoice);

        if ((float) $invoice->creditableAmount() <= 0) {
            throw ValidationException::withMessages(['invoice' => 'This invoice has already been credited in full.']);
        }

        $creditNote = DB::transaction(function () use ($request, $invoice) {
            $creditNote = new CreditNote([
                'invoice_id' => $invoice->id,
                'department_id' => $invoice->department_id,
                'title' => 'Credit for invoice '.$invoice->number,
                'company_id' => $invoice->company_id,
                'contact_id' => $invoice->contact_id,
                'date' => CarbonImmutable::today(),
                'currency' => $invoice->currency,
                'exchange_rate' => $invoice->exchange_rate,
                'discount_percent' => $invoice->discount_percent,
                'created_by' => $request->user()->id,
            ]);
            $creditNote->team_id = $invoice->team_id;
            $creditNote->status = CreditNoteStatus::Draft;
            $creditNote->further_tax_rate = $invoice->further_tax_rate;
            $creditNote->refreshCustomerSnapshot();
            $creditNote->save();

            $creditNote->replaceLines($invoice->linesForCopy());

            return $creditNote;
        });

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Adjust the lines to what you are crediting, then issue the credit note.')]);

        return to_route('credit-notes.edit', $creditNote);
    }

    /**
     * Download the (filtered) invoices as a CSV file, e.g. for the accountant or a sales tax return.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($this->filters($request))
            ->where('status', '!=', InvoiceStatus::Draft)
            ->orderBy('date')
            ->orderBy('number');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Invoice no.', 'Date', 'Due date', 'Customer', 'NTN', 'STRN', 'CNIC', 'Registered', 'Currency',
                'Value excl. tax', 'Sales tax', 'Further tax', 'Total', 'Paid', 'Credited', 'Balance', 'Status', 'FBR invoice no.',
            ], ',', '"', '');

            $query->chunk(500, function (Collection $invoices) use ($out) {
                foreach ($invoices as $invoice) {
                    /** @var Invoice $invoice */
                    fputcsv($out, [
                        $invoice->number,
                        $invoice->date->toDateString(),
                        $invoice->due_date?->toDateString(),
                        $invoice->customer_name,
                        $invoice->customer_ntn,
                        $invoice->customer_strn,
                        $invoice->customer_cnic,
                        $invoice->customer_registered ? 'Yes' : 'No',
                        $invoice->currency,
                        number_format((float) $invoice->subtotal - (float) $invoice->discount_amount, 2, '.', ''),
                        $invoice->tax_total,
                        $invoice->further_tax_amount,
                        $invoice->total,
                        $invoice->amount_paid,
                        $invoice->amount_credited,
                        $invoice->balanceDue(),
                        $invoice->displayStatus()['label'],
                        $invoice->fbr_invoice_number,
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'invoices-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Read the list filters from the request.
     *
     * @return array{search: string, status: string|null, company: int|null, period: string|null, from: string|null, to: string|null}
     */
    protected function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($request->input('status'), self::STATUS_FILTERS, true) ? (string) $request->input('status') : null,
            'company' => $request->integer('company') ?: null,
            ...DateRange::filters($request),
        ];
    }

    /**
     * Get the invoices matching the filters.
     *
     * @param  array{search: string, status: string|null, company: int|null, period: string|null, from: string|null, to: string|null}  $filters
     * @return Builder<Invoice>
     */
    protected function filteredQuery(array $filters): Builder
    {
        $range = DateRange::resolve($filters);

        return Invoice::query()
            ->search($filters['search'])
            ->when($filters['status'], fn (Builder $query, string $status) => match ($status) {
                'overdue' => $query->overdue(),
                'partial' => $query->where('status', InvoiceStatus::Open)->where('amount_paid', '>', 0),
                default => $query->where('status', $status),
            })
            ->when($filters['company'], fn (Builder $query, int $company) => $query->where('company_id', $company))
            ->when($range, fn (Builder $query, array $range) => DateRange::apply($query, 'date', $range));
    }

    /**
     * Get the document attributes from the request.
     *
     * @return array<string, mixed>
     */
    protected function attributes(InvoiceRequest $request): array
    {
        return [
            ...$request->safe()->except(['lines', 'apply_further_tax']),
            'exchange_rate' => $request->validated('exchange_rate') ?? 1,
            'discount_percent' => $request->validated('discount_percent') ?? 0,
        ];
    }

    /**
     * Set the further tax, the customer snapshot and (if empty) the due date.
     */
    protected function prepare(Invoice $invoice, InvoiceRequest $request): void
    {
        $invoice->applyFurtherTax($request->boolean('apply_further_tax'));
        $invoice->refreshCustomerSnapshot();

        if ($invoice->due_date === null && $invoice->payment_term_id) {
            $invoice->due_date = PaymentTerm::query()->find($invoice->payment_term_id)?->dueDate($invoice->date);
        }
    }

    /**
     * Only draft invoices can be changed.
     */
    protected function ensureDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Issued invoices cannot be changed. Create a credit note to correct it.']);
        }
    }

    /**
     * The action needs an issued invoice.
     */
    protected function ensureIssued(Invoice $invoice): void
    {
        if ($invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => 'Issue the invoice first.']);
        }
    }

    /**
     * Get the option lists for the create and edit forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $team = app(CurrentTeam::class)->team();

        return [
            'taxRates' => TeamOptions::taxRates(),
            'departments' => TeamOptions::departments(),
            'currencies' => TeamOptions::currencies(),
            'paymentTerms' => TeamOptions::paymentTerms(),
            'furtherTaxRate' => $team->teamSettings()->furtherTaxRate(),
        ];
    }
}
