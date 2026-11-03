<?php

namespace App\Actions\Invoicing;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentType;
use App\Enums\LineType;
use App\Models\CreditNote;
use App\Models\DocumentLine;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\DocumentNumberer;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a draft credit note: it gets its number, lowers the balance of the invoice
 * and (when goods were returned) puts tracked products back in stock.
 */
class IssueCreditNote
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * Issue the credit note.
     *
     * @throws ValidationException
     */
    public function handle(CreditNote $creditNote): CreditNote
    {
        return DB::transaction(function () use ($creditNote) {
            $creditNote = CreditNote::query()->withoutGlobalScopes()->whereKey($creditNote->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::query()->withoutGlobalScopes()->whereKey($creditNote->invoice_id)->lockForUpdate()->firstOrFail();

            if (! $creditNote->isDraft()) {
                throw ValidationException::withMessages(['credit_note' => 'This credit note has already been issued.']);
            }

            if ($invoice->isDraft()) {
                throw ValidationException::withMessages(['credit_note' => 'The invoice has not been issued yet; change the draft invoice instead.']);
            }

            if (! $creditNote->lines()->where('type', LineType::Item)->exists() || ! BigDecimal::of($creditNote->total)->isPositive()) {
                throw ValidationException::withMessages(['credit_note' => 'The credit note needs at least one line with an amount.']);
            }

            if (BigDecimal::of($creditNote->total)->isGreaterThan(BigDecimal::of($invoice->creditableAmount()))) {
                throw ValidationException::withMessages([
                    'credit_note' => sprintf(
                        'The credit note (%s) is more than can still be credited on invoice %s (%s).',
                        $creditNote->formatMoney($creditNote->total),
                        $invoice->number,
                        $invoice->formatMoney($invoice->creditableAmount()),
                    ),
                ]);
            }

            $team = $creditNote->team()->firstOrFail();

            $creditNote->refreshCustomerSnapshot();
            $creditNote->number = $this->numberer->next($team, DocumentType::CreditNote, $creditNote->date, $creditNote->department);
            $creditNote->status = CreditNoteStatus::Issued;
            $creditNote->issued_at = now();
            $creditNote->save();

            if ($creditNote->restock) {
                $this->returnToStock($creditNote);
            }

            $invoice->refreshBalance();

            $creditNote->logActivity('issued', "Issued as {$creditNote->number}");
            $invoice->logActivity('credited', sprintf('Credit note %s of %s', $creditNote->number, $creditNote->formatMoney($creditNote->total)));

            return $creditNote;
        });
    }

    /**
     * Put the returned tracked products back in stock.
     */
    private function returnToStock(CreditNote $creditNote): void
    {
        $lines = $creditNote->lines()->where('type', LineType::Item)->whereNotNull('product_id')->get();

        $products = Product::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('team_id', $creditNote->team_id)
            ->where('track_stock', true)
            ->whereKey($lines->pluck('product_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $lines->each(function (DocumentLine $line) use ($products, $creditNote) {
            $products->get($line->product_id)?->changeStock((string) $line->quantity, "Credit note {$creditNote->number}", $creditNote);
        });
    }
}
