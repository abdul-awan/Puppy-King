<?php

use App\Support\Documents\DocumentCalculator;
use App\Support\Documents\NumberToWords;

test('lines are priced with line discount, document discount and tax', function () {
    $result = (new DocumentCalculator)->calculate([
        ['type' => 'item', 'quantity' => '3', 'unit_price' => '1000', 'discount_percent' => '10', 'tax_rate' => '18', 'tax_name' => 'Sales tax 18%'],
        ['type' => 'section'],
        ['type' => 'item', 'quantity' => '2', 'unit_price' => '499.99', 'discount_percent' => '0', 'tax_rate' => '0', 'tax_name' => 'Exempt'],
    ], discountPercent: '5');

    expect($result['lines'][0])->toBe(['subtotal' => '2700.00', 'net_amount' => '2565.00', 'tax_amount' => '461.70', 'total' => '3026.70'])
        ->and($result['lines'][1])->toBe(['subtotal' => '0.00', 'net_amount' => '0.00', 'tax_amount' => '0.00', 'total' => '0.00'])
        ->and($result['lines'][2])->toBe(['subtotal' => '999.98', 'net_amount' => '949.98', 'tax_amount' => '0.00', 'total' => '949.98'])
        ->and($result['subtotal'])->toBe('3699.98')
        ->and($result['discount_amount'])->toBe('185.00')
        ->and($result['net_total'])->toBe('3514.98')
        ->and($result['tax_total'])->toBe('461.70')
        ->and($result['further_tax_amount'])->toBe('0.00')
        ->and($result['total'])->toBe('3976.68')
        ->and($result['taxes'])->toBe([
            ['name' => 'Sales tax 18%', 'rate' => '18.000', 'net' => '2565.00', 'tax' => '461.70'],
            ['name' => 'Exempt', 'rate' => '0.000', 'net' => '949.98', 'tax' => '0.00'],
        ]);
});

test('further tax is charged on the taxed lines only', function () {
    $result = (new DocumentCalculator)->calculate([
        ['type' => 'item', 'quantity' => '1', 'unit_price' => '10000', 'tax_rate' => '18'],
        ['type' => 'item', 'quantity' => '1', 'unit_price' => '5000', 'tax_rate' => '0'],
    ], 0, '4.00');

    expect($result['tax_total'])->toBe('1800.00')
        ->and($result['further_tax_amount'])->toBe('400.00')
        ->and($result['total'])->toBe('17200.00');
});

test('amounts are rounded half up per line', function () {
    $result = (new DocumentCalculator)->calculate([
        ['type' => 'item', 'quantity' => '3', 'unit_price' => '33.335', 'tax_rate' => '18'],
    ]);

    expect($result['lines'][0]['subtotal'])->toBe('100.01')
        ->and($result['lines'][0]['tax_amount'])->toBe('18.00')
        ->and($result['total'])->toBe('118.01');
});

test('empty quantities and prices count as zero', function () {
    $result = (new DocumentCalculator)->calculate([
        ['type' => 'item', 'quantity' => '', 'unit_price' => null, 'tax_rate' => '18'],
    ]);

    expect($result['total'])->toBe('0.00');
});

test('amounts are written in words', function () {
    expect(NumberToWords::money('1234567.50', 'PKR'))
        ->toBe('Rupees one million two hundred thirty-four thousand five hundred sixty-seven and fifty paisa only')
        ->and(NumberToWords::money('100', 'USD'))->toBe('US dollars one hundred only')
        ->and(NumberToWords::money('-5', 'PKR'))->toBe('Minus Rupees five only');
});
