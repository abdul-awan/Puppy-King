<?php

namespace App\Actions\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the customer's signature on a work order (on the spot or through the link).
 * Signing completes the work order, so its hours and materials no longer change.
 */
class SignWorkOrder
{
    /**
     * @throws ValidationException
     */
    public function sign(WorkOrder $workOrder, string $name, string $signature, ?string $remarks = null, ?string $ip = null, bool $online = false): void
    {
        if (! $workOrder->canBeSigned()) {
            throw ValidationException::withMessages(['work_order' => 'This work order was already signed.']);
        }

        DB::transaction(function () use ($workOrder, $name, $signature, $remarks, $ip, $online) {
            $workOrder->forceFill([
                'status' => WorkOrderStatus::Completed,
                'completed_at' => $workOrder->completed_at ?? now(),
                'signed_by_name' => $name,
                'signature' => $signature,
                'signed_at' => now(),
                'signed_ip' => $ip,
                'customer_remarks' => $remarks,
            ])->saveQuietly();

            $workOrder->logActivity('signed', ($online ? 'Signed online by ' : 'Signed by ').$name);
        });

        // Signed on the spot, the team saw it happen; signed online, they want to know.
        if ($online) {
            Notifier::workOrderSigned($workOrder);
        }
    }
}
