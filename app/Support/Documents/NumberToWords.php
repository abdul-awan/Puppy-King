<?php

namespace App\Support\Documents;

/**
 * Writes amounts in words for documents, e.g. "Rupees one hundred twenty thousand and fifty paisa only".
 */
final class NumberToWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = ['', 'thousand', 'million', 'billion', 'trillion'];

    /**
     * The main unit and the fractional unit for each currency.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const CURRENCY_UNITS = [
        'PKR' => ['Rupees', 'paisa'],
        'USD' => ['US dollars', 'cents'],
        'EUR' => ['Euros', 'cents'],
        'GBP' => ['Pounds', 'pence'],
        'AED' => ['Dirhams', 'fils'],
        'SAR' => ['Riyals', 'halalas'],
        'CNY' => ['Yuan', 'fen'],
        'CAD' => ['Canadian dollars', 'cents'],
        'AUD' => ['Australian dollars', 'cents'],
    ];

    /**
     * Write a money amount in words, with thousands and millions or (South Asian) lakhs and crores.
     */
    public static function money(string|int|float $amount, string $currency = 'PKR', bool $southAsian = false): string
    {
        $negative = (float) $amount < 0;
        [$whole, $fraction] = array_pad(explode('.', number_format(abs((float) $amount), 2, '.', '')), 2, '00');
        [$unit, $subunit] = self::CURRENCY_UNITS[$currency] ?? [$currency, 'cents'];
        $integer = $southAsian ? self::southAsian(...) : self::integer(...);

        $words = $unit.' '.$integer((int) $whole);

        if ((int) $fraction > 0) {
            $words .= ' and '.$integer((int) $fraction).' '.$subunit;
        }

        return ($negative ? 'Minus ' : '').$words.' only';
    }

    /**
     * Write a whole number in words with lakhs (100,000) and crores (10,000,000), e.g. "twelve lakh thirty-four thousand".
     */
    public static function southAsian(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $parts = [];
        $crores = intdiv($number, 10_000_000);
        $number %= 10_000_000;

        if ($crores > 0) {
            $parts[] = self::southAsian($crores).' crore';
        }

        foreach ([100_000 => 'lakh', 1_000 => 'thousand'] as $size => $name) {
            if ($number >= $size) {
                $parts[] = self::hundreds(intdiv($number, $size)).' '.$name;
                $number %= $size;
            }
        }

        if ($number > 0) {
            $parts[] = self::hundreds($number);
        }

        return implode(' ', $parts);
    }

    /**
     * Write a whole number in words.
     */
    public static function integer(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $parts = [];
        $scale = 0;

        while ($number > 0) {
            $chunk = $number % 1000;

            if ($chunk > 0) {
                $parts[] = trim(self::hundreds($chunk).' '.self::SCALES[$scale]);
            }

            $number = intdiv($number, 1000);
            $scale++;
        }

        return implode(' ', array_reverse($parts));
    }

    /**
     * Write a number below one thousand in words.
     */
    private static function hundreds(int $number): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = self::ONES[intdiv($number, 100)].' hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = self::TENS[intdiv($number, 10)].($number % 10 ? '-'.self::ONES[$number % 10] : '');
        } elseif ($number > 0) {
            $words[] = self::ONES[$number];
        }

        return implode(' ', $words);
    }
}
