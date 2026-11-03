<?php

namespace App\Support\Documents;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculates line and document totals for quotations, invoices and credit notes.
 *
 * Per priced line:
 *   subtotal   = round(quantity × unit price × (1 − line discount), 2)
 *   net amount = round(subtotal × (1 − document discount), 2)
 *   tax        = round(net amount × tax rate, 2)
 *   total      = net amount + tax
 *
 * Document: net total = Σ net amounts, tax total = Σ taxes, further tax is
 * charged on the net amount of the taxed lines, and the grand total is
 * net total + tax total + further tax. All rounding is half-up.
 */
final class DocumentCalculator
{
    /**
     * Calculate the totals.
     *
     * @param  array<int, array{type?: string, quantity?: mixed, unit_price?: mixed, discount_percent?: mixed, tax_rate?: mixed, tax_name?: string|null}>  $lines
     * @return array{
     *     lines: array<int, array{subtotal: string, net_amount: string, tax_amount: string, total: string}>,
     *     subtotal: string,
     *     discount_amount: string,
     *     net_total: string,
     *     tax_total: string,
     *     further_tax_amount: string,
     *     total: string,
     *     taxes: array<int, array{name: string, rate: string, net: string, tax: string}>,
     * }
     */
    public function calculate(array $lines, mixed $discountPercent = 0, mixed $furtherTaxRate = null): array
    {
        $documentDiscount = $this->fraction($discountPercent);
        $subtotal = BigDecimal::zero();
        $netTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $taxableNet = BigDecimal::zero();
        $results = [];
        $taxes = [];

        foreach ($lines as $index => $line) {
            if (($line['type'] ?? 'item') !== 'item') {
                $results[$index] = ['subtotal' => '0.00', 'net_amount' => '0.00', 'tax_amount' => '0.00', 'total' => '0.00'];

                continue;
            }

            $rate = $this->decimal($line['tax_rate'] ?? 0);

            $lineSubtotal = $this->decimal($line['quantity'] ?? 0)
                ->multipliedBy($this->decimal($line['unit_price'] ?? 0))
                ->multipliedBy(BigDecimal::one()->minus($this->fraction($line['discount_percent'] ?? 0)))
                ->toScale(2, RoundingMode::HalfUp);

            $lineNet = $lineSubtotal
                ->multipliedBy(BigDecimal::one()->minus($documentDiscount))
                ->toScale(2, RoundingMode::HalfUp);

            $lineTax = $lineNet->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp);

            $subtotal = $subtotal->plus($lineSubtotal);
            $netTotal = $netTotal->plus($lineNet);
            $taxTotal = $taxTotal->plus($lineTax);

            if ($rate->isPositive()) {
                $taxableNet = $taxableNet->plus($lineNet);
            }

            $key = $rate->toScale(3).'|'.($line['tax_name'] ?? '');
            $taxes[$key] ??= ['name' => (string) ($line['tax_name'] ?? ''), 'rate' => (string) $rate->toScale(3), 'net' => BigDecimal::zero(), 'tax' => BigDecimal::zero()];
            $taxes[$key]['net'] = $taxes[$key]['net']->plus($lineNet);
            $taxes[$key]['tax'] = $taxes[$key]['tax']->plus($lineTax);

            $results[$index] = [
                'subtotal' => (string) $lineSubtotal,
                'net_amount' => (string) $lineNet,
                'tax_amount' => (string) $lineTax,
                'total' => (string) $lineNet->plus($lineTax),
            ];
        }

        $furtherTax = $furtherTaxRate === null || $furtherTaxRate === ''
            ? BigDecimal::zero()->toScale(2)
            : $taxableNet->multipliedBy($this->decimal($furtherTaxRate))->dividedBy(100, 2, RoundingMode::HalfUp);

        return [
            'lines' => $results,
            'subtotal' => (string) $subtotal->toScale(2),
            'discount_amount' => (string) $subtotal->minus($netTotal)->toScale(2),
            'net_total' => (string) $netTotal->toScale(2),
            'tax_total' => (string) $taxTotal->toScale(2),
            'further_tax_amount' => (string) $furtherTax,
            'total' => (string) $netTotal->plus($taxTotal)->plus($furtherTax)->toScale(2),
            'taxes' => array_values(array_map(fn (array $tax) => [
                'name' => $tax['name'],
                'rate' => $tax['rate'],
                'net' => (string) $tax['net']->toScale(2),
                'tax' => (string) $tax['tax']->toScale(2),
            ], $taxes)),
        ];
    }

    /**
     * Convert any numeric value to a BigDecimal (empty values are zero).
     */
    private function decimal(mixed $value): BigDecimal
    {
        if ($value === null || $value === '' || $value === false) {
            return BigDecimal::zero();
        }

        return BigDecimal::of(is_float($value) ? sprintf('%.10F', $value) : (string) $value);
    }

    /**
     * Convert a percentage (e.g. 12.5) to a fraction (0.125).
     */
    private function fraction(mixed $percent): BigDecimal
    {
        return $this->decimal($percent)->dividedBy(100, 8, RoundingMode::HalfUp);
    }
}
