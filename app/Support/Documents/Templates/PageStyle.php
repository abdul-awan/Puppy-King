<?php

namespace App\Support\Documents\Templates;

/**
 * The page of a template: accent and text colour, type, text size, margins,
 * a decoration at the edge of the page, the footer line and the look of the
 * small labels ("BILL TO", "AMOUNT IN WORDS", ...).
 *
 * Every value comes from a fixed list, a range or is a #rrggbb colour, so it is safe in CSS.
 */
final readonly class PageStyle
{
    /** @var array<string, string> */
    public const FONTS = [
        'sans' => 'Sans-serif',
        'serif' => 'Serif',
        'mono' => 'Monospace',
    ];

    /** @var array<string, string> */
    public const FONT_SIZES = [
        'small' => 'Small',
        'normal' => 'Normal',
        'large' => 'Large',
    ];

    /** @var array<string, string> */
    public const MARGINS = [
        'narrow' => 'Narrow',
        'normal' => 'Normal',
        'wide' => 'Wide',
    ];

    /** @var array<string, string> */
    public const DECORATIONS = [
        'none' => 'None',
        'top_band' => 'Coloured band across the top',
        'top_line' => 'Thin line across the top',
        'bottom_band' => 'Coloured band across the bottom',
        'left_stripe' => 'Stripe down the left side',
    ];

    /** @var array<string, string> */
    public const FOOTER_ALIGNS = [
        'left' => 'Left',
        'center' => 'Centre',
        'right' => 'Right',
    ];

    /** @var array<string, string> */
    public const FOOTER_BORDERS = [
        'none' => 'No line',
        'line' => 'Grey line',
        'accent' => 'Accent line',
    ];

    public function __construct(
        public string $accent = '#1f3a5f',
        public string $text = '#1f2937',
        public string $font = 'sans',
        public string $fontSize = 'normal',
        public string $margins = 'normal',
        public string $decoration = 'none',
        public string $footerAlign = 'left',
        public string $footerBorder = 'line',
        public string $labelColor = 'muted',
        public float $labelSpacing = 0.5,
    ) {
        //
    }

    /**
     * Build the page style from stored or submitted values; anything invalid gets its default.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = new self;
        $pick = fn (string $key, array $allowed, string $default): string => is_string($data[$key] ?? null) && array_key_exists($data[$key], $allowed) ? $data[$key] : $default;
        $hex = fn (string $key, string $default): string => Palette::isHex($data[$key] ?? null) ? strtolower((string) $data[$key]) : $default;
        $label = $data['label_color'] ?? null;
        $spacing = $data['label_spacing'] ?? null;

        return new self(
            accent: $hex('accent', $defaults->accent),
            text: $hex('text', $defaults->text),
            font: $pick('font', self::FONTS, $defaults->font),
            fontSize: $pick('font_size', self::FONT_SIZES, $defaults->fontSize),
            margins: $pick('margins', self::MARGINS, $defaults->margins),
            decoration: $pick('decoration', self::DECORATIONS, $defaults->decoration),
            footerAlign: $pick('footer_align', self::FOOTER_ALIGNS, $defaults->footerAlign),
            footerBorder: $pick('footer_border', self::FOOTER_BORDERS, $defaults->footerBorder),
            labelColor: (is_string($label) && array_key_exists($label, Palette::TEXTS)) || Palette::isHex($label) ? strtolower((string) $label) : $defaults->labelColor,
            labelSpacing: is_numeric($spacing) ? round(max(0.0, min(4.0, (float) $spacing)), 1) : $defaults->labelSpacing,
        );
    }

    /**
     * Get the page style as stored values.
     *
     * @return array{accent: string, text: string, font: string, font_size: string, margins: string, decoration: string, footer_align: string, footer_border: string, label_color: string, label_spacing: float}
     */
    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'text' => $this->text,
            'font' => $this->font,
            'font_size' => $this->fontSize,
            'margins' => $this->margins,
            'decoration' => $this->decoration,
            'footer_align' => $this->footerAlign,
            'footer_border' => $this->footerBorder,
            'label_color' => $this->labelColor,
            'label_spacing' => $this->labelSpacing,
        ];
    }

    /**
     * Get a copy with some values changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->toArray(), ...$changes]);
    }

    /**
     * Get the colours of the page.
     */
    public function palette(): Palette
    {
        return new Palette($this->accent, $this->text);
    }

    /**
     * Get the CSS font family of a font (fonts that come with the PDF engine and cover all characters used).
     */
    public static function family(string $font): string
    {
        return match ($font) {
            'serif' => '"DejaVu Serif", serif',
            'mono' => '"DejaVu Sans Mono", monospace',
            default => '"DejaVu Sans", sans-serif',
        };
    }

    /**
     * Get the CSS font family of the page.
     */
    public function fontFamily(): string
    {
        return self::family($this->font);
    }

    /**
     * Get the name of the page font as the PDF engine knows it.
     */
    public function fontName(): string
    {
        return match ($this->font) {
            'serif' => 'DejaVu Serif',
            'mono' => 'DejaVu Sans Mono',
            default => 'DejaVu Sans',
        };
    }

    /**
     * Get the base text size in pixels.
     */
    public function baseFontSize(): float
    {
        return match ($this->fontSize) {
            'small' => 8.5,
            'large' => 10.5,
            default => 9.5,
        };
    }

    /**
     * Scale a size of the normal text size to the chosen text size.
     */
    public function scale(float $normal): float
    {
        return round($normal * $this->baseFontSize() / 9.5, 2);
    }

    /**
     * Get the page margins in pixels: top, right, bottom, left.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public function pageMargins(): array
    {
        return match ($this->margins) {
            'narrow' => [22, 24, 40, 24],
            'wide' => [40, 48, 54, 48],
            default => [30, 32, 46, 32],
        };
    }
}
