<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealPhase;
use App\Models\DealPipeline;
use App\Support\TeamRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales pipelines and their phases.
 */
class PipelineController extends Controller
{
    /**
     * List the pipelines with their phases.
     */
    public function index(): Response
    {
        $dealCounts = Deal::query()
            ->selectRaw('phase_id, count(*) as aggregate')
            ->groupBy('phase_id')
            ->pluck('aggregate', 'phase_id');

        return Inertia::render('company-settings/Pipelines', [
            'pipelines' => DealPipeline::query()
                ->with('phases')
                ->orderByDesc('is_default')
                ->orderBy('sort')
                ->get()
                ->map(fn (DealPipeline $pipeline) => [
                    'id' => $pipeline->id,
                    'name' => $pipeline->name,
                    'is_default' => $pipeline->is_default,
                    'phases' => $pipeline->phases->map(fn (DealPhase $phase) => [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'probability' => $phase->probability,
                        'deals_count' => (int) ($dealCounts[$phase->id] ?? 0),
                    ])->values(),
                ]),
        ]);
    }

    /**
     * Create a pipeline with a first phase.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        DB::transaction(function () use ($validated) {
            $pipeline = DealPipeline::create([
                'name' => $validated['name'],
                'is_default' => ! DealPipeline::query()->exists(),
                'sort' => (int) DealPipeline::query()->max('sort') + 1,
            ]);

            DealPhase::create(['pipeline_id' => $pipeline->id, 'name' => 'New', 'probability' => 10, 'sort' => 0]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pipeline added.')]);

        return back();
    }

    /**
     * Rename a pipeline or make it the default.
     */
    public function update(Request $request, DealPipeline $pipeline): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        DB::transaction(function () use ($pipeline, $validated) {
            $pipeline->update($validated);

            if ($pipeline->is_default) {
                DealPipeline::query()->whereKeyNot($pipeline->id)->update(['is_default' => false]);
            }
        });

        return back();
    }

    /**
     * Delete a pipeline that has no deals.
     */
    public function destroy(DealPipeline $pipeline): RedirectResponse
    {
        if ($pipeline->deals()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['pipeline' => 'Move or delete the deals of this pipeline first.']);
        }

        if (DealPipeline::query()->count() <= 1) {
            throw ValidationException::withMessages(['pipeline' => 'You need at least one pipeline.']);
        }

        DB::transaction(function () use ($pipeline) {
            $wasDefault = $pipeline->is_default;
            $pipeline->delete();

            if ($wasDefault) {
                DealPipeline::query()->orderBy('sort')->first()?->update(['is_default' => true]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pipeline deleted.')]);

        return back();
    }

    /**
     * Add a phase to a pipeline.
     */
    public function storePhase(Request $request, DealPipeline $pipeline): RedirectResponse
    {
        $validated = $this->validatePhase($request);

        DealPhase::create([
            ...$validated,
            'pipeline_id' => $pipeline->id,
            'sort' => (int) $pipeline->phases()->max('sort') + 1,
        ]);

        return back();
    }

    /**
     * Update a phase.
     */
    public function updatePhase(Request $request, DealPhase $phase): RedirectResponse
    {
        $phase->update($this->validatePhase($request));

        return back();
    }

    /**
     * Delete a phase that has no deals.
     */
    public function destroyPhase(DealPhase $phase): RedirectResponse
    {
        if ($phase->deals()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['phase' => 'Move the deals in this phase to another phase first.']);
        }

        if ($phase->pipeline->phases()->count() <= 1) {
            throw ValidationException::withMessages(['phase' => 'A pipeline needs at least one phase.']);
        }

        $phase->delete();

        return back();
    }

    /**
     * Save the order of a pipeline's phases.
     */
    public function orderPhases(Request $request, DealPipeline $pipeline): RedirectResponse
    {
        $validated = $request->validate([
            'phase_ids' => ['required', 'array'],
            'phase_ids.*' => ['integer', TeamRule::exists('deal_phases')->where('pipeline_id', $pipeline->id)],
        ]);

        foreach ($validated['phase_ids'] as $sort => $id) {
            DealPhase::query()->whereKey($id)->update(['sort' => $sort]);
        }

        return back();
    }

    /**
     * Validate a phase.
     *
     * @return array{name: string, probability: int}
     */
    protected function validatePhase(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'probability' => ['required', 'integer', 'between:0,100'],
        ]);

        return ['name' => $validated['name'], 'probability' => (int) $validated['probability']];
    }
}
