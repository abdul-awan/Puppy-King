<?php

namespace App\Support\Documents;

use App\Enums\PayComponentKind;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Team;
use App\Support\Documents\Templates\DocumentOptions;
use App\Support\Payroll\PayrollSettings;
use App\Support\Payroll\TaxYear;
use App\Support\Reports\Sql;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Renders payslips as A4 PDF files, one payslip per page.
 */
class PayslipPdf
{
    /**
     * Render one or more payslips (of one company) and return the PDF contents.
     *
     * @param  Payslip|Collection<int, Payslip>  $payslips
     */
    public function render(Payslip|Collection $payslips): string
    {
        return Pdf::loadView('pdf.payslips', $this->data($payslips instanceof Payslip ? collect([$payslips]) : $payslips))
            ->setPaper('a4')
            // Embed only the characters used instead of whole font files.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * Get the file name of a payslip's PDF.
     */
    public function filename(Payslip $payslip): string
    {
        return Str::slug('Payslip '.$payslip->periodLabel().' '.$payslip->employee_name).'.pdf';
    }

    /**
     * Get the file name of the PDF with all payslips of a payroll.
     */
    public function runFilename(PayrollRun $run): string
    {
        return Str::slug('Payslips '.$run->label()).'.pdf';
    }

    /**
     * Get the data used by the PDF view.
     *
     * @param  Collection<int, Payslip>  $payslips
     * @return array<string, mixed>
     */
    public function data(Collection $payslips): array
    {
        $first = $payslips->first();
        $team = $first !== null ? Team::query()->findOrFail($first->team_id) : null;
        $department = $team?->defaultDepartment();
        $options = $team !== null ? DocumentOptions::forTeam($team) : new DocumentOptions;
        $settings = $team !== null ? PayrollSettings::for($team) : null;
        $currency = $team?->teamSettings()->currency() ?? 'PKR';

        $slips = $payslips->map(function (Payslip $payslip) use ($options, $settings, $currency) {
            $payslip->loadMissing(['lines', 'run', 'employee']);
            $taxYear = TaxYear::for($payslip->period_start);

            // The totals of the tax year so far, this month included.
            $yearToDate = Payslip::query()
                ->withoutGlobalScopes()
                ->where('team_id', $payslip->team_id)
                ->where('employee_id', $payslip->employee_id)
                ->whereDate('period_start', '>=', TaxYear::start($taxYear))
                ->whereDate('period_start', '<=', $payslip->period_start)
                ->toBase()
                ->selectRaw('SUM(gross) as gross, SUM(taxable_income) as taxable, SUM(income_tax) as tax')
                ->first();

            // The salary and tax of the tax year before the payroll was used count too.
            $opening = $payslip->employee->opening_tax_year === $taxYear
                ? ['taxable' => (float) $payslip->employee->opening_taxable, 'tax' => (float) $payslip->employee->opening_tax]
                : ['taxable' => 0.0, 'tax' => 0.0];

            return [
                'payslip' => $payslip,
                'stamp' => match (true) {
                    $payslip->run->status === PayrollRunStatus::Draft => 'DRAFT',
                    $payslip->isPaid() => 'PAID',
                    default => null,
                },
                'earnings' => $payslip->linesOf(PayComponentKind::Earning),
                'deductions' => $payslip->linesOf(PayComponentKind::Deduction),
                'employer' => $settings?->showEmployerContributions() ? $payslip->linesOf(PayComponentKind::Employer) : collect(),
                'words' => NumberToWords::money($payslip->net, $currency, $options->numberFormat === 'south_asian'),
                'taxYear' => TaxYear::label($taxYear),
                'yearToDate' => [
                    'gross' => Sql::money($yearToDate->gross ?? 0),
                    'taxable' => Sql::money((float) ($yearToDate->taxable ?? 0) + $opening['taxable']),
                    'tax' => Sql::money((float) ($yearToDate->tax ?? 0) + $opening['tax']),
                ],
                'joiningDate' => $payslip->employee->joining_date,
                'payDate' => $payslip->paid_on ?? $payslip->run->pay_date,
            ];
        })->values();

        return [
            'slips' => $slips,
            'department' => $department,
            'logo' => $department?->logoDataUri(),
            'options' => $options,
            'currency' => $currency,
            'note' => $settings?->payslipNote(),
        ];
    }
}
