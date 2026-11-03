<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsSalesDocument;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNote
 */
class CreditNoteResource extends JsonResource
{
    use PresentsSalesDocument;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CreditNote $creditNote */
        $creditNote = $this->resource;

        return [
            ...$this->documentFields($creditNote),
            'status' => $creditNote->status->value,
            'reason' => $creditNote->reason,
            'restock' => $creditNote->restock,
            'invoice_id' => $creditNote->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $creditNote->invoice->id,
                'number' => $creditNote->invoice->number,
                'date' => $creditNote->invoice->date->toDateString(),
                'total' => $creditNote->invoice->total,
                'creditable_amount' => $creditNote->invoice->creditableAmount(),
            ]),
            'issued_at' => $creditNote->issued_at?->toIso8601String(),
            'sent_at' => $creditNote->sent_at?->toIso8601String(),
        ];
    }
}
