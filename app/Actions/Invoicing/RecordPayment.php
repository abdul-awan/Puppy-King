<?php

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records (or removes) a payment on an issued invoice and updates its balance.
 */
class RecordPayment
{
    /**
     * Record a payment.
     *
     * @param  array{date: string, amount: string|float|int, income_tax_withheld?: string|float|int|null, sales_tax_withheld?: string|float|int|null, method: string, reference?: string|null, notes?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function record(Invoice $invoice, array $data, ?User $user = null): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = Invoice::query()->withoutGlobalScopes()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->isDraft()) {
                throw ValidationException::withMessages(['amount' => 'Issue the invoice before recording payments.']);
            }

            $payment = new InvoicePayment([
                ...$data,
                'income_tax_withheld' => $data['income_tax_withheld'] ?? 0,
                'sales_tax_withheld' => $data['sales_tax_withheld'] ?? 0,
            ]);

            $settled = $payment->settledAmount();

            if (! $settled->isPositive()) {
                throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
            }

            if ($settled->isGreaterThan(BigDecimal::of($invoice->balanceDue()))) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('The payment (%s) is more than the balance due (%s).', $invoice->formatMoney((string) $settled->toScale(2)), $invoice->formatMoney($invoice->balanceDue())),
                ]);
            }

            $payment->team_id = $invoice->team_id;
            $payment->invoice_id = $invoice->id;
            $payment->user_id = $user?->id;
            $payment->save();

            $invoice->refreshBalance();
            $invoice->logActivity('payment', sprintf('Payment of %s recorded', $invoice->formatMoney((string) $settled->toScale(2))), [
                'amount' => $payment->amount,
                'income_tax_withheld' => $payment->income_tax_withheld,
                'sales_tax_withheld' => $payment->sales_tax_withheld,
            ]);

            return $payment;
        });
    }

    /**
     * Remove a payment (e.g. a bounced cheque).
     */
    public function remove(InvoicePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = Invoice::query()->withoutGlobalScopes()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();

            $payment->delete();

            $invoice->refreshBalance();
            $invoice->logActivity('payment_removed', sprintf('Payment of %s from %s removed', $invoice->formatMoney((string) $payment->settledAmount()->toScale(2)), $payment->date->format('d M Y')));
        });
    }
}
