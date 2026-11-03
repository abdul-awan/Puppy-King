<?php

namespace App\Support\Documents\Templates;

use App\Enums\LineType;
use App\Models\DocumentLine;
use App\Models\SalesDocument;
use App\Support\Documents\NumberToWords;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Works out what a quotation, invoice or credit note shows with the company's
 * document options: the title, the details, the columns and values of the
 * lines and the total rows, all formatted. The PDF templates and the
 * customer's web page both print this, so they always agree.
 *
 * When tax is not shown, the lines and totals include tax: each line shows
 * its value plus tax (before a document discount), and the discount row is
 * the difference to the taxed total, so the rows always add up exactly.
 */
final class DocumentPresenter
{
    /**
     * Present the document.
     *
     * @param  array<string, mixed>  $extra  the document's extra blocks (see SalesDocument::pdfExtra())
     * @return array{
     *     title: string,
     *     meta: list<array{label: string, value: string}>,
     *     customer_heading: string,
     *     columns: list<array{key: string, label: string, align: string, width: string|null}>,
     *     rows: list<array<string, mixed>>,
     *     totals: list<array{label: string, value: string, style: string}>,
     *     tax_note: string|null,
     *     amount_in_words: string|null,
     *     terms: string|null,
     * }
     */
    public function present(SalesDocument $document, DocumentOptions $options, array $extra = []): array
    {
        /** @var Collection<int, DocumentLine> $lines */
        $lines = $document->relationLoaded('lines') ? $document->lines : $document->lines()->get();
        $items = $lines->filter(fn (DocumentLine $line) => $line->type === LineType::Item);
        $hasTax = $items->contains(fn (DocumentLine $line) => (float) $line->tax_rate > 0) || (float) $document->further_tax_amount > 0;
        $taxColumns = $options->showTax && $hasTax ? $options->taxColumns : 'none';
        $inclusive = ! $options->showTax && $hasTax;

        $columns = $this->columns($options, $items, $taxColumns, $inclusive, $document->currency);

        return [
            'title' => $this->title($document, $options),
            'meta' => $this->meta($document, $options),
            'customer_heading' => $options->customerHeading ?? (string) ($extra['customer_label'] ?? $this->customerHeading($document)),
            'columns' => $columns,
            'rows' => $this->rows($lines, $options, $columns, $inclusive),
            'totals' => [
                ...($inclusive ? $this->inclusiveTotals($document, $items, $options) : $this->totals($document, $options, $hasTax)),
                ...$this->balanceRows($extra, $options, $document->currency),
            ],
            'tax_note' => $inclusive && (float) $document->tax_total > 0 ? 'All amounts include sales tax.' : null,
            'amount_in_words' => $options->amountInWords
                ? NumberToWords::money($document->total, $document->currency, $options->numberFormat === 'south_asian')
                : null,
            'terms' => $options->terms && filled($document->getAttribute('terms')) ? (string) $document->getAttribute('terms') : null,
        ];
    }

    /**
     * Get the title: the company's own title for this kind of document, or the automatic one.
     */
    public function title(SalesDocument $document, DocumentOptions $options): string
    {
        $title = $options->titleFor($document->documentType()->value) ?? $document->documentTitle();

        // A sales tax invoice has to show the tax; without it the document is a plain invoice.
        return ! $options->showTax && $title === 'Sales Tax Invoice' ? 'Invoice' : $title;
    }

    /**
     * Get the document details (number, dates, ...) with the chosen date format.
     *
     * @return list<array{label: string, value: string}>
     */
    private function meta(SalesDocument $document, DocumentOptions $options): array
    {
        $meta = [];

        foreach ($document->documentMeta($options->dateFormat) as $label => $value) {
            if (filled($value)) {
                $meta[] = ['label' => $label, 'value' => (string) $value];
            }
        }

        return $meta;
    }

    /**
     * Get the default heading above the customer's details.
     */
    private function customerHeading(SalesDocument $document): string
    {
        return match ($document->documentType()->value) {
            'invoice' => 'Bill to',
            'quotation' => 'Prepared for',
            default => 'Customer',
        };
    }

    /**
     * Get the columns of the lines table.
     *
     * @param  Collection<int, DocumentLine>  $items
     * @return list<array{key: string, label: string, align: string, width: string|null}>
     */
    private function columns(DocumentOptions $options, Collection $items, string $taxColumns, bool $inclusive, string $currency): array
    {
        $column = fn (string $key, string $label, string $align = 'right', ?string $width = null) => compact('key', 'label', 'align', 'width');
        $columns = [];

        if ($options->itemNumbers) {
            $columns[] = $column('number', '#', 'left', '4%');
        }

        $columns[] = $column('description', 'Description', 'left');

        if ($options->hsCodes && $items->contains(fn (DocumentLine $line) => filled($line->hs_code))) {
            $columns[] = $column('hs_code', 'HS code', 'left', '9%');
        }

        if ($options->quantityPrice) {
            $columns[] = $column('quantity', 'Qty', 'right', '9%');
            $columns[] = $column('unit_price', $inclusive ? 'Price incl. tax' : 'Unit price', 'right', '12%');
        }

        if ($options->discountColumn && $items->contains(fn (DocumentLine $line) => (float) $line->discount_percent > 0)) {
            $columns[] = $column('discount', 'Disc.', 'right', '7%');
        }

        if ($taxColumns === 'amounts') {
            // Many columns: the amounts take the width they need and the description keeps a fair share.
            foreach ($columns as $index => $existing) {
                $columns[$index]['width'] = match (true) {
                    $existing['key'] === 'description' => '25%',
                    $existing['align'] === 'right' => null,
                    default => $existing['width'],
                };
            }

            $columns[] = $column('net', 'Value excl. tax');
            $columns[] = $column('tax_rate', 'Tax');
            $columns[] = $column('tax', 'Tax amount');
            $columns[] = $column('amount', 'Value incl. tax');
        } else {
            if ($taxColumns === 'rate') {
                $columns[] = $column('tax_rate', 'Tax', 'right', '7%');
            }

            $columns[] = $column('amount', 'Amount ('.trim($options->currency($currency)).')', 'right', '14%');
        }

        return $columns;
    }

    /**
     * Get the rows of the lines table with a formatted value per column.
     *
     * @param  Collection<int, DocumentLine>  $lines
     * @param  list<array{key: string, label: string, align: string, width: string|null}>  $columns
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $lines, DocumentOptions $options, array $columns, bool $inclusive): array
    {
        $keys = array_column($columns, 'key');
        $rows = [];
        $number = 0;

        foreach ($lines as $line) {
            if ($line->type !== LineType::Item) {
                $rows[] = ['type' => $line->type->value, 'description' => $line->description];

                continue;
            }

            $number++;
            $taxed = (float) $line->tax_rate > 0;
            $cells = [];

            foreach ($keys as $key) {
                $cells[$key] = match ($key) {
                    'number' => (string) $number,
                    'hs_code' => (string) $line->hs_code,
                    'quantity' => trim($options->quantity($line->quantity).' '.($options->units ? (string) $line->unit : '')),
                    'unit_price' => $options->money($inclusive ? $this->withTax($line->unit_price, $line->tax_rate) : $line->unit_price),
                    'discount' => (float) $line->discount_percent > 0 ? $options->percent($line->discount_percent) : '',
                    'net' => $options->money($line->net_amount),
                    'tax_rate' => $taxed ? $options->percent($line->tax_rate) : '—',
                    'tax' => $taxed ? $options->money($line->tax_amount) : '—',
                    'amount' => $options->money(match (true) {
                        in_array('net', $keys, true) => $line->total,
                        $inclusive => $this->withTax($line->subtotal, $line->tax_rate),
                        default => $line->subtotal,
                    }),
                    default => null,
                };
            }

            unset($cells['description']);

            $rows[] = [
                'type' => 'item',
                'description' => $line->description,
                'details' => $line->details,
                'code' => $options->productCodes ? $line->product?->code : null,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Get the total rows when tax is shown.
     *
     * @return list<array{label: string, value: string, style: string}>
     */
    private function totals(SalesDocument $document, DocumentOptions $options, bool $hasTax): array
    {
        $currency = $document->currency;
        $rows = [['label' => 'Subtotal', 'value' => $options->money($document->subtotal, $currency), 'style' => 'normal']];

        if ((float) $document->discount_amount != 0) {
            $rows[] = ['label' => 'Discount ('.$options->percent($document->discount_percent).')', 'value' => '− '.$options->money($document->discount_amount), 'style' => 'normal'];
            $rows[] = [
                'label' => 'Value excl. tax',
                'value' => $options->money((string) BigDecimal::of($document->subtotal)->minus($document->discount_amount), $currency),
                'style' => 'normal',
            ];
        }

        if ($hasTax) {
            $taxes = array_values(array_filter($document->taxBreakdown(), fn (array $tax) => (float) $tax['rate'] > 0));

            if ($options->taxSummary === 'single') {
                $names = array_unique(array_map(fn (array $tax) => $tax['name'], $taxes));
                $rows[] = [
                    'label' => count($names) === 1 && filled($names[0] ?? null) ? (string) $names[0] : 'Sales tax',
                    'value' => $options->money($document->tax_total),
                    'style' => 'normal',
                ];
            } else {
                foreach ($taxes as $tax) {
                    $rows[] = [
                        'label' => ($tax['name'] ?: 'Tax '.$options->percent($tax['rate'])).' on '.$options->money($tax['net']),
                        'value' => $options->money($tax['tax']),
                        'style' => 'normal',
                    ];
                }
            }

            if ((float) $document->further_tax_amount > 0) {
                $rows[] = ['label' => 'Further tax ('.$options->percent((string) $document->further_tax_rate).')', 'value' => $options->money($document->further_tax_amount), 'style' => 'normal'];
            }
        }

        $rows[] = ['label' => 'Total', 'value' => $options->money($document->total, $currency), 'style' => 'grand'];

        return $rows;
    }

    /**
     * Get the total rows when the amounts include tax.
     *
     * @param  Collection<int, DocumentLine>  $items
     * @return list<array{label: string, value: string, style: string}>
     */
    private function inclusiveTotals(SalesDocument $document, Collection $items, DocumentOptions $options): array
    {
        $currency = $document->currency;
        $gross = BigDecimal::zero();
        $taxed = BigDecimal::zero();

        foreach ($items as $line) {
            $gross = $gross->plus($this->withTax($line->subtotal, $line->tax_rate));
            $taxed = $taxed->plus($line->total);
        }

        $discount = $gross->minus($taxed);
        $furtherTax = (float) $document->further_tax_amount > 0;
        $rows = [];

        if (! $discount->isZero() || $furtherTax) {
            $rows[] = ['label' => 'Subtotal', 'value' => $options->money((string) $gross, $currency), 'style' => 'normal'];
        }

        if (! $discount->isZero()) {
            $rows[] = ['label' => 'Discount ('.$options->percent($document->discount_percent).')', 'value' => '− '.$options->money((string) $discount), 'style' => 'normal'];
        }

        if ($furtherTax) {
            $rows[] = ['label' => 'Further tax ('.$options->percent((string) $document->further_tax_rate).')', 'value' => $options->money($document->further_tax_amount), 'style' => 'normal'];
        }

        $rows[] = ['label' => 'Total', 'value' => $options->money($document->total, $currency), 'style' => 'grand'];

        return $rows;
    }

    /**
     * Get the rows below the total (paid, credited, balance due).
     *
     * @param  array<string, mixed>  $extra
     * @return list<array{label: string, value: string, style: string}>
     */
    private function balanceRows(array $extra, DocumentOptions $options, string $currency): array
    {
        if (! $options->paymentSummary) {
            return [];
        }

        $rows = [];

        foreach ((array) ($extra['total_rows'] ?? []) as $row) {
            $rows[] = [
                'label' => (string) $row['label'],
                'value' => $options->money($row['value'], $currency),
                'style' => ($row['bold'] ?? false) ? 'strong' : 'normal',
            ];
        }

        return $rows;
    }

    /**
     * Add tax to an amount, rounded half-up to two decimals.
     */
    private function withTax(string $amount, string $rate): string
    {
        $value = BigDecimal::of($amount);

        return (string) $value->plus($value->multipliedBy($rate)->dividedBy(100, 2, RoundingMode::HalfUp))->toScale(2, RoundingMode::HalfUp);
    }
}
