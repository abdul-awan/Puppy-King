<?php

namespace App\Concerns;

use App\Enums\LineType;
use App\Models\DocumentLine;
use App\Models\TaxRate;
use App\Support\Documents\DocumentCalculator;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Priced lines (items, section headers, text) with calculated totals.
 *
 * Used by quotations, invoices, credit notes and recurring invoice profiles. The model
 * needs the columns team_id, discount_percent, further_tax_rate, subtotal,
 * discount_amount, tax_total, further_tax_amount and total.
 */
trait HasDocumentLines
{
    /**
     * Get the lines in order.
     *
     * @return MorphMany<DocumentLine, $this>
     */
    public function lines(): MorphMany
    {
        return $this->morphMany(DocumentLine::class, 'documentable')->orderBy('sort')->orderBy('id');
    }

    /**
     * Replace the lines and recalculate all totals.
     *
     * The tax rate of each line is copied from the team's tax rates, so later
     * changes to a rate never alter existing documents.
     *
     * @param  array<int, array<string, mixed>>  $input
     */
    public function replaceLines(array $input): void
    {
        $taxRateIds = array_values(array_filter(array_map(fn (array $line) => $line['tax_rate_id'] ?? null, $input)));

        $taxRates = TaxRate::query()
            ->withoutGlobalScopes()
            ->where('team_id', $this->team_id)
            ->whereKey($taxRateIds)
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach (array_values($input) as $sort => $line) {
            $type = LineType::tryFrom((string) ($line['type'] ?? 'item')) ?? LineType::Item;
            $isItem = $type === LineType::Item;
            $taxRate = $isItem && ! empty($line['tax_rate_id']) ? $taxRates->get((int) $line['tax_rate_id']) : null;

            $rows[] = [
                'type' => $type->value,
                'product_id' => $isItem ? ($line['product_id'] ?? null) : null,
                'description' => (string) ($line['description'] ?? ''),
                'details' => $line['details'] ?? null,
                'quantity' => $isItem ? (string) ($line['quantity'] ?? '1') : '0',
                'unit' => $isItem ? ($line['unit'] ?? null) : null,
                'unit_price' => $isItem ? (string) ($line['unit_price'] ?? '0') : '0',
                'discount_percent' => $isItem ? (string) ($line['discount_percent'] ?? '0') : '0',
                'tax_rate_id' => $taxRate?->id,
                'tax_rate' => $taxRate !== null ? (string) $taxRate->rate : '0',
                'tax_name' => $taxRate?->name,
                'hs_code' => $isItem ? ($line['hs_code'] ?? null) : null,
                'source_type' => $line['source_type'] ?? null,
                'source_id' => $line['source_id'] ?? null,
                'sort' => $sort,
            ];
        }

        $result = app(DocumentCalculator::class)->calculate($rows, $this->discount_percent, $this->further_tax_rate);

        $this->lines()->delete();

        foreach ($rows as $index => $row) {
            $this->lines()->create([...$row, ...$result['lines'][$index], 'team_id' => $this->team_id]);
        }

        $this->unsetRelation('lines');
        $this->fillTotals($result);
        $this->save();
    }

    /**
     * Recalculate the totals from the stored lines (e.g. after the discount changed).
     */
    public function recalculate(): void
    {
        $lines = $this->lines()->get()->values();
        $result = app(DocumentCalculator::class)->calculate($this->calculatorLines($lines), $this->discount_percent, $this->further_tax_rate);

        foreach ($lines as $index => $line) {
            $line->forceFill($result['lines'][$index])->save();
        }

        $this->unsetRelation('lines');
        $this->fillTotals($result);
        $this->save();
    }

    /**
     * Get the totals per tax rate.
     *
     * @return array<int, array{name: string, rate: string, net: string, tax: string}>
     */
    public function taxBreakdown(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return app(DocumentCalculator::class)->calculate($this->calculatorLines($lines->values()), $this->discount_percent, $this->further_tax_rate)['taxes'];
    }

    /**
     * Charge (or stop charging) further tax, using the team's current rate.
     */
    public function applyFurtherTax(bool $apply): void
    {
        $this->further_tax_rate = $apply ? $this->team()->firstOrFail()->teamSettings()->furtherTaxRate() : null;
    }

    /**
     * Get the lines in the shape the line form (and replaceLines) expects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function linesForCopy(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get();

        return $lines->map(fn (DocumentLine $line) => $line->toPresentation())->values()->all();
    }

    /**
     * Map lines to the calculator input.
     *
     * @param  Collection<int, DocumentLine>  $lines
     * @return array<int, array{type: string, quantity: string, unit_price: string, discount_percent: string, tax_rate: string, tax_name: string|null}>
     */
    protected function calculatorLines(Collection $lines): array
    {
        return $lines->map(fn (DocumentLine $line) => [
            'type' => $line->type->value,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'discount_percent' => $line->discount_percent,
            'tax_rate' => $line->tax_rate,
            'tax_name' => $line->tax_name,
        ])->all();
    }

    /**
     * Put calculated totals on the model.
     *
     * @param  array{subtotal: string, discount_amount: string, tax_total: string, further_tax_amount: string, total: string}  $result
     */
    protected function fillTotals(array $result): void
    {
        $this->forceFill([
            'subtotal' => $result['subtotal'],
            'discount_amount' => $result['discount_amount'],
            'tax_total' => $result['tax_total'],
            'further_tax_amount' => $result['further_tax_amount'],
            'total' => $result['total'],
        ]);
    }

    /**
     * The casts of the amount columns.
     *
     * @return array<string, string>
     */
    protected function amountCasts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'discount_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'further_tax_rate' => 'decimal:3',
            'further_tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
