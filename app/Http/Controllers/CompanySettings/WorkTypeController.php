<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\WorkType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkTypeController extends Controller
{
    /**
     * List the work types.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/WorkTypes', [
            'workTypes' => WorkType::query()
                ->orderBy('sort')
                ->orderBy('name')
                ->get(['id', 'name', 'hourly_rate', 'is_billable', 'is_active', 'sort']),
        ]);
    }

    /**
     * Store a new work type.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        WorkType::create([...$validated, 'sort' => $validated['sort'] ?? (int) WorkType::query()->max('sort') + 1]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work type added.')]);

        return back();
    }

    /**
     * Update a work type.
     */
    public function update(Request $request, WorkType $workType): RedirectResponse
    {
        $workType->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work type saved.')]);

        return back();
    }

    /**
     * Delete a work type.
     */
    public function destroy(WorkType $workType): RedirectResponse
    {
        $workType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work type deleted.')]);

        return back();
    }

    /**
     * Validate the work type input.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'is_billable' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
