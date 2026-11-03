<?php

namespace App\Http\Controllers\Invoicing;

use App\Actions\Invoicing\RecordPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\PaymentRequest;
use App\Http\Resources\InvoicePaymentResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoicePaymentController extends Controller
{
    /**
     * Display all received payments, with the tax withheld by customers.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'method' => PaymentMethod::tryFrom($request->string('method')->toString())?->value,
            ...DateRange::filters($request),
        ];

        $range = DateRange::resolve($filters);
        $like = '%'.addcslashes($filters['search'], '%_\\').'%';

        $query = InvoicePayment::query()
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', $like)
                ->orWhereHas('invoice', fn (Builder $query) => $query->where('number', 'like', $like)->orWhere('customer_name', 'like', $like))))
            ->when($filters['method'], fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($range, fn (Builder $query, array $range) => DateRange::apply($query, 'date', $range));

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(amount), 0) as received, COALESCE(SUM(income_tax_withheld), 0) as income_tax, COALESCE(SUM(sales_tax_withheld), 0) as sales_tax')
            ->first();

        return Inertia::render('payments/Index', [
            'payments' => InvoicePaymentResource::collection(
                $query->with(['invoice:id,number,customer_name,currency', 'user:id,name'])->latest('date')->latest('id')->paginate(25)->withQueryString(),
            ),
            'filters' => $filters,
            'periods' => DateRange::options(),
            'methods' => PaymentMethod::options(),
            'totals' => [
                'received' => number_format((float) $totals?->getAttribute('received'), 2, '.', ''),
                'income_tax_withheld' => number_format((float) $totals?->getAttribute('income_tax'), 2, '.', ''),
                'sales_tax_withheld' => number_format((float) $totals?->getAttribute('sales_tax'), 2, '.', ''),
            ],
        ]);
    }

    /**
     * Record a payment on the invoice.
     */
    public function store(PaymentRequest $request, Invoice $invoice, RecordPayment $payments): RedirectResponse
    {
        $payments->record($invoice, $request->payment(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /**
     * Remove a payment from the invoice.
     */
    public function destroy(Invoice $invoice, InvoicePayment $payment, RecordPayment $payments): RedirectResponse
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $payments->remove($payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment removed.')]);

        return back();
    }
}
