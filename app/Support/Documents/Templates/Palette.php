<?php

namespace App\Support\Documents\Templates;

/**
 * The colours of a template: an accent colour and a text colour, and the colour
 * tokens elements use ("accent", "soft", "muted", ...). Text colours are
 * adjusted to their background so text on coloured areas stays readable.
 *
 * Every colour this class returns is a #rrggbb value, so it is safe in CSS.
 */
final readonly class Palette
{
    /**
     * Background colours (or a #rrggbb colour).
     *
     * @var array<string, string>
     */
    public const BACKGROUNDS = [
        'none' => 'None',
        'accent' => 'Accent colour',
        'soft' => 'Light accent',
        'faint' => 'Very light accent',
        'grey' => 'Light grey',
        'dark' => 'Dark',
    ];

    /**
     * Text colours (or a #rrggbb colour); automatic picks white or dark text for the background.
     *
     * @var array<string, string>
     */
    public const TEXTS = [
        'auto' => 'Automatic',
        'text' => 'Text colour',
        'accent' => 'Accent colour',
        'muted' => 'Grey',
    ];

    /**
     * Line colours (or a #rrggbb colour).
     *
     * @var array<string, string>
     */
    public const LINES = [
        'grey' => 'Light grey',
        'soft' => 'Light accent',
        'accent' => 'Accent colour',
        'dark' => 'Dark',
    ];

    public function __construct(public string $accent, public string $text = '#1f2937')
    {
        //
    }

    /**
     * Get a background colour, or null for no background.
     */
    public function background(string $token): ?string
    {
        return match ($token) {
            'none', '' => null,
            'accent' => $this->accent,
            'soft' => self::mix($this->accent, '#ffffff', 0.9),
            'faint' => self::mix($this->accent, '#ffffff', 0.95),
            'grey' => '#f3f4f6',
            'dark' => '#111827',
            default => self::isHex($token) ? strtolower($token) : null,
        };
    }

    /**
     * Get a line (border) colour.
     */
    public function line(string $token): string
    {
        return match ($token) {
            'accent' => $this->accent,
            'soft' => self::mix($this->accent, '#ffffff', 0.7),
            'dark' => '#374151',
            default => self::isHex($token) ? strtolower($token) : '#e5e7eb',
        };
    }

    /**
     * Get a text colour on a background (null is the white page).
     *
     * A colour that would hardly stand out from its background falls back to the automatic colour.
     */
    public function text(string $token, ?string $background = null): string
    {
        $background ??= '#ffffff';
        $auto = $this->isDark($background) ? '#ffffff' : $this->text;

        $colour = match ($token) {
            'text' => $this->text,
            'accent' => $this->accentOn($background),
            'muted' => $this->isDark($background) ? self::mix($background, '#ffffff', 0.75) : '#6b7280',
            default => self::isHex($token) ? strtolower($token) : $auto,
        };

        return self::contrast($colour, $background) < 1.6 ? $auto : $colour;
    }

    /**
     * Get the text colour on the accent colour (white or the text colour, whichever reads better).
     */
    public function onAccent(): string
    {
        return $this->text('auto', $this->accent);
    }

    /**
     * Get the accent colour as it reads on a background: darkened on light backgrounds
     * and lightened on dark ones until it is easy to read.
     */
    public function accentOn(string $background = '#ffffff'): string
    {
        $towards = $this->isDark($background) ? '#ffffff' : '#000000';
        $colour = $this->accent;

        for ($step = 0.1; self::contrast($colour, $background) < 4.5 && $step <= 1.0; $step += 0.1) {
            $colour = self::mix($this->accent, $towards, $step);
        }

        return $colour;
    }

    /**
     * Determine if a background is dark enough for white text: at least 3:1, the contrast WCAG asks
     * for large and bold text, which is what sits on coloured areas (titles, table headers, totals).
     */
    public function isDark(string $background): bool
    {
        return self::contrast($background, '#ffffff') >= 3.0;
    }

    /**
     * Determine if a value is a #rrggbb colour.
     */
    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    /**
     * Mix a colour with another colour (0 = the first colour, 1 = the second).
     */
    public static function mix(string $hex, string $with, float $amount): string
    {
        $amount = max(0.0, min(1.0, $amount));
        $a = self::rgb($hex);
        $b = self::rgb($with);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($a[0] * (1 - $amount) + $b[0] * $amount),
            (int) round($a[1] * (1 - $amount) + $b[1] * $amount),
            (int) round($a[2] * (1 - $amount) + $b[2] * $amount),
        );
    }

    /**
     * Get the WCAG contrast ratio of two colours.
     */
    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /**
     * Get the relative luminance of a colour.
     */
    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel) {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Split a #rrggbb colour into its channels (black for anything else).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        if (! self::isHex($hex)) {
            return [0, 0, 0];
        }

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }
}
