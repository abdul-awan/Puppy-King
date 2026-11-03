<?php

namespace App\Support\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * What customers still owe (open invoices), split by how long it is overdue.
 */
final class ReceivablesReport
{
    /**
     * The overdue buckets, by days past the due date.
     *
     * @var array<string, string>
     */
    public const BUCKETS = [
        'not_due' => 'Not due yet',
        'days_1_30' => '1–30 days',
        'days_31_60' => '31–60 days',
        'days_61_90' => '61–90 days',
        'days_over_90' => 'Over 90 days',
    ];

    /**
     * The open balances per customer and bucket, in the team's currency, largest first.
     *
     * @return array{
     *     rows: list<array{name: string, company_id: int|null, contact_id: int|null, invoices: int, buckets: array<string, string>, total: string}>,
     *     totals: array<string, string>,
     *     total: string,
     *     overdue: string,
     *     invoices: int
     * }
     */
    public static function aging(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Open)
            ->toBase()
            ->select(['company_id', 'contact_id', 'customer_name', 'date', 'due_date'])
            ->selectRaw('(total - amount_paid - amount_credited) * exchange_rate as balance')
            ->get();

        $empty = array_fill_keys(array_keys(self::BUCKETS), 0.0);
        $customers = [];
        $totals = $empty;
        $count = 0;

        foreach ($invoices as $invoice) {
            $balance = (float) $invoice->balance;

            if ($balance < 0.005) {
                continue;
            }

            $companyId = $invoice->company_id !== null ? (int) $invoice->company_id : null;
            $contactId = $companyId === null && $invoice->contact_id !== null ? (int) $invoice->contact_id : null;
            $key = $companyId !== null ? "company:{$companyId}" : ($contactId !== null ? "contact:{$contactId}" : 'name:'.$invoice->customer_name);
            $bucket = self::bucket(CarbonImmutable::parse((string) ($invoice->due_date ?? $invoice->date)), $today);

            $customers[$key] ??= [
                'name' => (string) ($invoice->customer_name ?? '—'),
                'company_id' => $companyId,
                'contact_id' => $contactId,
                'invoices' => 0,
                'buckets' => $empty,
                'total' => 0.0,
            ];
            $customers[$key]['invoices']++;
            $customers[$key]['buckets'][$bucket] += $balance;
            $customers[$key]['total'] += $balance;
            $totals[$bucket] += $balance;
            $count++;
        }

        usort($customers, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $sum = array_sum($totals);

        return [
            'rows' => array_map(fn (array $customer) => [
                ...$customer,
                'buckets' => array_map(fn (float $amount) => Sql::money($amount), $customer['buckets']),
                'total' => Sql::money($customer['total']),
            ], $customers),
            'totals' => array_map(fn (float $amount) => Sql::money($amount), $totals),
            'total' => Sql::money($sum),
            'overdue' => Sql::money($sum - $totals['not_due']),
            'invoices' => $count,
        ];
    }

    /**
     * The bucket of an invoice with the given due date. Due today is not overdue yet.
     */
    public static function bucket(CarbonImmutable $dueDate, CarbonImmutable $today): string
    {
        $dueDate = $dueDate->startOfDay();

        if ($dueDate->greaterThanOrEqualTo($today)) {
            return 'not_due';
        }

        $days = (int) $dueDate->diffInDays($today);

        return match (true) {
            $days <= 30 => 'days_1_30',
            $days <= 60 => 'days_31_60',
            $days <= 90 => 'days_61_90',
            default => 'days_over_90',
        };
    }
}
