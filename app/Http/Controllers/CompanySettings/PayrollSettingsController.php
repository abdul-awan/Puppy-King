<?php

namespace App\Http\Controllers\CompanySettings;

use App\Enums\PayAmountType;
use App\Enums\PayComponentKind;
use App\Http\Controllers\Controller;
use App\Models\EmployeePayItem;
use App\Models\PayComponent;
use App\Models\SalaryTaxTable;
use App\Support\CurrentTeam;
use App\Support\Payroll\PayrollSettings;
use App\Support\Payroll\TaxYear;
use App\Support\TeamSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How the payroll works out salaries: part months, rounding, EOBI, the pay components and the
 * income tax slabs of salaried individuals.
 */
class PayrollSettingsController extends Controller
{
    /**
     * Show the payroll settings.
     */
    public function edit(): Response
    {
        $team = app(CurrentTeam::class)->team();
        $inUse = EmployeePayItem::query()
            ->toBase()
            ->selectRaw('pay_component_id, COUNT(DISTINCT employee_id) as employees')
            ->groupBy('pay_component_id')
            ->pluck('employees', 'pay_component_id');

        return Inertia::render('company-settings/Payroll', [
            'settings' => PayrollSettings::for($team)->toArray(),
            'daysBases' => array_map(
                fn (string $value, string $label) => ['value' => $value, 'label' => $label],
                array_keys(PayrollSettings::DAYS_BASES),
                PayrollSettings::DAYS_BASES,
            ),
            'components' => PayComponent::query()->orderBy('sort')->orderBy('name')->get()->map(fn (PayComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'kind' => $component->kind->value,
                'kind_label' => $component->kind->label(),
                'amount_type' => $component->amount_type->value,
                'amount_type_label' => $component->amount_type->label(),
                'default_value' => $component->default_value,
                'taxable' => $component->taxable,
                'tax_free_percent' => $component->tax_free_percent,
                'tax_label' => $component->taxLabel(),
                'is_active' => $component->is_active,
                'sort' => $component->sort,
                'employees' => (int) ($inUse[$component->id] ?? 0),
            ])->values(),
            'taxTables' => SalaryTaxTable::query()->orderByDesc('tax_year')->get()->map(fn (SalaryTaxTable $table) => [
                'id' => $table->id,
                'tax_year' => $table->tax_year,
                'label' => $table->label(),
                'name' => $table->name,
                'slabs' => $table->sortedSlabs(),
                'surcharge_threshold' => $table->surcharge_threshold,
                'surcharge_percent' => $table->surcharge_percent,
                'notes' => $table->notes,
            ])->values(),
            'currentTaxYear' => TaxYear::for(CarbonImmutable::today()),
            'kinds' => PayComponentKind::options(),
            'amountTypes' => PayAmountType::options(),
        ]);
    }

    /**
     * Save the payroll settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days_basis' => ['required', Rule::in(array_keys(PayrollSettings::DAYS_BASES))],
            'whole_rupees' => ['boolean'],
            'pay_day' => ['required', 'integer', 'between:0,28'],
            'eobi_default' => ['boolean'],
            'eobi_wage_base' => ['required', 'numeric', 'between:0,9999999'],
            'eobi_employer_percent' => ['required', 'numeric', 'between:0,100'],
            'eobi_employee_percent' => ['required', 'numeric', 'between:0,100'],
            'show_employer_contributions' => ['boolean'],
            'payslip_note' => ['nullable', 'string', 'max:500'],
        ]);

        $team = app(CurrentTeam::class)->team();

        $team->update(['settings' => TeamSettings::merge($team->settings ?? [], ['payroll' => [
            'days_basis' => $validated['days_basis'],
            'whole_rupees' => $request->boolean('whole_rupees'),
            'pay_day' => (int) $validated['pay_day'],
            'eobi_default' => $request->boolean('eobi_default'),
            'eobi_wage_base' => number_format((float) $validated['eobi_wage_base'], 2, '.', ''),
            'eobi_employer_percent' => number_format((float) $validated['eobi_employer_percent'], 2, '.', ''),
            'eobi_employee_percent' => number_format((float) $validated['eobi_employee_percent'], 2, '.', ''),
            'show_employer_contributions' => $request->boolean('show_employer_contributions'),
            'payslip_note' => (string) ($validated['payslip_note'] ?? ''),
        ]])]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payroll settings saved. Draft payrolls use them when they are worked out again.')]);

        return back();
    }
}
