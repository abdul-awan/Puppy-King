<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\PayAmountType;
use App\Enums\PayComponentKind;
use App\Http\Controllers\Controller;
use App\Models\EmployeePayItem;
use App\Models\PayComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The allowances, deductions and employer contributions that can be put on salaries.
 */
class PayComponentController extends Controller
{
    /**
     * Add a component.
     */
    public function store(Request $request): RedirectResponse
    {
        PayComponent::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay component added.')]);

        return back();
    }

    /**
     * Change a component (draft payrolls pick up the change when they are worked out again).
     */
    public function update(Request $request, PayComponent $payComponent): RedirectResponse
    {
        $payComponent->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay component saved.')]);

        return back();
    }

    /**
     * Delete a component that is on nobody's salary.
     */
    public function destroy(PayComponent $payComponent): RedirectResponse
    {
        $employees = EmployeePayItem::query()->where('pay_component_id', $payComponent->id)->distinct()->count('employee_id');

        if ($employees > 0) {
            throw ValidationException::withMessages([
                'component' => trans_choice('{1} :name is on the salary of 1 employee. Switch it off instead, or take it off the salary first.|[2,*] :name is on the salary of :count employees. Switch it off instead, or take it off their salaries first.', $employees, ['name' => $payComponent->name]),
            ]);
        }

        $payComponent->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay component deleted.')]);

        return back();
    }

    /**
     * Validate the component.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(PayComponentKind::class)],
            'amount_type' => [
                'required',
                Rule::enum(PayAmountType::class),
                // An allowance cannot depend on the gross salary it is part of.
                Rule::when($request->input('kind') === PayComponentKind::Earning->value, [Rule::notIn([PayAmountType::PercentGross->value])]),
            ],
            'default_value' => ['nullable', 'numeric', 'min:0', $request->input('amount_type') === PayAmountType::Fixed->value ? 'max:999999999' : 'max:100'],
            'taxable' => ['boolean'],
            'tax_free_percent' => ['nullable', 'numeric', 'between:0,100'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ], [
            'amount_type.not_in' => 'An allowance can be a fixed amount or a percentage of the basic salary.',
            'default_value.max' => 'A percentage can be at most 100.',
        ]);

        $earning = $validated['kind'] === PayComponentKind::Earning->value;
        $taxable = $earning && $request->boolean('taxable');

        return [
            'name' => trim((string) $validated['name']),
            'kind' => $validated['kind'],
            'amount_type' => $validated['amount_type'],
            'default_value' => number_format((float) ($validated['default_value'] ?? 0), 2, '.', ''),
            'taxable' => $taxable,
            'tax_free_percent' => $taxable && (float) ($validated['tax_free_percent'] ?? 0) > 0
                ? number_format((float) $validated['tax_free_percent'], 2, '.', '')
                : null,
            'is_active' => $request->boolean('is_active', true),
            'sort' => (int) ($validated['sort'] ?? 0),
        ];
    }
}
