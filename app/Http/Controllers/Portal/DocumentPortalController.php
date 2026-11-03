<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\OpensActiveTeams;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\InvoiceResource;
use App\Models\ActivityLog;
use App\Models\CreditNote;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\SalesDocument;
use App\Support\Documents\DocumentPdf;
use App\Support\Documents\Templates\DocumentOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The customer's view of an invoice or credit note, opened through its secret link.
 */
class DocumentPortalController extends Controller
{
    use OpensActiveTeams;

    /**
     * Show the invoice to the customer.
     */
    public function invoice(Request $request, string $token, DocumentPdf $pdf): Response
    {
        $invoice = $this->findInvoice($token);
        $this->recordView($invoice, $request);

        return $this->render($invoice, InvoiceResource::make($invoice)->resolve($request), $pdf, [
            'kind' => 'invoice',
            'pdfUrl' => route('public.invoices.pdf', ['token' => $token]),
            'balance' => [
                'paid' => $invoice->amount_paid,
                'credited' => $invoice->amount_credited,
                'balance_due' => $invoice->balanceDue(),
            ],
        ]);
    }

    /**
     * Download the invoice as PDF.
     */
    public function invoicePdf(string $token, DocumentPdf $pdf): HttpResponse
    {
        return $this->pdfResponse($this->findInvoice($token), $pdf);
    }

    /**
     * Show the credit note to the customer.
     */
    public function creditNote(Request $request, string $token, DocumentPdf $pdf): Response
    {
        $creditNote = $this->findCreditNote($token);
        $this->recordView($creditNote, $request);

        return $this->render($creditNote, CreditNoteResource::make($creditNote)->resolve($request), $pdf, [
            'kind' => 'credit_note',
            'pdfUrl' => route('public.credit-notes.pdf', ['token' => $token]),
            'balance' => null,
        ]);
    }

    /**
     * Download the credit note as PDF.
     */
    public function creditNotePdf(string $token, DocumentPdf $pdf): HttpResponse
    {
        return $this->pdfResponse($this->findCreditNote($token), $pdf);
    }

    /**
     * Render the public document page.
     *
     * @param  array<string, mixed>  $resource  the document as sent to the page
     * @param  array<string, mixed>  $props  extra page props
     */
    protected function render(SalesDocument $document, array $resource, DocumentPdf $pdf, array $props): Response
    {
        // The same presentation as the PDF, so the page shows what the company chose to show.
        $data = $pdf->data($document);

        return Inertia::render('public/Document', [
            'document' => $resource,
            'title' => $data['title'],
            'meta' => $data['view']['meta'],
            'seller' => self::seller($data),
            'amountInWords' => $data['amountInWords'],
            'presentation' => $data['view'],
            'payment' => $data['payment']['lines'] ?? null,
            ...$props,
        ]);
    }

    /**
     * Get the company details for a public document page, as the company chose to show them.
     *
     * @param  array<string, mixed>  $data  the PDF data (see DocumentPdf::data())
     * @return array<string, mixed>
     */
    public static function seller(array $data): array
    {
        /** @var Department|null $department */
        $department = $data['department'];
        /** @var DocumentOptions $options */
        $options = $data['options'];

        return [
            'name' => $department?->displayName(),
            'address' => $department?->addressLines() ?? [],
            'ntn' => $options->companyTaxNumbers ? $department?->ntn : null,
            'strn' => $options->companyTaxNumbers ? $department?->strn : null,
            'email' => $department?->email,
            'phone' => $department?->phone,
            'logo' => $data['logo'],
        ];
    }

    /**
     * Stream a PDF inline.
     */
    protected function pdfResponse(SalesDocument $document, DocumentPdf $pdf): HttpResponse
    {
        return response($pdf->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($document).'"',
        ]);
    }

    /**
     * Find an issued invoice and scope the request to its team.
     */
    protected function findInvoice(string $token): Invoice
    {
        $invoice = Invoice::query()
            ->withoutGlobalScopes()
            ->with(['lines', 'department', 'team'])
            ->where('public_token', $token)
            ->where('status', '!=', InvoiceStatus::Draft)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->openTeam($invoice->team);

        return $invoice;
    }

    /**
     * Find an issued credit note and scope the request to its team.
     */
    protected function findCreditNote(string $token): CreditNote
    {
        $creditNote = CreditNote::query()
            ->withoutGlobalScopes()
            ->with(['lines', 'department', 'team', 'invoice'])
            ->where('public_token', $token)
            ->where('status', CreditNoteStatus::Issued)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->openTeam($creditNote->team);

        return $creditNote;
    }

    /**
     * Log that the customer opened the document (at most once an hour).
     */
    protected function recordView(SalesDocument $document, Request $request): void
    {
        if ($request->user() !== null) {
            return;
        }

        $recent = ActivityLog::query()
            ->where('subject_type', $document->getMorphClass())
            ->where('subject_id', $document->id)
            ->where('event', 'viewed')
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if (! $recent) {
            $document->logActivity('viewed', 'Opened by the customer');
        }
    }
}
