<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\CalculatePayslip;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Payslip;
use App\Models\PayslipLine;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Salary advances and loans, recovered in monthly installments through the payroll.
 */
class EmployeeAdvanceController extends Controller
{
    /**
     * Give the employee an advance.
     */
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validated($request);

        $advance = new EmployeeAdvance([...$validated, 'employee_id' => $employee->id, 'created_by' => $request->user()->id]);
        $advance->team_id = $employee->team_id;
        $advance->save();

        $employee->logActivity('advance', 'Advance of '.number_format((float) $advance->amount, 2).' given ('.number_format((float) $advance->installment, 2).' a month)');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advance saved. It is recovered from the next payrolls.')]);

        return back();
    }

    /**
     * Change the advance (e.g. a smaller installment). It cannot drop below what was recovered.
     */
    public function update(Request $request, EmployeeAdvance $advance): RedirectResponse
    {
        $validated = $this->validated($request);

        if (BigDecimal::of((string) $validated['amount'])->isLessThan($advance->recovered())) {
            throw ValidationException::withMessages(['amount' => 'More than this was already recovered through the payroll.']);
        }

        $advance->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advance updated. Draft payrolls pick up the change when they are worked out again.')]);

        return back();
    }

    /**
     * Remove the advance; recoveries planned on draft payslips are removed from them.
     */
    public function destroy(EmployeeAdvance $advance, CalculatePayslip $calculator): RedirectResponse
    {
        if (! $advance->recovered()->isZero()) {
            throw ValidationException::withMessages(['advance' => 'Part of this advance was recovered through an approved payroll, so it is kept.']);
        }

        DB::transaction(function () use ($advance, $calculator) {
            $payslipIds = PayslipLine::query()->where('employee_advance_id', $advance->id)->pluck('payslip_id')->unique();
            PayslipLine::query()->where('employee_advance_id', $advance->id)->delete();

            $advance->employee->logActivity('advance', 'Advance of '.number_format((float) $advance->amount, 2).' removed');
            $advance->delete();

            foreach (Payslip::query()->with('run')->whereKey($payslipIds->all())->get() as $payslip) {
                $calculator->handle($payslip);
                $payslip->run->refreshTotals();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advance removed.')]);

        return back();
    }

    /**
     * Validate the advance.
     *
     * @return array{date: string, amount: string, installment: string, reason: string|null}
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'installment' => ['required', 'numeric', 'gt:0', 'lte:amount'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'installment.lte' => 'The monthly installment cannot be more than the advance.',
        ]);

        return [
            'date' => (string) $validated['date'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'installment' => number_format((float) $validated['installment'], 2, '.', ''),
            'reason' => $validated['reason'] ?? null,
        ];
    }
}
