<?php

namespace App\Actions\Payroll;

use App\Enums\EmailTemplateType;
use App\Models\Payslip;
use App\Models\SentEmail;
use App\Models\Team;
use App\Models\User;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\PayslipPdf;
use App\Support\Documents\Templates\DocumentOptions;

/**
 * E-mails a payslip (as PDF) to the employee, with the company's payslip e-mail template. The copy
 * of the e-mail is kept in the employee's history.
 */
class EmailPayslip
{
    public function __construct(private DocumentMailer $mailer, private PayslipPdf $pdf)
    {
        //
    }

    /**
     * Send the payslip. A failed delivery is recorded (and returned) instead of thrown.
     */
    public function handle(Payslip $payslip, User $sender): SentEmail
    {
        $payslip->loadMissing('employee');

        $team = Team::query()->findOrFail($payslip->team_id);
        $department = $team->defaultDepartment();
        $options = DocumentOptions::forTeam($team);

        $message = $this->mailer->compose(
            $team->id,
            EmailTemplateType::Payslip,
            [
                '{employee_name}' => $payslip->employee_name,
                '{period}' => $payslip->periodLabel(),
                '{net_pay}' => $options->money($payslip->net, $team->teamSettings()->currency()),
                '{payslip_number}' => $payslip->number ?? '',
                '{company_name}' => $department?->displayName() ?? $team->name,
                '{user_name}' => $sender->name,
            ],
            (string) $payslip->employee->email,
            'Payslip '.$payslip->periodLabel(),
        );

        $sent = $this->mailer->deliver(
            $payslip->employee,
            $department,
            $sender,
            $message,
            $this->pdf->render($payslip),
            $this->pdf->filename($payslip),
        );

        if ($sent->status === 'sent') {
            $payslip->forceFill(['emailed_at' => now()])->save();
        }

        return $sent;
    }
}
