<?php

namespace App\Http\Controllers\Documents;

use App\Actions\Documents\AnswerQuotation;
use App\Enums\DocumentType;
use App\Enums\EmailTemplateType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\QuotationRequest;
use App\Http\Requests\Documents\SendDocumentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\QuotationResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DocumentLine;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Quotation;
use App\Support\CurrentTeam;
use App\Support\DocumentNumberer;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\DocumentPlaceholders;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuotationController extends Controller
{
    /**
     * Display a listing of the quotations.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($request->input('status'), ['draft', 'sent', 'accepted', 'declined', 'expired'], true) ? (string) $request->input('status') : null,
            'company' => $request->integer('company') ?: null,
        ];

        $quotations = Quotation::query()
            ->with(['company:id,name', 'contact:id,first_name,last_name'])
            ->search($filters['search'])
            ->when(
                $filters['status'] === 'expired',
                fn ($query) => $query->where('status', QuotationStatus::Sent)->whereDate('valid_until', '<', today()),
                fn ($query) => $query->when($filters['status'], fn ($query, $status) => $query->where('status', $status)),
            )
            ->when($filters['company'], fn ($query, $company) => $query->where('company_id', $company))
            ->latest('date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $open = Quotation::query()
            ->where('status', QuotationStatus::Sent)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today()));

        return Inertia::render('quotations/Index', [
            'quotations' => QuotationResource::collection($quotations),
            'filters' => $filters,
            'summary' => [
                'open_count' => (clone $open)->count(),
                'open_total' => (string) (clone $open)->sum('total'),
                'accepted_this_month' => (string) Quotation::query()
                    ->where('status', QuotationStatus::Accepted)
                    ->where('accepted_at', '>=', now()->startOfMonth())
                    ->sum('total'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new quotation.
     */
    public function create(Request $request): Response
    {
        $team = app(CurrentTeam::class)->team();
        $settings = $team->teamSettings();

        $deal = $request->integer('deal') ? Deal::query()->with(['company', 'contact'])->find($request->integer('deal')) : null;
        $company = $deal->company ?? ($request->integer('company') ? Company::query()->find($request->integer('company')) : null);
        $contact = $deal->contact ?? ($request->integer('contact') ? Contact::query()->find($request->integer('contact')) : null);

        return Inertia::render('quotations/Create', [
            'prefill' => [
                'deal' => $deal?->only(['id', 'title']),
                'company' => $company?->only(['id', 'name']),
                'contact' => $contact ? ['id' => $contact->id, 'name' => $contact->full_name] : null,
                'apply_further_tax' => $company !== null && ! $company->sales_tax_registered,
                'title' => $deal?->title,
                'currency' => $deal->currency ?? $company->currency ?? $settings->currency(),
                'date' => now()->toDateString(),
                'valid_until' => now()->addDays((int) $settings->get('quotation_validity_days', 30))->toDateString(),
                'department_id' => $deal->department_id ?? $team->defaultDepartment()?->id,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Store a newly created quotation.
     */
    public function store(QuotationRequest $request, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $quotation = DB::transaction(function () use ($request, $numberer, $team) {
            $quotation = new Quotation([
                ...$this->attributes($request),
                'status' => QuotationStatus::Draft,
                'created_by' => $request->user()->id,
            ]);
            $quotation->team_id = $team->id;
            $quotation->department_id ??= $team->defaultDepartment()?->id;
            $quotation->applyFurtherTax($request->boolean('apply_further_tax'));
            $quotation->refreshCustomerSnapshot();
            $quotation->number = $numberer->next($team, DocumentType::Quotation, $quotation->date, $quotation->department);
            $quotation->save();

            $quotation->replaceLines($request->validated('lines'));

            return $quotation;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation :number created.', ['number' => $quotation->number])]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Display the quotation.
     */
    public function show(Request $request, Quotation $quotation, DocumentMailer $mailer): Response
    {
        $quotation->load(['lines', 'company:id,name', 'contact', 'deal:id,title', 'department:id,name', 'creator:id,name']);

        return Inertia::render('quotations/Show', [
            'quotation' => QuotationResource::make($quotation),
            'timeline' => Timeline::for($quotation),
            'attachments' => AttachmentResource::collection($quotation->attachments()->with('user:id,name')->get()),
            'emailDraft' => $mailer->draft($quotation, EmailTemplateType::Quotation, DocumentPlaceholders::for($quotation, $request->user())),
        ]);
    }

    /**
     * Show the form for editing the quotation.
     */
    public function edit(Quotation $quotation): Response
    {
        $this->ensureEditable($quotation);

        $quotation->load(['lines', 'company:id,name', 'contact', 'deal:id,title']);

        return Inertia::render('quotations/Edit', [
            'quotation' => QuotationResource::make($quotation),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the quotation.
     */
    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureEditable($quotation);

        DB::transaction(function () use ($request, $quotation) {
            $quotation->fill($this->attributes($request));
            $quotation->applyFurtherTax($request->boolean('apply_further_tax'));
            $quotation->refreshCustomerSnapshot();
            $quotation->save();

            $quotation->replaceLines($request->validated('lines'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation updated.')]);

        return to_route('quotations.show', $quotation);
    }

    /**
     * Delete the quotation.
     */
    public function destroy(Quotation $quotation): RedirectResponse
    {
        $quotation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation deleted.')]);

        return to_route('quotations.index');
    }

    /**
     * Show or download the PDF.
     */
    public function pdf(Request $request, Quotation $quotation, DocumentPdf $pdf): HttpResponse
    {
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($quotation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($quotation).'"',
        ]);
    }

    /**
     * E-mail the quotation to the customer.
     */
    public function send(SendDocumentRequest $request, Quotation $quotation, DocumentMailer $mailer): RedirectResponse
    {
        $sent = $mailer->send($quotation, $request->user(), $request->message());

        if ($sent->status !== 'sent') {
            throw ValidationException::withMessages(['to' => 'The e-mail could not be sent: '.$sent->error]);
        }

        if ($quotation->status === QuotationStatus::Draft) {
            $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()]);
            $quotation->saveQuietly();
        }

        $quotation->logActivity('sent', "Sent to {$sent->to}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation sent to :to.', ['to' => $sent->to])]);

        return back();
    }

    /**
     * Mark the quotation as sent without e-mailing it (e.g. handed over in person).
     */
    public function markSent(Quotation $quotation): RedirectResponse
    {
        if ($quotation->status === QuotationStatus::Draft) {
            $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()]);
            $quotation->saveQuietly();
            $quotation->logActivity('sent', 'Marked as sent');
        }

        return back();
    }

    /**
     * Record that the customer accepted the quotation.
     */
    public function accept(Quotation $quotation, AnswerQuotation $answer): RedirectResponse
    {
        $answer->accept($quotation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quotation marked as accepted.')]);

        return back();
    }

    /**
     * Record that the customer declined the quotation.
     */
    public function decline(Request $request, Quotation $quotation, AnswerQuotation $answer): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $answer->decline($quotation, $validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Quotation marked as declined.')]);

        return back();
    }

    /**
     * Put an accepted or declined quotation back to "sent".
     */
    public function reopen(Quotation $quotation): RedirectResponse
    {
        $quotation->forceFill([
            'status' => $quotation->sent_at ? QuotationStatus::Sent : QuotationStatus::Draft,
            'accepted_at' => null,
            'accepted_by_name' => null,
            'accepted_signature' => null,
            'accepted_ip' => null,
            'declined_at' => null,
            'decline_reason' => null,
        ]);
        $quotation->saveQuietly();
        $quotation->logActivity('reopened', 'Reopened');

        return back();
    }

    /**
     * Copy the quotation into a new draft.
     */
    public function duplicate(Request $request, Quotation $quotation, DocumentNumberer $numberer): RedirectResponse
    {
        $team = app(CurrentTeam::class)->team();

        $copy = DB::transaction(function () use ($request, $quotation, $numberer, $team) {
            $copy = $quotation->replicate([
                'number', 'public_token', 'status', 'sent_at', 'accepted_at', 'declined_at', 'accepted_by_name',
                'accepted_signature', 'accepted_ip', 'decline_reason', 'invoiced_at', 'created_by',
            ]);
            $copy->status = QuotationStatus::Draft;
            $copy->created_by = $request->user()->id;
            $copy->date = now();
            $copy->valid_until = now()->addDays((int) $team->teamSettings()->get('quotation_validity_days', 30));
            $copy->number = $numberer->next($team, DocumentType::Quotation, $copy->date, $quotation->department);
            $copy->save();

            $copy->replaceLines($quotation->lines()->get()->map(fn (DocumentLine $line) => $line->toPresentation())->all());

            return $copy;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Copy :number created.', ['number' => $copy->number])]);

        return to_route('quotations.show', $copy);
    }

    /**
     * Create a draft invoice with the quotation's customer and lines.
     */
    public function invoice(Request $request, Quotation $quotation): RedirectResponse
    {
        if (in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Declined], true)) {
            throw ValidationException::withMessages(['quotation' => 'Only sent or accepted quotations can be invoiced.']);
        }

        $invoice = DB::transaction(function () use ($request, $quotation) {
            $today = CarbonImmutable::today();
            $companyTermId = $quotation->company?->payment_term_id;
            $term = ($companyTermId !== null ? PaymentTerm::query()->find($companyTermId) : null)
                ?? PaymentTerm::query()->where('is_default', true)->first();

            $invoice = new Invoice([
                'department_id' => $quotation->department_id,
                'title' => $quotation->title,
                'company_id' => $quotation->company_id,
                'contact_id' => $quotation->contact_id,
                'deal_id' => $quotation->deal_id,
                'quotation_id' => $quotation->id,
                'payment_term_id' => $term?->id,
                'date' => $today,
                'due_date' => $term?->dueDate($today),
                'currency' => $quotation->currency,
                'exchange_rate' => $quotation->exchange_rate,
                'discount_percent' => $quotation->discount_percent,
                'introduction' => $quotation->introduction,
                'terms' => $quotation->terms,
                'created_by' => $request->user()->id,
            ]);
            $invoice->team_id = $quotation->team_id;
            $invoice->status = InvoiceStatus::Draft;
            $invoice->further_tax_rate = $quotation->further_tax_rate;
            $invoice->refreshCustomerSnapshot();
            $invoice->save();

            $invoice->replaceLines($quotation->linesForCopy());

            $quotation->forceFill(['invoiced_at' => now()])->saveQuietly();
            $quotation->logActivity('invoiced', 'Draft invoice created');

            return $invoice;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft invoice created from quotation :number.', ['number' => $quotation->number])]);

        return to_route('invoices.edit', $invoice);
    }

    /**
     * Get the document attributes from the request.
     *
     * @return array<string, mixed>
     */
    protected function attributes(QuotationRequest $request): array
    {
        return [
            ...$request->safe()->except(['lines', 'apply_further_tax']),
            'exchange_rate' => $request->validated('exchange_rate') ?? 1,
            'discount_percent' => $request->validated('discount_percent') ?? 0,
        ];
    }

    /**
     * Only drafts and sent quotations can be changed.
     */
    protected function ensureEditable(Quotation $quotation): void
    {
        if (! in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)) {
            throw ValidationException::withMessages(['quotation' => 'Reopen the quotation before changing it.']);
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
            'furtherTaxRate' => $team->teamSettings()->furtherTaxRate(),
        ];
    }
}
