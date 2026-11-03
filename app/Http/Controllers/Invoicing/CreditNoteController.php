<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Invoicing\IssueCreditNote;
use App\Enums\CreditNoteStatus;
use App\Enums\EmailTemplateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\SendDocumentRequest;
use App\Http\Requests\Invoicing\CreditNoteRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CreditNoteResource;
use App\Models\CreditNote;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\DocumentPlaceholders;
use App\Support\TeamOptions;
use App\Support\Timeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CreditNoteController extends Controller
{
    /**
     * Display a listing of the credit notes.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => in_array($request->input('status'), ['draft', 'issued'], true) ? (string) $request->input('status') : null,
        ];

        $creditNotes = CreditNote::query()
            ->with(['invoice:id,number,date,total,amount_credited'])
            ->search($filters['search'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('credit-notes/Index', [
            'creditNotes' => CreditNoteResource::collection($creditNotes),
            'filters' => $filters,
        ]);
    }

    /**
     * Display the credit note.
     */
    public function show(Request $request, CreditNote $creditNote, DocumentMailer $mailer): Response
    {
        $creditNote->load(['lines', 'invoice', 'company:id,name', 'contact', 'department:id,name', 'creator:id,name']);

        return Inertia::render('credit-notes/Show', [
            'creditNote' => CreditNoteResource::make($creditNote),
            'timeline' => Timeline::for($creditNote),
            'attachments' => AttachmentResource::collection($creditNote->attachments()->with('user:id,name')->get()),
            'emailDraft' => $creditNote->isDraft() ? null : $mailer->draft($creditNote, EmailTemplateType::CreditNote, DocumentPlaceholders::for($creditNote, $request->user())),
        ]);
    }

    /**
     * Show the form for editing the (draft) credit note.
     */
    public function edit(CreditNote $creditNote): Response
    {
        $this->ensureDraft($creditNote);

        $creditNote->load(['lines', 'invoice', 'company:id,name', 'contact']);

        return Inertia::render('credit-notes/Edit', [
            'creditNote' => CreditNoteResource::make($creditNote),
            'taxRates' => TeamOptions::taxRates(),
        ]);
    }

    /**
     * Update the (draft) credit note, and issue it when asked.
     */
    public function update(CreditNoteRequest $request, CreditNote $creditNote, IssueCreditNote $issue): RedirectResponse
    {
        $this->ensureDraft($creditNote);

        DB::transaction(function () use ($request, $creditNote) {
            $creditNote->fill([
                ...$request->safe()->except(['lines']),
                'restock' => $request->boolean('restock'),
            ]);
            $creditNote->save();

            $creditNote->replaceLines($request->validated('lines'));
        });

        if ($request->boolean('issue_now')) {
            $creditNote = $issue->handle($creditNote);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit note :number issued.', ['number' => $creditNote->number])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit note saved.')]);
        }

        return to_route('credit-notes.show', $creditNote);
    }

    /**
     * Delete a draft credit note.
     */
    public function destroy(CreditNote $creditNote): RedirectResponse
    {
        $this->ensureDraft($creditNote);

        $invoiceId = $creditNote->invoice_id;
        $creditNote->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft credit note deleted.')]);

        return to_route('invoices.show', ['invoice' => $invoiceId]);
    }

    /**
     * Issue the draft credit note.
     */
    public function issue(CreditNote $creditNote, IssueCreditNote $issue): RedirectResponse
    {
        $creditNote = $issue->handle($creditNote);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit note :number issued.', ['number' => $creditNote->number])]);

        return to_route('credit-notes.show', $creditNote);
    }

    /**
     * Show or download the PDF.
     */
    public function pdf(Request $request, CreditNote $creditNote, DocumentPdf $pdf): HttpResponse
    {
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($creditNote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($creditNote).'"',
        ]);
    }

    /**
     * E-mail the issued credit note to the customer.
     */
    public function send(SendDocumentRequest $request, CreditNote $creditNote, DocumentMailer $mailer): RedirectResponse
    {
        if ($creditNote->isDraft()) {
            throw ValidationException::withMessages(['credit_note' => 'Issue the credit note first.']);
        }

        $sent = $mailer->send($creditNote, $request->user(), $request->message());

        if ($sent->status !== 'sent') {
            throw ValidationException::withMessages(['to' => 'The e-mail could not be sent: '.$sent->error]);
        }

        $creditNote->forceFill(['sent_at' => now()])->saveQuietly();
        $creditNote->logActivity('sent', "Sent to {$sent->to}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit note sent to :to.', ['to' => $sent->to])]);

        return back();
    }

    /**
     * Only draft credit notes can be changed.
     */
    protected function ensureDraft(CreditNote $creditNote): void
    {
        if ($creditNote->status !== CreditNoteStatus::Draft) {
            throw ValidationException::withMessages(['credit_note' => 'Issued credit notes cannot be changed.']);
        }
    }
}
