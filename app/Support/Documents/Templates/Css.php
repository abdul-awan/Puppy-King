<?php

namespace App\Support\Documents\Templates;

/**
 * Builds the inline CSS of rows, cells and elements from their normalised
 * style (fixed lists, numbers and #rrggbb colours only).
 */
final class Css
{
    public function __construct(private Palette $palette)
    {
        //
    }

    /**
     * Get the CSS of a box (row, cell or element) and the colours inside it.
     *
     * @param  array<string, mixed>  $style
     * @param  string|null  $parentBackground  the background behind the box (null is the white page)
     * @return array{css: string, background: string|null, ink: string}
     */
    public function box(array $style, ?string $parentBackground): array
    {
        $own = $this->palette->background((string) ($style['background'] ?? 'none'));
        $background = $own ?? $parentBackground;
        $ink = $this->palette->text((string) ($style['color'] ?? 'auto'), $background);
        $rules = ['color: '.$ink];

        if (isset($style['align'])) {
            $rules[] = 'text-align: '.$style['align'];
        }

        if (($style['font'] ?? 'inherit') !== 'inherit') {
            $rules[] = 'font-family: '.PageStyle::family((string) $style['font']);
        }

        $size = (float) ($style['font_size'] ?? 0);

        if ($size > 0) {
            $rules[] = 'font-size: '.self::px($size);

            // Large text wraps with less space between its lines.
            if ($size >= 12) {
                $rules[] = 'line-height: '.($size >= 16 ? '1.15' : '1.25');
            }
        }

        if (in_array($style['weight'] ?? 'inherit', ['bold', 'normal'], true)) {
            $rules[] = 'font-weight: '.$style['weight'];
        }

        if ($style['italic'] ?? false) {
            $rules[] = 'font-style: italic';
        }

        if ($style['uppercase'] ?? false) {
            $rules[] = 'text-transform: uppercase';
        }

        if ((float) ($style['letter_spacing'] ?? 0) > 0) {
            $rules[] = 'letter-spacing: '.self::px($style['letter_spacing']);
        }

        if ($own !== null) {
            $rules[] = 'background-color: '.$own;
        }

        $paddingY = (float) ($style['padding_y'] ?? 0);
        $paddingX = (float) ($style['padding_x'] ?? 0);

        if ($paddingY > 0 || $paddingX > 0) {
            $rules[] = 'padding: '.self::px($paddingY).' '.self::px($paddingX);
        }

        if ((float) ($style['margin_top'] ?? 0) > 0) {
            $rules[] = 'margin-top: '.self::px($style['margin_top']);
        }

        array_push($rules, ...$this->borders($style));

        if ((float) ($style['radius'] ?? 0) > 0) {
            $rules[] = 'border-radius: '.self::px($style['radius']);
        }

        return ['css' => implode('; ', $rules), 'background' => $background, 'ink' => $ink];
    }

    /**
     * Get the border rules of a style.
     *
     * @param  array<string, mixed>  $style
     * @return list<string>
     */
    public function borders(array $style): array
    {
        $sides = match ($style['border'] ?? 'none') {
            'box' => ['top', 'right', 'bottom', 'left'],
            'top' => ['top'],
            'bottom' => ['bottom'],
            'top_bottom' => ['top', 'bottom'],
            'left' => ['left'],
            default => [],
        };

        $value = $this->line((float) ($style['border_width'] ?? 1), (string) ($style['border_style'] ?? 'solid'), (string) ($style['border_color'] ?? 'grey'));

        return array_map(fn (string $side) => "border-{$side}: {$value}", $sides);
    }

    /**
     * Get a line, e.g. "1px solid #e5e7eb" (double lines are at least 3px, or they show as one).
     */
    public function line(float $width, string $style, string $color): string
    {
        $style = in_array($style, ['solid', 'dashed', 'dotted', 'double'], true) ? $style : 'solid';
        $width = $style === 'double' ? max($width, 3.0) : max(0.25, min(8.0, $width));

        return self::px($width).' '.$style.' '.$this->palette->line($color);
    }

    /**
     * Format a size in pixels without needless decimals.
     */
    public static function px(int|float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'px';
    }
}
