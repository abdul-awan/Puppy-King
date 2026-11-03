<?php

namespace App\Support\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\TaxAuthority;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The figures for the sales tax returns of a period (in the team's currency):
 * output tax on issued invoices minus credit notes, input tax on approved bills,
 * and the income and sales tax withheld by customers and from suppliers.
 */
final class SalesTaxReport
{
    /**
     * Get the report for the period.
     *
     * @return array{
     *     output: list<array{authority: string, authority_label: string, name: string, rate: string, net: string, tax: string}>,
     *     further_tax: string,
     *     input: list<array{authority: string, authority_label: string, name: string, rate: string, net: string, tax: string}>,
     *     input_not_claimable: string,
     *     authorities: list<array{authority: string, label: string, output_tax: string, input_tax: string, net: string}>,
     *     withheld_by_customers: array{income_tax: string, sales_tax: string},
     *     withheld_from_suppliers: array{income_tax: string, sales_tax: string},
     *     totals: array{output_tax: string, input_tax: string, net: string}
     * }
     */
    public static function for(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $output = [];

        foreach (['invoices' => 1.0, 'credit_notes' => -1.0] as $table => $sign) {
            $rows = RevenueReport::issuedLines($table, $start, $end)
                ->leftJoin('tax_rates', 'tax_rates.id', '=', 'document_lines.tax_rate_id')
                ->groupBy('tax_rates.authority', 'document_lines.tax_name', 'document_lines.tax_rate')
                ->select(['tax_rates.authority', 'document_lines.tax_name', 'document_lines.tax_rate'])
                ->selectRaw("SUM(document_lines.net_amount * {$table}.exchange_rate) as net")
                ->selectRaw("SUM(document_lines.tax_amount * {$table}.exchange_rate) as tax")
                ->get();

            foreach ($rows as $row) {
                self::add($output, $row->authority, $row->tax_name, $row->tax_rate, $sign * (float) $row->net, $sign * (float) $row->tax);
            }
        }

        // Further tax (on supplies to unregistered buyers) is federal sales tax.
        $furtherTax = (float) DateRange::apply(Invoice::query()->issued(), 'date', [$start, $end])->sum(DB::raw('further_tax_amount * exchange_rate'))
            - (float) DateRange::apply(CreditNote::query()->issued(), 'date', [$start, $end])->sum(DB::raw('further_tax_amount * exchange_rate'));

        $expenses = DateRange::apply(Expense::query()->where('expenses.status', ExpenseStatus::Approved), 'expenses.date', [$start, $end]);

        $input = [];
        $inputRows = (clone $expenses)
            ->where('expenses.tax_claimable', true)
            ->where('expenses.tax_amount', '!=', 0)
            ->toBase()
            ->leftJoin('tax_rates', 'tax_rates.id', '=', 'expenses.tax_rate_id')
            ->groupBy('tax_rates.authority', 'tax_rates.name', 'expenses.tax_rate')
            ->select(['tax_rates.authority', 'tax_rates.name as tax_name', 'expenses.tax_rate'])
            ->selectRaw('SUM(expenses.net_amount * expenses.exchange_rate) as net')
            ->selectRaw('SUM(expenses.tax_amount * expenses.exchange_rate) as tax')
            ->get();

        foreach ($inputRows as $row) {
            self::add($input, $row->authority, $row->tax_name, $row->tax_rate, (float) $row->net, (float) $row->tax);
        }

        $notClaimable = (float) (clone $expenses)->where('expenses.tax_claimable', false)->sum(DB::raw('expenses.tax_amount * expenses.exchange_rate'));

        $byCustomers = InvoicePayment::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->whereBetween('invoice_payments.date', [$start->startOfDay(), $end->endOfDay()])
            ->toBase()
            ->selectRaw('SUM(invoice_payments.income_tax_withheld * invoices.exchange_rate) as income_tax')
            ->selectRaw('SUM(invoice_payments.sales_tax_withheld * invoices.exchange_rate) as sales_tax')
            ->first();

        $fromSuppliers = (clone $expenses)
            ->toBase()
            ->selectRaw('SUM(expenses.income_tax_withheld * expenses.exchange_rate) as income_tax')
            ->selectRaw('SUM(expenses.sales_tax_withheld * expenses.exchange_rate) as sales_tax')
            ->first();

        $outputTax = array_sum(array_column($output, 'tax')) + $furtherTax;
        $inputTax = array_sum(array_column($input, 'tax'));

        return [
            'output' => self::present($output),
            'further_tax' => Sql::money($furtherTax),
            'input' => self::present($input),
            'input_not_claimable' => Sql::money($notClaimable),
            'authorities' => self::perAuthority($output, $input, $furtherTax),
            'withheld_by_customers' => [
                'income_tax' => Sql::money($byCustomers?->income_tax),
                'sales_tax' => Sql::money($byCustomers?->sales_tax),
            ],
            'withheld_from_suppliers' => [
                'income_tax' => Sql::money($fromSuppliers?->income_tax),
                'sales_tax' => Sql::money($fromSuppliers?->sales_tax),
            ],
            'totals' => [
                'output_tax' => Sql::money($outputTax),
                'input_tax' => Sql::money($inputTax),
                'net' => Sql::money($outputTax - $inputTax),
            ],
        ];
    }

    /**
     * Get the name of a tax authority.
     */
    public static function authorityLabel(string $authority): string
    {
        return TaxAuthority::tryFrom($authority)?->label() ?? 'No tax authority';
    }

    /**
     * Add amounts to the row of their authority and rate.
     *
     * @param  array<string, array{authority: string, name: string, rate: float, net: float, tax: float}>  $rows
     */
    private static function add(array &$rows, mixed $authority, mixed $name, mixed $rate, float $net, float $tax): void
    {
        $authority = is_string($authority) && $authority !== '' ? $authority : 'none';
        $rate = is_numeric($rate) ? (float) $rate : 0.0;
        $name = is_string($name) ? $name : '';
        $key = $authority.'|'.number_format($rate, 3, '.', '').'|'.$name;

        $rows[$key] ??= ['authority' => $authority, 'name' => $name, 'rate' => $rate, 'net' => 0.0, 'tax' => 0.0];
        $rows[$key]['net'] += $net;
        $rows[$key]['tax'] += $tax;
    }

    /**
     * Sort and format the rows: per authority, highest rate first.
     *
     * @param  array<string, array{authority: string, name: string, rate: float, net: float, tax: float}>  $rows
     * @return list<array{authority: string, authority_label: string, name: string, rate: string, net: string, tax: string}>
     */
    private static function present(array $rows): array
    {
        usort($rows, fn (array $a, array $b) => [$a['authority'], -$a['rate']] <=> [$b['authority'], -$b['rate']]);

        return array_map(function (array $row) {
            $rate = rtrim(rtrim(number_format($row['rate'], 3, '.', ''), '0'), '.');

            return [
                'authority' => $row['authority'],
                'authority_label' => self::authorityLabel($row['authority']),
                'name' => $row['name'] !== '' ? $row['name'] : ($row['rate'] > 0 ? "Tax {$rate}%" : 'No tax'),
                'rate' => $rate,
                'net' => Sql::money($row['net']),
                'tax' => Sql::money($row['tax']),
            ];
        }, $rows);
    }

    /**
     * The output tax, input tax and balance per tax authority (each files its own return).
     *
     * @param  array<string, array{authority: string, name: string, rate: float, net: float, tax: float}>  $output
     * @param  array<string, array{authority: string, name: string, rate: float, net: float, tax: float}>  $input
     * @return list<array{authority: string, label: string, output_tax: string, input_tax: string, net: string}>
     */
    private static function perAuthority(array $output, array $input, float $furtherTax): array
    {
        $authorities = [];

        foreach ([['output', $output], ['input', $input]] as [$side, $rows]) {
            foreach ($rows as $row) {
                $authorities[$row['authority']] ??= ['output' => 0.0, 'input' => 0.0];
                $authorities[$row['authority']][$side] += $row['tax'];
            }
        }

        if (abs($furtherTax) >= 0.005) {
            $authorities[TaxAuthority::Fbr->value] ??= ['output' => 0.0, 'input' => 0.0];
            $authorities[TaxAuthority::Fbr->value]['output'] += $furtherTax;
        }

        ksort($authorities);
        $result = [];

        foreach ($authorities as $authority => $amounts) {
            if (abs($amounts['output']) < 0.005 && abs($amounts['input']) < 0.005) {
                continue;
            }

            $result[] = [
                'authority' => (string) $authority,
                'label' => self::authorityLabel((string) $authority),
                'output_tax' => Sql::money($amounts['output']),
                'input_tax' => Sql::money($amounts['input']),
                'net' => Sql::money($amounts['output'] - $amounts['input']),
            ];
        }

        return $result;
    }
}
