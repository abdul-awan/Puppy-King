<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectMaterialRequest;
use App\Models\ProjectMaterial;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Materials used for a work order (booked on its project too, when it has one).
 */
class WorkOrderMaterialController extends Controller
{
    /**
     * Record material used for the work order.
     */
    public function store(ProjectMaterialRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->ensureOpen($workOrder);

        $material = new ProjectMaterial([...$request->validated(), 'created_by' => $request->user()->id]);
        $material->team_id = $workOrder->team_id;
        $material->work_order_id = $workOrder->id;
        $material->project_id = $workOrder->project_id;
        $material->save();

        return back();
    }

    /**
     * Update material of the (open) work order.
     */
    public function update(ProjectMaterialRequest $request, WorkOrder $workOrder, ProjectMaterial $material): RedirectResponse
    {
        $this->ensureEditable($workOrder, $material);

        $material->update($request->validated());

        return back();
    }

    /**
     * Remove material from the (open) work order.
     */
    public function destroy(WorkOrder $workOrder, ProjectMaterial $material): RedirectResponse
    {
        $this->ensureEditable($workOrder, $material);

        $material->delete();

        return back();
    }

    /**
     * Materials only change while the work order is open and they are not invoiced.
     */
    protected function ensureEditable(WorkOrder $workOrder, ProjectMaterial $material): void
    {
        abort_unless($material->work_order_id === $workOrder->id, 404);

        $this->ensureOpen($workOrder);

        if ($material->invoice_id !== null) {
            throw ValidationException::withMessages(['material' => 'This material is already on an invoice.']);
        }
    }

    /**
     * Completed work orders no longer change.
     */
    protected function ensureOpen(WorkOrder $workOrder): void
    {
        if (! $workOrder->isOpen()) {
            throw ValidationException::withMessages(['material' => 'This work order is completed. Reopen it to change the materials.']);
        }
    }
}
