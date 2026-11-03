<?php

namespace App\Http\Requests\Concerns;

use App\Enums\LineType;
use App\Support\TeamRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation of the lines of quotations, invoices and credit notes.
 */
trait ValidatesDocumentLines
{
    /**
     * Get the rules for the "lines" list.
     *
     * @return array<string, mixed>
     */
    protected function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.type' => ['required', Rule::enum(LineType::class)],
            'lines.*.product_id' => ['nullable', 'integer', TeamRule::exists('products')],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.details' => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity' => ['nullable', 'required_if:lines.*.type,item', 'numeric', 'between:-999999999,999999999'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['nullable', 'required_if:lines.*.type,item', 'numeric', 'between:-99999999999,99999999999'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate_id' => ['nullable', 'integer', TeamRule::exists('tax_rates')],
            'lines.*.hs_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Require at least one priced line.
     */
    protected function requireItemLine(Validator $validator): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            if (is_array($line) && ($line['type'] ?? null) === LineType::Item->value) {
                return;
            }
        }

        $validator->errors()->add('lines', 'Add at least one line with an amount.');
    }

    /**
     * Get custom messages for the line rules.
     *
     * @return array<string, string>
     */
    protected function lineMessages(): array
    {
        return [
            'lines.*.description.required' => 'Every line needs a description.',
            'lines.*.quantity.required_if' => 'Enter a quantity.',
            'lines.*.unit_price.required_if' => 'Enter a price.',
        ];
    }
}
