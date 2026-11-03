<?php

namespace App\Actions\Invoicing;

use App\Enums\EmailTemplateType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\DocumentPlaceholders;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the next invoice of a recurring invoice profile and moves the profile on
 * to the following period. Depending on the profile the invoice is also issued and
 * e-mailed to the customer.
 */
class GenerateRecurringInvoice
{
    public function __construct(
        private readonly IssueInvoice $issue,
        private readonly DocumentMailer $mailer,
    ) {}

    /**
     * Create the invoice for the profile's next date.
     *
     * @throws ValidationException
     */
    public function handle(RecurringInvoice $profile): Invoice
    {
        $invoice = DB::transaction(function () use ($profile) {
            $profile = RecurringInvoice::query()->withoutGlobalScopes()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $date = $profile->next_date ?? throw ValidationException::withMessages(['profile' => 'This recurring invoice has ended.']);

            $invoice = new Invoice([
                'department_id' => $profile->department_id,
                'title' => $profile->title.' — '.$profile->frequency->periodLabel($date),
                'company_id' => $profile->company_id,
                'contact_id' => $profile->contact_id,
                'payment_term_id' => $profile->payment_term_id,
                'date' => $date,
                'due_date' => $profile->paymentTerm?->dueDate($date),
                'currency' => $profile->currency,
                'exchange_rate' => $profile->exchange_rate,
                'discount_percent' => $profile->discount_percent,
                'introduction' => $profile->introduction,
                'terms' => $profile->terms,
                'created_by' => $profile->created_by,
            ]);
            $invoice->team_id = $profile->team_id;
            $invoice->recurring_invoice_id = $profile->id;
            $invoice->status = InvoiceStatus::Draft;
            $invoice->applyFurtherTax($profile->further_tax_rate !== null);
            $invoice->refreshCustomerSnapshot();
            $invoice->save();
            $invoice->replaceLines($profile->linesForCopy());

            $profile->generated_count++;
            $profile->last_generated_at = now();
            $profile->scheduleNext();
            $profile->save();

            $profile->logActivity('generated', 'Created the invoice for '.$profile->frequency->periodLabel($date));

            return $invoice;
        });

        if ($profile->auto_issue) {
            $invoice = $this->issue->handle($invoice);

            if ($profile->auto_send && filled($invoice->customer_email)) {
                $this->send($invoice, $profile);
            }
        }

        return $invoice;
    }

    /**
     * E-mail the issued invoice with the team's invoice template.
     */
    private function send(Invoice $invoice, RecurringInvoice $profile): void
    {
        $sender = $profile->creator ?? $invoice->team()->firstOrFail()->owner();

        if (! $sender instanceof User) {
            return;
        }

        $draft = $this->mailer->draft($invoice, EmailTemplateType::Invoice, DocumentPlaceholders::for($invoice, $sender));
        $sent = $this->mailer->send($invoice, $sender, [...$draft, 'attach_pdf' => true]);

        if ($sent->status === 'sent') {
            $invoice->forceFill(['sent_at' => now()])->saveQuietly();
            $invoice->logActivity('sent', "Sent automatically to {$sent->to}");
        }
    }
}
