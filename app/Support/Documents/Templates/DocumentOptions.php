<?php

namespace App\Support\Documents\Templates;

use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * What quotations, invoices and credit notes show, whichever template is used:
 * tax, columns, blocks, titles and number and date formats. A company sets
 * these once in its invoice settings (stored in the team settings under
 * "documents.options").
 */
final readonly class DocumentOptions
{
    /** @var array<string, string> */
    public const TAX_COLUMNS = [
        'rate' => 'Tax rate on each line',
        'amounts' => 'Tax rate, tax amount and value incl. tax on each line (FBR style)',
        'none' => 'No tax columns, tax only in the totals',
    ];

    /** @var array<string, string> */
    public const TAX_SUMMARIES = [
        'per_rate' => 'One line per tax rate, with the taxable value',
        'single' => 'One line with all tax together',
    ];

    /** @var array<string, string> */
    public const SIGNATURES = [
        'none' => 'No signature lines',
        'single' => 'Authorised signature',
        'double' => 'Received by + authorised signature',
    ];

    /** @var array<string, string> */
    public const DATE_FORMATS = [
        'd M Y' => '30 Sep 2026',
        'd/m/Y' => '30/09/2026',
        'd-m-Y' => '30-09-2026',
        'd.m.Y' => '30.09.2026',
        'Y-m-d' => '2026-09-30',
        'M j, Y' => 'Sep 30, 2026',
        'j F Y' => '30 September 2026',
    ];

    /** @var array<string, string> */
    public const NUMBER_FORMATS = [
        'international' => '1,234,567.89 (thousand, million)',
        'south_asian' => '12,34,567.89 (lakh, crore)',
    ];

    /** @var array<string, string> */
    public const CURRENCY_DISPLAYS = [
        'code' => 'Currency code (PKR 1,000.00)',
        'symbol' => 'Currency symbol (Rs 1,000.00)',
    ];

    /** @var array<string, string> */
    public const PAPERS = [
        'a4' => 'A4 (210 × 297 mm)',
        'letter' => 'Letter (8.5 × 11 in)',
        'legal' => 'Legal (8.5 × 14 in)',
    ];

    /**
     * Currency symbols (the code is used for currencies without a well-known symbol).
     *
     * @var array<string, string>
     */
    public const SYMBOLS = [
        'PKR' => 'Rs ',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CNY' => '¥',
        'CAD' => 'C$',
        'AUD' => 'A$',
    ];

    /**
     * The options that are switched on or off, with their default.
     *
     * @var array<string, bool>
     */
    private const FLAGS = [
        'show_tax' => true,
        'item_numbers' => true,
        'hs_codes' => true,
        'product_codes' => false,
        'units' => true,
        'discount_column' => true,
        'quantity_price' => true,
        'amount_in_words' => true,
        'payment_details' => true,
        'payment_summary' => true,
        'status_stamp' => true,
        'company_tax_numbers' => true,
        'company_contact' => true,
        'customer_tax_numbers' => true,
        'terms' => true,
        'qr_code' => false,
        'page_numbers' => true,
        'footer_bar' => true,
        'company_footer' => true,
    ];

    /**
     * The options with a fixed list of choices: their default and the choices.
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    private const CHOICES = [
        'tax_columns' => ['rate', self::TAX_COLUMNS],
        'tax_summary' => ['per_rate', self::TAX_SUMMARIES],
        'signature' => ['none', self::SIGNATURES],
        'date_format' => ['d M Y', self::DATE_FORMATS],
        'number_format' => ['international', self::NUMBER_FORMATS],
        'currency_display' => ['code', self::CURRENCY_DISPLAYS],
        'paper' => ['a4', self::PAPERS],
    ];

    /**
     * The optional texts, with their maximum length (empty means the automatic text).
     *
     * @var array<string, int>
     */
    private const TEXTS = [
        'closing_note' => 300,
        'invoice_title' => 40,
        'quotation_title' => 40,
        'credit_note_title' => 40,
        'customer_heading' => 40,
    ];

    public function __construct(
        public bool $showTax = true,
        public string $taxColumns = 'rate',
        public string $taxSummary = 'per_rate',
        public bool $itemNumbers = true,
        public bool $hsCodes = true,
        public bool $productCodes = false,
        public bool $units = true,
        public bool $discountColumn = true,
        public bool $quantityPrice = true,
        public bool $amountInWords = true,
        public bool $paymentDetails = true,
        public bool $paymentSummary = true,
        public bool $statusStamp = true,
        public bool $companyTaxNumbers = true,
        public bool $companyContact = true,
        public bool $customerTaxNumbers = true,
        public bool $terms = true,
        public string $signature = 'none',
        public bool $qrCode = false,
        public bool $pageNumbers = true,
        public bool $footerBar = true,
        public bool $companyFooter = true,
        public ?string $closingNote = null,
        public ?string $invoiceTitle = null,
        public ?string $quotationTitle = null,
        public ?string $creditNoteTitle = null,
        public ?string $customerHeading = null,
        public string $dateFormat = 'd M Y',
        public string $numberFormat = 'international',
        public string $currencyDisplay = 'code',
        public string $paper = 'a4',
    ) {
        //
    }

    /**
     * Get the options a company chose (defaults for anything it did not set).
     */
    public static function forTeam(Team $team): self
    {
        return self::fromArray((array) $team->teamSettings()->get('documents.options', []));
    }

    /**
     * Build the options from stored or submitted values; unknown or invalid values get their default.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            showTax: self::flag($data, 'show_tax'),
            taxColumns: self::choice($data, 'tax_columns'),
            taxSummary: self::choice($data, 'tax_summary'),
            itemNumbers: self::flag($data, 'item_numbers'),
            hsCodes: self::flag($data, 'hs_codes'),
            productCodes: self::flag($data, 'product_codes'),
            units: self::flag($data, 'units'),
            discountColumn: self::flag($data, 'discount_column'),
            quantityPrice: self::flag($data, 'quantity_price'),
            amountInWords: self::flag($data, 'amount_in_words'),
            paymentDetails: self::flag($data, 'payment_details'),
            paymentSummary: self::flag($data, 'payment_summary'),
            statusStamp: self::flag($data, 'status_stamp'),
            companyTaxNumbers: self::flag($data, 'company_tax_numbers'),
            companyContact: self::flag($data, 'company_contact'),
            customerTaxNumbers: self::flag($data, 'customer_tax_numbers'),
            terms: self::flag($data, 'terms'),
            signature: self::choice($data, 'signature'),
            qrCode: self::flag($data, 'qr_code'),
            pageNumbers: self::flag($data, 'page_numbers'),
            footerBar: self::flag($data, 'footer_bar'),
            companyFooter: self::flag($data, 'company_footer'),
            closingNote: self::text($data, 'closing_note'),
            invoiceTitle: self::text($data, 'invoice_title'),
            quotationTitle: self::text($data, 'quotation_title'),
            creditNoteTitle: self::text($data, 'credit_note_title'),
            customerHeading: self::text($data, 'customer_heading'),
            dateFormat: self::choice($data, 'date_format'),
            numberFormat: self::choice($data, 'number_format'),
            currencyDisplay: self::choice($data, 'currency_display'),
            paper: self::choice($data, 'paper'),
        );
    }

    /**
     * Read an on/off option ("1", "0", "true" and "false" are understood too).
     *
     * @param  array<string, mixed>  $data
     */
    private static function flag(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        return $value === null ? self::FLAGS[$key] : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? self::FLAGS[$key]);
    }

    /**
     * Read an option with a fixed list of choices.
     *
     * @param  array<string, mixed>  $data
     */
    private static function choice(array $data, string $key): string
    {
        [$default, $choices] = self::CHOICES[$key];
        $value = $data[$key] ?? null;

        return is_string($value) && array_key_exists($value, $choices) ? $value : $default;
    }

    /**
     * Read an optional text, trimmed and cut to its maximum length (empty becomes null).
     *
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, self::TEXTS[$key]) : null;
    }

    /**
     * Get the default options as stored values.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return (new self)->toArray();
    }

    /**
     * Get a copy with every block switched on (the template builder shows all elements, greying out
     * the ones the invoice settings switch off).
     */
    public function withBlocksOn(): self
    {
        return self::fromArray([
            ...$this->toArray(),
            ...array_fill_keys(['amount_in_words', 'payment_details', 'payment_summary', 'status_stamp', 'company_tax_numbers', 'company_contact', 'customer_tax_numbers', 'terms', 'qr_code', 'footer_bar', 'company_footer'], true),
            'signature' => $this->signature === 'none' ? 'double' : $this->signature,
        ]);
    }

    /**
     * The validation rules for submitted options.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = ''): array
    {
        $rules = [];

        foreach (array_keys(self::FLAGS) as $key) {
            $rules[$prefix.$key] = ['required', 'boolean'];
        }

        foreach (self::CHOICES as $key => [, $choices]) {
            $rules[$prefix.$key] = ['required', Rule::in(array_keys($choices))];
        }

        foreach (self::TEXTS as $key => $max) {
            $rules[$prefix.$key] = ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /**
     * Get the options as stored values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [];

        foreach ([...array_keys(self::FLAGS), ...array_keys(self::CHOICES), ...array_keys(self::TEXTS)] as $key) {
            $values[$key] = $this->{Str::camel($key)};
        }

        return $values;
    }

    /**
     * Get the title chosen for a document type ("invoice", "quotation" or "credit_note"), if any.
     */
    public function titleFor(string $type): ?string
    {
        return match ($type) {
            'invoice' => $this->invoiceTitle,
            'quotation' => $this->quotationTitle,
            'credit_note' => $this->creditNoteTitle,
            default => null,
        };
    }

    /**
     * Format a date.
     */
    public function date(?CarbonInterface $date): ?string
    {
        return $date?->format($this->dateFormat);
    }

    /**
     * Format an amount, with the currency in front when one is given.
     */
    public function money(string|int|float|null $amount, ?string $currency = null): string
    {
        $number = $this->number($amount);

        return $currency === null ? $number : $this->currency($currency).$number;
    }

    /**
     * Get the currency as printed in front of amounts ("PKR " or "Rs ").
     */
    public function currency(string $code): string
    {
        return $this->currencyDisplay === 'symbol' && isset(self::SYMBOLS[$code]) ? self::SYMBOLS[$code] : $code.' ';
    }

    /**
     * Format a number with the chosen digit grouping.
     */
    public function number(string|int|float|null $value, int $decimals = 2): string
    {
        $formatted = number_format((float) $value, $decimals, '.', '');
        $negative = str_starts_with($formatted, '-') && (float) $formatted !== 0.0;
        [$whole, $fraction] = array_pad(explode('.', ltrim($formatted, '-'), 2), 2, null);

        $whole = $this->numberFormat === 'south_asian' && strlen($whole) > 3
            ? strrev(implode(',', str_split(strrev(substr($whole, 0, -3)), 2))).','.substr($whole, -3)
            : strrev(implode(',', str_split(strrev($whole), 3)));

        return ($negative ? '-' : '').$whole.($fraction !== null ? '.'.$fraction : '');
    }

    /**
     * Format a quantity: up to three decimals, without trailing zeros.
     */
    public function quantity(string|int|float|null $value): string
    {
        return rtrim(rtrim($this->number($value, 3), '0'), '.');
    }

    /**
     * Format a percentage, e.g. "18%" or "17.5%".
     */
    public function percent(string|int|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.').'%';
    }
}
