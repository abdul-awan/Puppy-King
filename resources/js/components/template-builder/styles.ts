// The colours and CSS of templates in the browser: the same rules as the PDF
// (App\Support\Documents\Templates\Palette, Css and PageStyle), so the builder
// shows what the PDF will look like.
import type { CSSProperties } from 'vue';
import type { PageSettings, StyleValues } from './types';

const isHex = (value: unknown): value is string =>
    typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value);

const rgb = (hex: string): [number, number, number] => {
    if (!isHex(hex)) {
        return [0, 0, 0];
    }

    const n = parseInt(hex.slice(1), 16);

    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
};

export const mix = (hex: string, withHex: string, amount: number): string => {
    const a = rgb(hex);
    const b = rgb(withHex);
    const t = Math.max(0, Math.min(1, amount));

    return (
        '#' +
        a
            .map((channel, index) =>
                Math.round(channel * (1 - t) + b[index] * t)
                    .toString(16)
                    .padStart(2, '0'),
            )
            .join('')
    );
};

const luminance = (hex: string): number => {
    const [r, g, b] = rgb(hex).map((channel) => {
        const value = channel / 255;

        return value <= 0.03928
            ? value / 12.92
            : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export const contrast = (a: string, b: string): number => {
    const first = luminance(a);
    const second = luminance(b);

    return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
};

export const px = (value: number | string): string =>
    `${Number(Number(value).toFixed(2))}px`;

export class Palette {
    constructor(
        public accent: string,
        public textColor: string = '#1f2937',
    ) {}

    background(token: string): string | null {
        switch (token) {
            case 'none':
            case '':
                return null;
            case 'accent':
                return this.accent;
            case 'soft':
                return mix(this.accent, '#ffffff', 0.9);
            case 'faint':
                return mix(this.accent, '#ffffff', 0.95);
            case 'grey':
                return '#f3f4f6';
            case 'dark':
                return '#111827';
            default:
                return isHex(token) ? token.toLowerCase() : null;
        }
    }

    line(token: string): string {
        switch (token) {
            case 'accent':
                return this.accent;
            case 'soft':
                return mix(this.accent, '#ffffff', 0.7);
            case 'dark':
                return '#374151';
            default:
                return isHex(token) ? token.toLowerCase() : '#e5e7eb';
        }
    }

    isDark(background: string): boolean {
        return contrast(background, '#ffffff') >= 3;
    }

    accentOn(background = '#ffffff'): string {
        const towards = this.isDark(background) ? '#ffffff' : '#000000';
        let colour = this.accent;

        for (
            let step = 0.1;
            contrast(colour, background) < 4.5 && step <= 1;
            step += 0.1
        ) {
            colour = mix(this.accent, towards, step);
        }

        return colour;
    }

    text(token: string, background: string | null = null): string {
        const bg = background ?? '#ffffff';
        const auto = this.isDark(bg) ? '#ffffff' : this.textColor;
        let colour: string;

        switch (token) {
            case 'text':
                colour = this.textColor;
                break;
            case 'accent':
                colour = this.accentOn(bg);
                break;
            case 'muted':
                colour = this.isDark(bg) ? mix(bg, '#ffffff', 0.75) : '#6b7280';
                break;
            default:
                colour = isHex(token) ? token.toLowerCase() : auto;
        }

        return contrast(colour, bg) < 1.6 ? auto : colour;
    }

    onAccent(): string {
        return this.text('auto', this.accent);
    }
}

export const fontFamily = (font: string): string => {
    switch (font) {
        case 'serif':
            return '"DejaVu Serif", Georgia, "Times New Roman", serif';
        case 'mono':
            return '"DejaVu Sans Mono", Menlo, Consolas, monospace';
        default:
            return '"DejaVu Sans", Verdana, "Segoe UI", sans-serif';
    }
};

export const baseFontSize = (page: PageSettings): number =>
    page.font_size === 'small' ? 8.5 : page.font_size === 'large' ? 10.5 : 9.5;

export const scale = (page: PageSettings, normal: number): number =>
    Math.round(((normal * baseFontSize(page)) / 9.5) * 100) / 100;

export const pageMargins = (
    page: PageSettings,
): [number, number, number, number] => {
    switch (page.margins) {
        case 'narrow':
            return [22, 24, 40, 24];
        case 'wide':
            return [40, 48, 54, 48];
        default:
            return [30, 32, 46, 32];
    }
};

export const lineCss = (
    palette: Palette,
    width: number,
    style: string,
    color: string,
): string => {
    const lineStyle = ['solid', 'dashed', 'dotted', 'double'].includes(style)
        ? style
        : 'solid';
    const lineWidth =
        lineStyle === 'double'
            ? Math.max(width, 3)
            : Math.max(0.25, Math.min(8, width));

    return `${px(lineWidth)} ${lineStyle} ${palette.line(color)}`;
};

export type Box = {
    css: CSSProperties;
    background: string | null;
    ink: string;
};

/**
 * The CSS of a row, cell or element and the colours inside it (Css::box()).
 */
export const box = (
    style: StyleValues,
    palette: Palette,
    parentBackground: string | null,
): Box => {
    const own = palette.background(String(style.background ?? 'none'));
    const background = own ?? parentBackground;
    const ink = palette.text(String(style.color ?? 'auto'), background);
    const css: CSSProperties = { color: ink };

    if (style.align !== undefined) {
        css.textAlign = style.align as CSSProperties['textAlign'];
    }

    if (style.font !== undefined && style.font !== 'inherit') {
        css.fontFamily = fontFamily(String(style.font));
    }

    const size = Number(style.font_size ?? 0);

    if (size > 0) {
        css.fontSize = px(size);

        if (size >= 12) {
            css.lineHeight = size >= 16 ? 1.15 : 1.25;
        }
    }

    if (style.weight === 'bold' || style.weight === 'normal') {
        css.fontWeight = style.weight;
    }

    if (style.italic) {
        css.fontStyle = 'italic';
    }

    if (style.uppercase) {
        css.textTransform = 'uppercase';
    }

    if (Number(style.letter_spacing ?? 0) > 0) {
        css.letterSpacing = px(Number(style.letter_spacing));
    }

    if (own !== null) {
        css.backgroundColor = own;
    }

    const paddingY = Number(style.padding_y ?? 0);
    const paddingX = Number(style.padding_x ?? 0);

    if (paddingY > 0 || paddingX > 0) {
        css.padding = `${px(paddingY)} ${px(paddingX)}`;
    }

    if (Number(style.margin_top ?? 0) > 0) {
        css.marginTop = px(Number(style.margin_top));
    }

    const sides: Record<string, string[]> = {
        box: ['Top', 'Right', 'Bottom', 'Left'],
        top: ['Top'],
        bottom: ['Bottom'],
        top_bottom: ['Top', 'Bottom'],
        left: ['Left'],
    };
    const line = lineCss(
        palette,
        Number(style.border_width ?? 1),
        String(style.border_style ?? 'solid'),
        String(style.border_color ?? 'grey'),
    );

    for (const side of sides[String(style.border ?? 'none')] ?? []) {
        (css as Record<string, string>)[`border${side}`] = line;
    }

    if (Number(style.radius ?? 0) > 0) {
        css.borderRadius = px(Number(style.radius));
    }

    return { css, background, ink };
};
