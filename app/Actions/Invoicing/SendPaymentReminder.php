<?php

namespace App\Actions\Invoicing;

use App\Enums\EmailTemplateType;
use App\Models\Invoice;
use App\Models\SentEmail;
use App\Models\User;
use App\Support\Documents\DocumentMailer;
use App\Support\Documents\DocumentPlaceholders;

/**
 * E-mails a payment reminder for an open invoice.
 */
class SendPaymentReminder
{
    public function __construct(private readonly DocumentMailer $mailer) {}

    /**
     * Get the reminder e-mail as it would be sent.
     *
     * @return array{to: string, cc: string, subject: string, body: string}
     */
    public function draft(Invoice $invoice, ?User $user = null): array
    {
        return $this->mailer->draft($invoice, EmailTemplateType::PaymentReminder, DocumentPlaceholders::for($invoice, $user));
    }

    /**
     * Send the reminder (the drafted one unless a message is given).
     *
     * @param  array{to: string, cc?: string|null, subject: string, body: string, attach_pdf?: bool}|null  $message
     */
    public function send(Invoice $invoice, User $sender, ?array $message = null): SentEmail
    {
        $sent = $this->mailer->send($invoice, $sender, $message ?? [...$this->draft($invoice, $sender), 'attach_pdf' => true]);

        if ($sent->status === 'sent') {
            $invoice->forceFill([
                'reminder_count' => $invoice->reminder_count + 1,
                'last_reminder_at' => now(),
            ])->saveQuietly();

            $invoice->logActivity('reminded', sprintf('Payment reminder %d sent to %s', $invoice->reminder_count, $sent->to));
        }

        return $sent;
    }
}
