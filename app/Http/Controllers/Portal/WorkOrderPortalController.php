<?php

namespace App\Http\Controllers\Portal;

use App\Actions\WorkOrders\SignWorkOrder;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\OpensActiveTeams;
use App\Models\ActivityLog;
use App\Models\ProjectMaterial;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use App\Rules\PngDataUri;
use App\Support\Documents\WorkOrderPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The customer's view of a work order, opened through its secret link: read it,
 * download it and sign it online. Internal notes are never shown here.
 */
class WorkOrderPortalController extends Controller
{
    use OpensActiveTeams;

    /**
     * Show the work order to the customer.
     */
    public function show(Request $request, string $token, WorkOrderPdf $pdf): Response
    {
        $workOrder = $this->find($token);
        $this->recordView($workOrder, $request);

        $data = $pdf->data($workOrder);
        $department = $data['department'];
        $prices = $workOrder->show_prices;
        $totals = $workOrder->totals();

        return Inertia::render('public/WorkOrder', [
            'workOrder' => [
                'number' => $workOrder->number,
                'title' => $workOrder->title,
                'date' => $workOrder->date->toDateString(),
                'status_label' => $workOrder->displayStatus()['label'],
                'location' => $workOrder->location,
                'description' => $workOrder->description,
                'technician' => $workOrder->assignee?->name,
                'customer' => $data['customer'],
                'show_prices' => $prices,
                'is_signed' => $workOrder->isSigned(),
                'signed_by_name' => $workOrder->signed_by_name,
                'signed_at' => $workOrder->signed_at?->toIso8601String(),
                'signature' => $workOrder->signature,
                'customer_remarks' => $workOrder->customer_remarks,
            ],
            'entries' => $workOrder->timeEntries->map(fn (TimeEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->date->toDateString(),
                'user' => $entry->user?->name,
                'work' => $entry->workType->name ?? 'Work',
                'description' => $entry->description,
                'minutes' => $entry->duration_minutes,
                'is_billable' => $entry->is_billable,
                'rate' => $prices ? $entry->hourly_rate : null,
                'value' => $prices ? $entry->value() : null,
            ])->values(),
            'materials' => $workOrder->materials->map(fn (ProjectMaterial $material) => [
                'id' => $material->id,
                'description' => $material->description,
                'quantity' => $material->quantity,
                'unit' => $material->unit,
                'is_billable' => $material->is_billable,
                'unit_price' => $prices ? $material->unit_price : null,
                'value' => $prices ? $material->value() : null,
            ])->values(),
            'totals' => [
                'minutes' => $totals['minutes'],
                'time_value' => $prices ? $totals['time_value'] : null,
                'materials_value' => $prices ? $totals['materials_value'] : null,
                'total_value' => $prices ? $totals['total_value'] : null,
            ],
            'currency' => $data['currency'],
            'seller' => [
                'name' => $department?->displayName(),
                'address' => $department?->addressLines() ?? [],
                'ntn' => $department?->ntn,
                'strn' => $department?->strn,
                'email' => $department?->email,
                'phone' => $department?->phone,
                'logo' => $data['logo'],
            ],
            'canSign' => $workOrder->canBeSigned(),
            'urls' => [
                'pdf' => route('public.work-orders.pdf', ['token' => $token]),
                'sign' => route('public.work-orders.sign', ['token' => $token]),
            ],
        ]);
    }

    /**
     * Download the work order as PDF.
     */
    public function pdf(string $token, WorkOrderPdf $pdf): HttpResponse
    {
        $workOrder = $this->find($token);

        return response($pdf->render($workOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($workOrder).'"',
        ]);
    }

    /**
     * The customer signs the work order online.
     */
    public function sign(Request $request, string $token, SignWorkOrder $signer): RedirectResponse
    {
        $workOrder = $this->find($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'signature' => ['required', 'string', 'max:700000', new PngDataUri],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'agree' => ['accepted'],
        ], [
            'signature.required' => 'Please sign in the box.',
            'agree.accepted' => 'Please confirm that the work was carried out as described.',
        ]);

        $signer->sign($workOrder, $validated['name'], $validated['signature'], $validated['remarks'] ?? null, $request->ip(), online: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you! The work order has been signed.')]);

        return back();
    }

    /**
     * Find the work order by its secret link and scope the request to its team.
     */
    protected function find(string $token): WorkOrder
    {
        $workOrder = WorkOrder::query()
            ->withoutGlobalScopes()
            ->with(['department', 'team'])
            ->where('public_token', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->openTeam($workOrder->team);

        return $workOrder;
    }

    /**
     * Log that the customer opened the work order (at most once an hour).
     */
    protected function recordView(WorkOrder $workOrder, Request $request): void
    {
        if ($request->user() !== null) {
            return;
        }

        $recent = ActivityLog::query()
            ->where('subject_type', $workOrder->getMorphClass())
            ->where('subject_id', $workOrder->id)
            ->where('event', 'viewed')
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if (! $recent) {
            $workOrder->logActivity('viewed', 'Opened by the customer');
        }
    }
}
