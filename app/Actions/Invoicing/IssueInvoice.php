<?php

namespace App\Actions\Invoicing;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\LineType;
use App\Models\DocumentLine;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Support\DocumentNumberer;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a draft invoice: it gets its number and due date, the customer's details are
 * frozen and tracked products are taken out of stock. After this it can only be
 * corrected with a credit note.
 */
class IssueInvoice
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * Issue the invoice.
     *
     * @throws ValidationException
     */
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->withoutGlobalScopes()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->isDraft()) {
                throw ValidationException::withMessages(['invoice' => 'This invoice has already been issued.']);
            }

            if (! $invoice->lines()->where('type', LineType::Item)->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Add at least one line with an amount before issuing the invoice.']);
            }

            if (BigDecimal::of($invoice->total)->isNegative()) {
                throw ValidationException::withMessages(['invoice' => 'An invoice cannot have a negative total. Use a credit note instead.']);
            }

            $team = $invoice->team()->firstOrFail();

            if ($invoice->due_date === null) {
                $term = $invoice->paymentTerm
                    ?? PaymentTerm::query()->withoutGlobalScopes()->where('team_id', $team->id)->where('is_default', true)->first();

                $invoice->due_date = $term?->dueDate($invoice->date) ?? $invoice->date;
            }

            $invoice->refreshCustomerSnapshot();
            $invoice->number = $this->numberer->next($team, DocumentType::Invoice, $invoice->date, $invoice->department);
            $invoice->status = InvoiceStatus::Open;
            $invoice->issued_at = now();
            $invoice->save();

            $this->takeFromStock($invoice);

            // A zero-value invoice is settled straight away.
            $invoice->refreshBalance();
            $invoice->logActivity('issued', "Issued as {$invoice->number}");

            return $invoice;
        });
    }

    /**
     * Lower the stock of the tracked products on the invoice.
     */
    private function takeFromStock(Invoice $invoice): void
    {
        $lines = $invoice->lines()->where('type', LineType::Item)->whereNotNull('product_id')->get();

        $products = Product::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('team_id', $invoice->team_id)
            ->where('track_stock', true)
            ->whereKey($lines->pluck('product_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $lines->each(function (DocumentLine $line) use ($products, $invoice) {
            $product = $products->get($line->product_id);

            $product?->changeStock((string) BigDecimal::of($line->quantity)->negated(), "Invoice {$invoice->number}", $invoice);
        });
    }
}
