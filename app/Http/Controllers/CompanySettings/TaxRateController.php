<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\TaxAuthority;
use App\Enums\TaxKind;
use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxRateController extends Controller
{
    /**
     * List the tax rates.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/TaxRates', [
            'taxRates' => TaxRate::query()
                ->orderBy('sort')
                ->orderBy('id')
                ->get()
                ->map(fn (TaxRate $rate) => [
                    'id' => $rate->id,
                    'name' => $rate->name,
                    'rate' => $rate->rate,
                    'kind' => $rate->kind->value,
                    'kind_label' => $rate->kind->label(),
                    'authority' => $rate->authority?->value,
                    'authority_label' => $rate->authority?->label(),
                    'description' => $rate->description,
                    'is_default' => $rate->is_default,
                    'is_active' => $rate->is_active,
                    'sort' => $rate->sort,
                ]),
            'kinds' => TaxKind::options(),
            'authorities' => TaxAuthority::options(),
        ]);
    }

    /**
     * Store a new tax rate.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $rate = TaxRate::create([...$validated, 'sort' => $validated['sort'] ?? (int) TaxRate::query()->max('sort') + 1]);
            $this->ensureSingleDefault($rate);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rate added.')]);

        return back();
    }

    /**
     * Update a tax rate.
     */
    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $taxRate) {
            $taxRate->update($validated);
            $this->ensureSingleDefault($taxRate);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rate saved.')]);

        return back();
    }

    /**
     * Delete a tax rate. Existing documents keep a copy of the rate.
     */
    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        $taxRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax rate deleted.')]);

        return back();
    }

    /**
     * Validate the tax rate input.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'between:0,100'],
            'kind' => ['required', Rule::enum(TaxKind::class)],
            'authority' => ['nullable', Rule::enum(TaxAuthority::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Keep at most one default tax rate.
     */
    protected function ensureSingleDefault(TaxRate $rate): void
    {
        if ($rate->is_default) {
            TaxRate::query()->whereKeyNot($rate->id)->update(['is_default' => false]);
        }
    }
}
