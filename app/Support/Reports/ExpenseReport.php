<?php

namespace App\Support\Reports;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

/**
 * The approved bills of a period (in the team's currency), per category and per supplier.
 */
final class ExpenseReport
{
    /**
     * Get the report for the period.
     *
     * @return array{
     *     categories: list<array{id: int|null, name: string, count: int, net: string, tax: string, total: string}>,
     *     suppliers: list<array{name: string, company_id: int|null, count: int, total: string}>,
     *     totals: array{count: int, net: string, tax: string, total: string}
     * }
     */
    public static function for(CarbonImmutable $start, CarbonImmutable $end, ?int $limit = 10): array
    {
        $expenses = DateRange::apply(Expense::query()->where('status', ExpenseStatus::Approved), 'date', [$start, $end]);

        $perCategory = (clone $expenses)
            ->toBase()
            ->groupBy('category_id')
            ->select('category_id')
            ->selectRaw('COUNT(*) as bills')
            ->selectRaw('SUM(net_amount * exchange_rate) as net')
            ->selectRaw('SUM(tax_amount * exchange_rate) as tax')
            ->selectRaw('SUM(total * exchange_rate) as total')
            ->get();

        $names = ExpenseCategory::query()->whereKey($perCategory->pluck('category_id')->filter()->all())->pluck('name', 'id');
        $categories = [];
        $totals = ['count' => 0, 'net' => 0.0, 'tax' => 0.0, 'total' => 0.0];

        foreach ($perCategory as $row) {
            $id = $row->category_id !== null ? (int) $row->category_id : null;

            $categories[] = [
                'id' => $id,
                'name' => $id !== null ? (string) ($names[$id] ?? 'Deleted category') : 'No category',
                'count' => (int) $row->bills,
                'amount' => (float) $row->total,
                'net' => Sql::money($row->net),
                'tax' => Sql::money($row->tax),
                'total' => Sql::money($row->total),
            ];

            $totals['count'] += (int) $row->bills;
            $totals['net'] += (float) $row->net;
            $totals['tax'] += (float) $row->tax;
            $totals['total'] += (float) $row->total;
        }

        usort($categories, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        $perSupplier = (clone $expenses)
            ->toBase()
            ->groupBy('company_id', 'supplier_name')
            ->select(['company_id', 'supplier_name'])
            ->selectRaw('COUNT(*) as bills')
            ->selectRaw('SUM(total * exchange_rate) as total')
            ->get();

        $suppliers = [];

        foreach ($perSupplier as $row) {
            $companyId = $row->company_id !== null ? (int) $row->company_id : null;
            $key = $companyId !== null ? "company:{$companyId}" : 'name:'.mb_strtolower(trim((string) $row->supplier_name));

            $suppliers[$key] ??= ['name' => (string) ($row->supplier_name ?? '—'), 'company_id' => $companyId, 'count' => 0, 'amount' => 0.0];
            $suppliers[$key]['count'] += (int) $row->bills;
            $suppliers[$key]['amount'] += (float) $row->total;
        }

        usort($suppliers, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        return [
            'categories' => array_map(fn (array $category) => [
                'id' => $category['id'],
                'name' => $category['name'],
                'count' => $category['count'],
                'net' => $category['net'],
                'tax' => $category['tax'],
                'total' => $category['total'],
            ], $categories),
            'suppliers' => array_map(fn (array $supplier) => [
                'name' => $supplier['name'],
                'company_id' => $supplier['company_id'],
                'count' => $supplier['count'],
                'total' => Sql::money($supplier['amount']),
            ], array_slice($suppliers, 0, $limit)),
            'totals' => [
                'count' => $totals['count'],
                'net' => Sql::money($totals['net']),
                'tax' => Sql::money($totals['tax']),
                'total' => Sql::money($totals['total']),
            ],
        ];
    }
}
