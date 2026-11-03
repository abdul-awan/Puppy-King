<?php

namespace App\Http\Controllers\Projects;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectMaterialRequest;
use App\Models\Project;
use App\Models\ProjectMaterial;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ProjectMaterialController extends Controller
{
    /**
     * Record material used on the project.
     */
    public function store(ProjectMaterialRequest $request, Project $project): RedirectResponse
    {
        $material = new ProjectMaterial([...$request->validated(), 'created_by' => $request->user()->id]);
        $material->team_id = $project->team_id;
        $material->project_id = $project->id;
        $material->save();

        return back();
    }

    /**
     * Update material that was not invoiced yet.
     */
    public function update(ProjectMaterialRequest $request, ProjectMaterial $material): RedirectResponse
    {
        $this->ensureNotInvoiced($material);

        $material->update($request->validated());

        return back();
    }

    /**
     * Remove material that was not invoiced yet.
     */
    public function destroy(ProjectMaterial $material): RedirectResponse
    {
        $this->ensureNotInvoiced($material);

        $material->delete();

        return back();
    }

    /**
     * Invoiced material is part of an invoice and cannot change, nor can material of a completed work order.
     */
    protected function ensureNotInvoiced(ProjectMaterial $material): void
    {
        if ($material->invoice_id !== null) {
            throw ValidationException::withMessages(['material' => 'This material is already on an invoice.']);
        }

        if ($material->work_order_id !== null
            && WorkOrder::query()->whereKey($material->work_order_id)->where('status', '!=', WorkOrderStatus::Open)->exists()) {
            throw ValidationException::withMessages(['material' => 'This material is part of a completed work order.']);
        }
    }
}
