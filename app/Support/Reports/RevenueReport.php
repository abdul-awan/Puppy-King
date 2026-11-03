<?php

namespace App\Support\Reports;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Support\CurrentTeam;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Turnover excluding tax, in the team's currency: issued invoices minus issued credit notes.
 */
final class RevenueReport
{
    /**
     * The value of a document after discounts and before tax, in the team's currency.
     */
    private const NET = '(subtotal - discount_amount) * exchange_rate';

    /**
     * The revenue of every month in the period.
     *
     * @return list<array{month: string, invoiced: string, credited: string, net: string}>
     */
    public static function perMonth(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $invoiced = self::sumPerMonth(Invoice::query()->issued(), $start, $end);
        $credited = self::sumPerMonth(CreditNote::query()->issued(), $start, $end);
        $months = [];

        for ($month = $start->startOfMonth(); $month->lessThanOrEqualTo($end); $month = $month->addMonthNoOverflow()) {
            $key = $month->format('Y-m');
            $in = $invoiced[$key] ?? 0.0;
            $out = $credited[$key] ?? 0.0;

            $months[] = [
                'month' => $key,
                'invoiced' => Sql::money($in),
                'credited' => Sql::money($out),
                'net' => Sql::money($in - $out),
            ];
        }

        return $months;
    }

    /**
     * The revenue of the whole period.
     *
     * @return array{invoiced: string, credited: string, net: string, invoices: int}
     */
    public static function totals(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $invoices = DateRange::apply(Invoice::query()->issued(), 'date', [$start, $end]);
        $invoiced = (float) (clone $invoices)->sum(DB::raw(self::NET));
        $credited = (float) DateRange::apply(CreditNote::query()->issued(), 'date', [$start, $end])->sum(DB::raw(self::NET));

        return [
            'invoiced' => Sql::money($invoiced),
            'credited' => Sql::money($credited),
            'net' => Sql::money($invoiced - $credited),
            'invoices' => $invoices->count(),
        ];
    }

    /**
     * The customers with the most revenue in the period.
     *
     * @return list<array{name: string, company_id: int|null, contact_id: int|null, net: string}>
     */
    public static function perCustomer(CarbonImmutable $start, CarbonImmutable $end, ?int $limit = 10): array
    {
        $customers = [];
        $sources = [[Invoice::query()->issued(), 1.0], [CreditNote::query()->issued(), -1.0]];

        foreach ($sources as [$query, $sign]) {
            $totals = DateRange::apply($query, 'date', [$start, $end])
                ->toBase()
                ->select(['company_id', 'contact_id'])
                ->selectRaw('MAX(customer_name) as name')
                ->selectRaw('SUM('.self::NET.') as amount')
                ->groupBy('company_id', 'contact_id')
                ->get();

            foreach ($totals as $total) {
                $companyId = $total->company_id !== null ? (int) $total->company_id : null;
                $contactId = $companyId === null && $total->contact_id !== null ? (int) $total->contact_id : null;
                $key = $companyId !== null ? "company:{$companyId}" : ($contactId !== null ? "contact:{$contactId}" : 'name:'.$total->name);

                $customers[$key] ??= ['name' => (string) ($total->name ?? '—'), 'company_id' => $companyId, 'contact_id' => $contactId, 'amount' => 0.0];
                $customers[$key]['amount'] += $sign * (float) $total->amount;
            }
        }

        usort($customers, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        return array_map(fn (array $customer) => [
            'name' => $customer['name'],
            'company_id' => $customer['company_id'],
            'contact_id' => $customer['contact_id'],
            'net' => Sql::money($customer['amount']),
        ], array_slice($customers, 0, $limit));
    }

    /**
     * The products and services with the most revenue in the period (lines without a product together).
     *
     * @return list<array{product_id: int|null, name: string, quantity: string, net: string}>
     */
    public static function perProduct(CarbonImmutable $start, CarbonImmutable $end, ?int $limit = 10): array
    {
        $products = [];

        foreach (['invoices' => 1.0, 'credit_notes' => -1.0] as $table => $sign) {
            $totals = self::issuedLines($table, $start, $end)
                ->leftJoin('products', 'products.id', '=', 'document_lines.product_id')
                ->groupBy('document_lines.product_id', 'products.name')
                ->select(['document_lines.product_id', 'products.name'])
                ->selectRaw('SUM(document_lines.quantity) as quantity')
                ->selectRaw("SUM(document_lines.net_amount * {$table}.exchange_rate) as amount")
                ->get();

            foreach ($totals as $total) {
                $productId = $total->product_id !== null ? (int) $total->product_id : null;
                $key = $productId ?? 0;

                $products[$key] ??= [
                    'product_id' => $productId,
                    'name' => $productId !== null ? (string) ($total->name ?? 'Deleted product') : 'Other lines (no product)',
                    'quantity' => 0.0,
                    'amount' => 0.0,
                ];
                $products[$key]['quantity'] += $sign * (float) $total->quantity;
                $products[$key]['amount'] += $sign * (float) $total->amount;
            }
        }

        usort($products, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        return array_map(fn (array $product) => [
            'product_id' => $product['product_id'],
            'name' => $product['name'],
            'quantity' => rtrim(rtrim(number_format($product['quantity'], 3, '.', ''), '0'), '.'),
            'net' => Sql::money($product['amount']),
        ], array_slice($products, 0, $limit));
    }

    /**
     * The item lines of the issued invoices or credit notes (by table name) dated in the period.
     */
    public static function issuedLines(string $table, CarbonImmutable $start, CarbonImmutable $end): QueryBuilder
    {
        $model = $table === 'credit_notes' ? new CreditNote : new Invoice;

        return DB::table('document_lines')
            ->join($table, fn (JoinClause $join) => $join
                ->on("{$table}.id", '=', 'document_lines.documentable_id')
                ->where('document_lines.documentable_type', '=', $model->getMorphClass()))
            ->where("{$table}.team_id", app(CurrentTeam::class)->id())
            ->whereNull("{$table}.deleted_at")
            ->when(
                $model instanceof CreditNote,
                fn (QueryBuilder $query) => $query->where('credit_notes.status', CreditNoteStatus::Issued->value),
                fn (QueryBuilder $query) => $query->where('invoices.status', '!=', InvoiceStatus::Draft->value),
            )
            ->whereBetween("{$table}.date", [$start->startOfDay(), $end->endOfDay()])
            ->where('document_lines.type', 'item');
    }

    /**
     * Sum the documents per month.
     *
     * @param  Builder<Invoice>|Builder<CreditNote>  $query
     * @return array<string, float>
     */
    private static function sumPerMonth(Builder $query, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $month = Sql::month('date');

        return DateRange::apply($query, 'date', [$start, $end])
            ->toBase()
            ->selectRaw("{$month} as month")
            ->selectRaw('SUM('.self::NET.') as amount')
            ->groupByRaw($month)
            ->pluck('amount', 'month')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }
}
