<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayslipResource;
use App\Models\Employee;
use App\Models\Payslip;
use App\Support\Documents\PayslipPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employees with a login see their own approved payslips, without access to the payroll itself.
 */
class MyPayslipController extends Controller
{
    /**
     * List the user's payslips in the current company.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('payroll/MyPayslips', [
            'payslips' => PayslipResource::collection($this->mine($request)->orderByDesc('period_start')->paginate(24)),
            'employees' => Employee::query()->where('user_id', $request->user()->id)->get(['id', 'name', 'code'])->map(fn (Employee $employee) => [
                'name' => $employee->name,
                'code' => $employee->code,
            ])->values(),
        ]);
    }

    /**
     * Show or download one of the user's payslips as PDF.
     */
    public function pdf(Request $request, Payslip $payslip, PayslipPdf $pdf): HttpResponse
    {
        abort_unless($this->mine($request)->whereKey($payslip->id)->exists(), 404);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->render($payslip), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($payslip).'"',
        ]);
    }

    /**
     * The approved payslips of the employees linked to the user's login.
     *
     * @return Builder<Payslip>
     */
    protected function mine(Request $request): Builder
    {
        return Payslip::query()
            ->whereHas('employee', fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->whereHas('run', fn (Builder $query) => $query->where('status', '!=', PayrollRunStatus::Draft));
    }
}
