<?php

namespace App\Http\Controllers\CompanySettings;

use App\Http\Controllers\Controller;
use App\Models\PaymentTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentTermController extends Controller
{
    /**
     * List the payment terms.
     */
    public function index(): Response
    {
        return Inertia::render('company-settings/PaymentTerms', [
            'paymentTerms' => PaymentTerm::query()
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'name', 'days', 'end_of_month', 'is_default', 'sort']),
        ]);
    }

    /**
     * Store a new payment term.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $term = PaymentTerm::create([...$validated, 'sort' => $validated['sort'] ?? (int) PaymentTerm::query()->max('sort') + 1]);
            $this->ensureSingleDefault($term);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment term added.')]);

        return back();
    }

    /**
     * Update a payment term.
     */
    public function update(Request $request, PaymentTerm $paymentTerm): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $paymentTerm) {
            $paymentTerm->update($validated);
            $this->ensureSingleDefault($paymentTerm);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment term saved.')]);

        return back();
    }

    /**
     * Delete a payment term.
     */
    public function destroy(PaymentTerm $paymentTerm): RedirectResponse
    {
        $paymentTerm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment term deleted.')]);

        return back();
    }

    /**
     * Validate the payment term input.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'days' => ['required', 'integer', 'between:0,365'],
            'end_of_month' => ['boolean'],
            'is_default' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * Keep at most one default payment term.
     */
    protected function ensureSingleDefault(PaymentTerm $term): void
    {
        if ($term->is_default) {
            PaymentTerm::query()->whereKeyNot($term->id)->update(['is_default' => false]);
        }
    }
}
