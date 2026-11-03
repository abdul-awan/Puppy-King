<?php

namespace App\Support\Documents\Templates;

use App\Models\CreditNote;
use App\Models\DocumentLine;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesDocument;
use App\Models\TaxRate;
use App\Models\Team;
use App\Support\Documents\DocumentCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * An example quotation, invoice or credit note with the company's own details,
 * used to preview templates. It is built in memory and never saved.
 */
final class SampleDocument
{
    public function __construct(private DocumentCalculator $calculator)
    {
        //
    }

    /**
     * Build an example document of a kind ("invoice", "quotation" or "credit_note").
     */
    public function make(Team $team, string $type): SalesDocument
    {
        $tax = TaxRate::query()->withoutGlobalScopes()->where('team_id', $team->id)->where('is_default', true)->first();
        $rate = $tax !== null && (float) $tax->rate > 0 ? (string) $tax->rate : '18';
        $taxName = $tax !== null && (float) $tax->rate > 0 ? $tax->name : 'Sales tax 18%';
        $today = CarbonImmutable::today();

        $lines = $type === 'credit_note'
            ? [
                ['type' => 'item', 'description' => 'Ergonomic office chair', 'details' => 'Returned: delivered damaged', 'quantity' => '2', 'unit' => 'pcs', 'unit_price' => '25000', 'discount_percent' => '0', 'tax_rate' => $rate, 'tax_name' => $taxName, 'hs_code' => '9401.3000'],
            ]
            : [
                ['type' => 'section', 'description' => 'Office furniture'],
                ['type' => 'item', 'description' => 'Ergonomic office chair', 'details' => 'Black mesh, adjustable arms', 'quantity' => '10', 'unit' => 'pcs', 'unit_price' => '25000', 'discount_percent' => '0', 'tax_rate' => $rate, 'tax_name' => $taxName, 'hs_code' => '9401.3000'],
                ['type' => 'item', 'description' => 'Standing desk 160 × 80 cm', 'details' => null, 'quantity' => '4', 'unit' => 'pcs', 'unit_price' => '62500', 'discount_percent' => '5', 'tax_rate' => $rate, 'tax_name' => $taxName, 'hs_code' => '9403.1000'],
                ['type' => 'section', 'description' => 'Services'],
                ['type' => 'item', 'description' => 'Delivery and installation', 'details' => null, 'quantity' => '1', 'unit' => 'job', 'unit_price' => '15000', 'discount_percent' => '0', 'tax_rate' => '0', 'tax_name' => null, 'hs_code' => null],
                ['type' => 'text', 'description' => 'Installation within three working days after delivery.'],
            ];

        $result = $this->calculator->calculate($lines, '0', null);

        $document = match ($type) {
            'quotation' => new Quotation,
            'credit_note' => new CreditNote,
            default => new Invoice,
        };

        $document->forceFill([
            'team_id' => $team->id,
            'date' => $today,
            'currency' => $team->teamSettings()->currency(),
            'exchange_rate' => '1',
            'discount_percent' => '0',
            'further_tax_rate' => null,
            'subtotal' => $result['subtotal'],
            'discount_amount' => $result['discount_amount'],
            'tax_total' => $result['tax_total'],
            'further_tax_amount' => $result['further_tax_amount'],
            'total' => $result['total'],
            'customer_name' => 'Example Traders (Pvt) Ltd',
            'customer_address' => "Plot 12, Street 4, I-9/3\nIslamabad",
            'customer_email' => 'accounts@example.com',
            'customer_ntn' => '1234567-8',
            'customer_strn' => '17-00-1234-567-89',
            'customer_registered' => true,
            'public_token' => 'preview',
        ]);

        match (true) {
            $document instanceof Invoice => $document->forceFill([
                'number' => 'INV-'.$today->format('Y').'-0042',
                'status' => 'open',
                'title' => 'Furniture for the new branch office',
                'due_date' => $today->addDays(30),
                'amount_paid' => '100000.00',
                'amount_credited' => '0.00',
                'terms' => 'Payment within 30 days. The goods remain our property until they are paid in full.',
            ]),
            $document instanceof Quotation => $document->forceFill([
                'number' => 'QT-'.$today->format('Y').'-0015',
                'status' => 'sent',
                'title' => 'Furniture for the new branch office',
                'valid_until' => $today->addDays(30),
                'introduction' => 'Thank you for your enquiry. We are pleased to offer the following:',
                'terms' => 'Prices are valid for 30 days. Delivery within two weeks after confirmation.',
            ]),
            default => $document->forceFill([
                'number' => 'CN-'.$today->format('Y').'-0003',
                'status' => 'issued',
                'reason' => 'Two chairs were delivered damaged and have been returned.',
            ]),
        };

        if ($document instanceof CreditNote) {
            $document->setRelation('invoice', (new Invoice)->forceFill(['number' => 'INV-'.$today->format('Y').'-0042', 'date' => $today->subDays(10)]));
        }

        $models = [];

        foreach ($lines as $index => $line) {
            $model = (new DocumentLine)->forceFill([...$line, ...$result['lines'][$index], 'team_id' => $team->id, 'sort' => $index]);
            $model->setRelation('product', null);
            $models[] = $model;
        }

        $document->setRelation('lines', new Collection($models));
        $document->setRelation('department', $team->defaultDepartment());
        $document->setRelation('team', $team);

        return $document;
    }
}
