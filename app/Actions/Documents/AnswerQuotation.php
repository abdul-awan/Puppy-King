<?php

namespace App\Actions\Documents;

use App\Enums\DealStatus;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Records a customer's answer to a quotation (online or entered by a user).
 */
class AnswerQuotation
{
    /**
     * Accept the quotation; an open deal it belongs to is marked as won.
     */
    public function accept(Quotation $quotation, ?string $name = null, ?string $signature = null, ?string $ip = null, bool $online = false): void
    {
        DB::transaction(function () use ($quotation, $name, $signature, $ip, $online) {
            $quotation->forceFill([
                'status' => QuotationStatus::Accepted,
                'accepted_at' => now(),
                'accepted_by_name' => $name,
                'accepted_signature' => $signature,
                'accepted_ip' => $ip,
                'declined_at' => null,
                'decline_reason' => null,
            ]);
            $quotation->saveQuietly();

            $quotation->logActivity(
                'accepted',
                $online ? 'Accepted online'.($name ? " by {$name}" : '') : 'Marked as accepted',
            );

            $deal = $quotation->deal;

            if ($deal && $deal->status === DealStatus::Open) {
                $deal->forceFill(['status' => DealStatus::Won, 'closed_at' => now()]);
                $deal->saveQuietly();
                $deal->logActivity('won', "Won: quotation {$quotation->number} was accepted");
            }
        });

        if ($online) {
            Notifier::quotationAnswered($quotation, accepted: true);
        }
    }

    /**
     * Decline the quotation.
     */
    public function decline(Quotation $quotation, ?string $reason = null, bool $online = false): void
    {
        $quotation->forceFill([
            'status' => QuotationStatus::Declined,
            'declined_at' => now(),
            'decline_reason' => $reason,
            'accepted_at' => null,
            'accepted_by_name' => null,
            'accepted_signature' => null,
            'accepted_ip' => null,
        ]);
        $quotation->saveQuietly();

        $quotation->logActivity('declined', ($online ? 'Declined online' : 'Marked as declined').($reason ? ": {$reason}" : ''));

        if ($online) {
            Notifier::quotationAnswered($quotation, accepted: false);
        }
    }
}
