<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Documents\AnswerQuotation;
use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\OpensActiveTeams;
use App\Http\Resources\QuotationResource;
use App\Models\ActivityLog;
use App\Models\Quotation;
use App\Rules\PngDataUri;
use App\Support\Documents\DocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The customer's view of a quotation, opened through its secret link.
 */
class QuotationPortalController extends Controller
{
    use OpensActiveTeams;

    /**
     * Show the quotation to the customer.
     */
    public function show(Request $request, string $token, DocumentPdf $pdf): Response
    {
        $quotation = $this->find($token);
        $this->recordView($quotation, $request);

        // The same presentation as the PDF, so the page shows what the company chose to show.
        $data = $pdf->data($quotation);

        return Inertia::render('public/Quotation', [
            'quotation' => QuotationResource::make($quotation),
            'seller' => DocumentPortalController::seller($data),
            'amountInWords' => $data['amountInWords'],
            'presentation' => $data['view'],
            'canAnswer' => $quotation->canBeAnswered(),
            'urls' => [
                'pdf' => route('public.quotations.pdf', ['token' => $token]),
                'accept' => route('public.quotations.accept', ['token' => $token]),
                'decline' => route('public.quotations.decline', ['token' => $token]),
            ],
        ]);
    }

    /**
     * Download the quotation as PDF.
     */
    public function pdf(string $token, DocumentPdf $pdf): HttpResponse
    {
        $quotation = $this->find($token);

        return response($pdf->render($quotation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($quotation).'"',
        ]);
    }

    /**
     * Accept the quotation with a name and signature.
     */
    public function accept(Request $request, string $token, AnswerQuotation $answer): RedirectResponse
    {
        $quotation = $this->find($token);
        $this->ensureAnswerable($quotation);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'signature' => ['required', 'string', 'max:700000', new PngDataUri],
            'agree' => ['accepted'],
        ], [
            'signature.required' => 'Please sign in the box.',
            'agree.accepted' => 'Please confirm that you accept the quotation.',
        ]);

        $answer->accept($quotation, $validated['name'], $validated['signature'], $request->ip(), online: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you! The quotation has been accepted.')]);

        return back();
    }

    /**
     * Decline the quotation.
     */
    public function decline(Request $request, string $token, AnswerQuotation $answer): RedirectResponse
    {
        $quotation = $this->find($token);
        $this->ensureAnswerable($quotation);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        $answer->decline($quotation, $validated['reason'] ?? null, online: true);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('The quotation has been declined. Thank you for letting us know.')]);

        return back();
    }

    /**
     * Find a quotation that was sent to the customer and scope the request to its team.
     */
    protected function find(string $token): Quotation
    {
        $quotation = Quotation::query()
            ->withoutGlobalScopes()
            ->with(['lines', 'department', 'team'])
            ->where('public_token', $token)
            ->where('status', '!=', QuotationStatus::Draft)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->openTeam($quotation->team);

        return $quotation;
    }

    /**
     * Only sent, unexpired quotations can be answered.
     */
    protected function ensureAnswerable(Quotation $quotation): void
    {
        if (! $quotation->canBeAnswered()) {
            throw ValidationException::withMessages(['quotation' => 'This quotation can no longer be answered online.']);
        }
    }

    /**
     * Log that the customer opened the quotation (at most once an hour).
     */
    protected function recordView(Quotation $quotation, Request $request): void
    {
        if ($request->user() !== null) {
            return;
        }

        $recent = ActivityLog::query()
            ->where('subject_type', $quotation->getMorphClass())
            ->where('subject_id', $quotation->id)
            ->where('event', 'viewed')
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if (! $recent) {
            $quotation->logActivity('viewed', 'Opened by the customer');
        }
    }
}
