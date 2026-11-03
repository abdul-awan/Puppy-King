<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\CalculatePayslip;
use App\Actions\Payroll\CreatePayrollRun;
use App\Actions\Payroll\EmailPayslip;
use App\Enums\PayComponentKind;
use App\Enums\PayrollRunStatus;
use App\Enums\PayslipLineSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\PayslipRequest;
use App\Models\EmployeeAdvance;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Support\Documents\PayslipPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * One employee's payslip in a payroll.
 */
class PayslipController extends Controller
{
    /**
     * Change the draft payslip and work it out again.
     */
    public function update(PayslipRequest $request, Payslip $payslip, CalculatePayslip $calculator): RedirectResponse
    {
        $this->ensureDraft($payslip);

        DB::transaction(function () use ($request, $payslip, $calculator) {
            $payslip->fill([
                'unpaid_days' => number_format((float) $request->validated('unpaid_days'), 2, '.', ''),
                'tax_override' => $request->validated('tax_override') !== null ? number_format((float) $request->validated('tax_override'), 2, '.', '') : null,
                'notes' => $request->validated('notes'),
            ])->save();

            // The one-off amounts and the advance recoveries are replaced only when they are sent.
            if ($request->has('adjustments')) {
                $payslip->lines()->where('source', PayslipLineSource::Adjustment)->delete();

                foreach ($request->adjustments() as $adjustment) {
                    $this->addLine($payslip, [
                        'source' => PayslipLineSource::Adjustment,
                        'kind' => PayComponentKind::from($adjustment['kind']),
                        'name' => $adjustment['name'],
                        'amount' => $adjustment['amount'],
                        'taxable_amount' => $adjustment['taxable'] ? $adjustment['amount'] : '0.00',
                    ]);
                }
            }

            $advances = EmployeeAdvance::query()->where('employee_id', $payslip->employee_id)->get()->keyBy('id');

            if ($request->has('advances')) {
                $payslip->lines()->where('source', PayslipLineSource::Advance)->delete();
            }

            foreach ($request->recoveries() as $recovery) {
                $advance = $advances->get($recovery['id']);

                if ($advance === null) {
                    continue;
                }

                $this->addLine($payslip, [
                    'source' => PayslipLineSource::Advance,
                    'kind' => PayComponentKind::Deduction,
                    'employee_advance_id' => $advance->id,
                    'name' => CreatePayrollRun::advanceLineName($advance),
                    'amount' => $recovery['amount'],
                    'taxable_amount' => '0.00',
                ]);
            }

            $payslip->unsetRelation('lines');
            $calculator->handle($payslip);
            $payslip->run->refreshTotals();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payslip of :name worked out again.', ['name' => $payslip->employee_name])]);

        return back();
    }

    /**
     * Take the employee off the draft payroll.
     */
    public function destroy(Payslip $payslip): RedirectResponse
    {
        $this->ensureDraft($payslip);

        DB::transaction(function () use ($payslip) {
            $run = $payslip->run;
            $payslip->delete();
            $run->refreshTotals();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name taken off this payroll.', ['name' => $payslip->employee_name])]);

        return back();
    }

    /**
     * Show or download the payslip as PDF.
     */
    public function pdf(Request $request, Payslip $payslip, PayslipPdf $pdf): HttpResponse
    {
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($payslip), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($payslip).'"',
        ]);
    }

    /**
     * E-mail the payslip to the employee.
     */
    public function email(Request $request, Payslip $payslip, EmailPayslip $mailer): RedirectResponse
    {
        if ($payslip->run->isDraft()) {
            throw ValidationException::withMessages(['run' => 'Approve the payroll before sending the payslips.']);
        }

        if (blank($payslip->employee->email)) {
            throw ValidationException::withMessages(['run' => "{$payslip->employee_name} has no e-mail address. Add it to the employee first."]);
        }

        $sent = $mailer->handle($payslip, $request->user());

        if ($sent->status !== 'sent') {
            throw ValidationException::withMessages(['run' => 'The e-mail could not be sent: '.$sent->error]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payslip sent to :to.', ['to' => $sent->to])]);

        return back();
    }

    /**
     * Record that the salary was paid.
     */
    public function pay(Request $request, Payslip $payslip): RedirectResponse
    {
        if ($payslip->run->isDraft()) {
            throw ValidationException::withMessages(['run' => 'Approve the payroll before paying it.']);
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($payslip, $validated) {
            $payslip->forceFill(['paid_on' => $validated['date'], 'payment_reference' => $validated['reference'] ?? null])->save();
            $this->syncRunStatus($payslip);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Salary of :name marked as paid.', ['name' => $payslip->employee_name])]);

        return back();
    }

    /**
     * Undo the payment of the salary.
     */
    public function unpay(Payslip $payslip): RedirectResponse
    {
        DB::transaction(function () use ($payslip) {
            $payslip->forceFill(['paid_on' => null, 'payment_reference' => null])->save();
            $this->syncRunStatus($payslip);
        });

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Payment undone.')]);

        return back();
    }

    /**
     * The payroll is paid when every payslip is paid.
     */
    protected function syncRunStatus(Payslip $payslip): void
    {
        $run = $payslip->run;
        $allPaid = ! $run->payslips()->whereNull('paid_on')->exists();

        if ($allPaid && $run->status !== PayrollRunStatus::Paid) {
            $run->forceFill(['status' => PayrollRunStatus::Paid, 'paid_at' => now()])->save();
        } elseif (! $allPaid && $run->status === PayrollRunStatus::Paid) {
            $run->forceFill(['status' => PayrollRunStatus::Approved, 'paid_at' => null])->save();
        }
    }

    /**
     * Save a line on the payslip.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function addLine(Payslip $payslip, array $attributes): void
    {
        $line = new PayslipLine($attributes);
        $line->team_id = $payslip->team_id;
        $payslip->lines()->save($line);
    }

    /**
     * Payslips can only be changed while the payroll is a draft.
     */
    protected function ensureDraft(Payslip $payslip): void
    {
        if (! $payslip->run->isDraft()) {
            throw ValidationException::withMessages(['run' => 'The payroll was approved, so its payslips are locked. Make it a draft again to change it.']);
        }
    }
}
