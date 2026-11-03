<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\SalaryTaxTable;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The income tax slabs for salaried individuals per tax year (they change with every budget).
 */
class SalaryTaxTableController extends Controller
{
    /**
     * Add the slabs of a tax year.
     */
    public function store(Request $request): RedirectResponse
    {
        $table = SalaryTaxTable::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rates for tax year :year saved.', ['year' => $table->tax_year])]);

        return back();
    }

    /**
     * Change the slabs of a tax year (draft payrolls pick up the change when they are worked out again).
     */
    public function update(Request $request, SalaryTaxTable $salaryTaxTable): RedirectResponse
    {
        $salaryTaxTable->update($this->validated($request, $salaryTaxTable));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rates for tax year :year saved.', ['year' => $salaryTaxTable->tax_year])]);

        return back();
    }

    /**
     * Delete the slabs of a tax year.
     */
    public function destroy(SalaryTaxTable $salaryTaxTable): RedirectResponse
    {
        $salaryTaxTable->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rates for tax year :year deleted.', ['year' => $salaryTaxTable->tax_year])]);

        return back();
    }

    /**
     * Validate the slabs; they are stored from the lowest to the highest.
     *
     * @return array{tax_year: int, name: string, slabs: list<array{from: string, fixed: string, rate: string}>, surcharge_threshold: string|null, surcharge_percent: string, notes: string|null}
     */
    protected function validated(Request $request, ?SalaryTaxTable $table = null): array
    {
        $validated = $request->validate([
            'tax_year' => [
                'required', 'integer', 'between:2000,2100',
                Rule::unique('salary_tax_tables', 'tax_year')->where('team_id', app(CurrentTeam::class)->id())->ignore($table?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'slabs' => ['required', 'array', 'min:1', 'max:20'],
            'slabs.*.from' => ['required', 'numeric', 'between:0,99999999999', 'distinct'],
            'slabs.*.fixed' => ['required', 'numeric', 'between:0,99999999999'],
            'slabs.*.rate' => ['required', 'numeric', 'between:0,100'],
            'surcharge_threshold' => ['nullable', 'numeric', 'between:0,99999999999'],
            'surcharge_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'tax_year.unique' => 'There are already tax rates for this tax year.',
            'slabs.*.from.distinct' => 'Two slabs start at the same income.',
        ]);

        /** @var list<array{from: int|float|string, fixed: int|float|string, rate: int|float|string}> $slabs */
        $slabs = $validated['slabs'];
        $number = fn (int|float|string $value): string => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        $slabs = array_map(fn (array $slab) => [
            'from' => $number($slab['from']),
            'fixed' => $number($slab['fixed']),
            'rate' => $number($slab['rate']),
        ], $slabs);

        usort($slabs, fn (array $a, array $b) => (float) $a['from'] <=> (float) $b['from']);

        return [
            'tax_year' => (int) $validated['tax_year'],
            'name' => trim((string) $validated['name']),
            'slabs' => $slabs,
            'surcharge_threshold' => isset($validated['surcharge_threshold']) ? number_format((float) $validated['surcharge_threshold'], 2, '.', '') : null,
            'surcharge_percent' => number_format((float) ($validated['surcharge_percent'] ?? 0), 2, '.', ''),
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
