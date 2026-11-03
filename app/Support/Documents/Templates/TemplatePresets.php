<?php

namespace App\Support\Documents\Templates;

use App\Enums\TemplateLayout;

/**
 * The fifteen ready-made templates. They are made of the same rows and
 * elements as templates made with the builder, so every one of them can be
 * opened in the builder and changed.
 */
final class TemplatePresets
{
    /**
     * A box with a light grey border.
     *
     * @var array<string, mixed>
     */
    private const BOXED = ['border' => 'box', 'border_color' => 'grey', 'radius' => 3, 'padding_y' => 8, 'padding_x' => 10];

    /**
     * Built templates, cached.
     *
     * @var array<string, TemplateDefinition>
     */
    private static array $built = [];

    /**
     * Get the ready-made template of a layout.
     */
    public static function for(TemplateLayout $layout): TemplateDefinition
    {
        return self::$built[$layout->value] ??= TemplateDefinition::fromArray(match ($layout) {
            TemplateLayout::Classic => self::classic(),
            TemplateLayout::Modern => self::modern(),
            TemplateLayout::Minimal => self::minimal(),
            TemplateLayout::Corporate => self::corporate(),
            TemplateLayout::Elegant => self::elegant(),
            TemplateLayout::Fresh => self::fresh(),
            TemplateLayout::Slate => self::slate(),
            TemplateLayout::Ledger => self::ledger(),
            TemplateLayout::Horizon => self::horizon(),
            TemplateLayout::Noir => self::noir(),
            TemplateLayout::Breeze => self::breeze(),
            TemplateLayout::Summit => self::summit(),
            TemplateLayout::Atlas => self::atlas(),
            TemplateLayout::Prism => self::prism(),
            TemplateLayout::Clarity => self::clarity(),
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function classic(): array
    {
        return [
            'page' => ['accent' => '#1f3a5f', 'footer_border' => 'line'],
            'rows' => [
                self::row('55-45', [
                    [self::el('logo'), self::el('company', [], ['margin_top' => 6])],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 20, 'uppercase' => true, 'letter_spacing' => 0.5]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 58], ['align' => 'right', 'margin_top' => 8]),
                    ],
                ]),
                self::one(self::el('divider', ['thickness' => 2, 'color' => 'accent'], ['margin_top' => 12])),
                self::row('55-45', [
                    [self::el('customer', [], self::BOXED)],
                    [self::el('extra', [], self::BOXED)],
                ], ['margin_top' => 14, 'gap' => 20]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'light', 'header_background' => 'grey', 'header_line' => 'accent'], ['margin_top' => 12])),
                self::summary([self::el('totals', ['grand_style' => 'line'])], ['margin_top' => 10, ...self::BOXED]),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function modern(): array
    {
        return [
            'page' => ['accent' => '#0f766e', 'decoration' => 'top_band', 'footer_border' => 'none', 'label_color' => 'accent'],
            'rows' => [
                self::row('60-40', [
                    [self::el('logo'), self::el('company', [], ['margin_top' => 6])],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 24]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                    ],
                ], ['margin_top' => 10]),
                self::row('40-30-30', [
                    [self::el('customer')],
                    [self::el('details', ['heading' => 'Details', 'label_width' => 42])],
                    [self::el('amount_due')],
                ], ['margin_top' => 16, 'background' => 'soft', 'padding_y' => 10, 'padding_x' => 12]),
                self::one(self::el('extra', [], ['margin_top' => 10, 'background' => 'faint', 'border' => 'box', 'border_color' => 'soft', 'radius' => 3, 'padding_y' => 8, 'padding_x' => 10])),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'fill', 'header_background' => 'accent', 'row_style' => 'striped', 'row_line' => 'soft'], ['margin_top' => 12])),
                self::summary(
                    [self::el('totals', ['grand_style' => 'fill', 'grand_size' => 12.5])],
                    ['margin_top' => 10, 'border' => 'box', 'border_color' => 'soft', 'radius' => 3, 'padding_y' => 8, 'padding_x' => 10],
                ),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function minimal(): array
    {
        $line = ['margin_top' => 10, 'border' => 'left', 'border_color' => 'soft', 'border_width' => 2, 'padding_x' => 10];

        return [
            'page' => ['accent' => '#111827', 'margins' => 'wide', 'footer_border' => 'none'],
            'rows' => [
                self::row('55-45', [
                    [
                        self::el('logo', ['height' => 40]),
                        self::el('company', ['name_size' => 12, 'address' => 'hidden', 'contact' => 'hidden', 'tax_numbers' => 'hidden'], ['margin_top' => 6]),
                    ],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 22, 'weight' => 'normal', 'letter_spacing' => 1, 'color' => 'text']),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                    ],
                ]),
                self::row('33-33-34', [
                    [self::el('customer')],
                    [self::el('details', ['heading' => 'Details', 'label_width' => 40])],
                    [self::el('company', ['heading' => 'From', 'show_name' => false, 'contact' => 'stacked', 'tax_numbers' => 'stacked'])],
                ], ['margin_top' => 26, 'gap' => 14]),
                self::one(self::el('extra', [], $line)),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'plain', 'header_color' => 'muted', 'header_line' => 'accent', 'header_bold' => false], ['margin_top' => 18])),
                self::summary([self::el('totals', ['grand_style' => 'line', 'grand_size' => 13])], $line, ['margin_top' => 16]),
                ...self::ending(['color' => 'muted']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function corporate(): array
    {
        $barred = ['border' => 'box', 'border_color' => 'soft'];

        return [
            'page' => ['accent' => '#1e3a8a', 'footer_border' => 'accent'],
            'rows' => [
                self::row('55-45', [
                    [self::el('logo'), self::el('company', [], ['margin_top' => 6])],
                    self::cell([
                        self::el('title', [], ['align' => 'right', 'font_size' => 18, 'uppercase' => true, 'letter_spacing' => 1.2, 'color' => 'auto']),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 58], ['align' => 'right', 'margin_top' => 8]),
                    ], ['background' => 'accent', 'padding_y' => 12, 'padding_x' => 14]),
                ], ['gap' => 14]),
                self::row('50-50', [
                    [self::el('customer', ['heading_style' => 'bar'], $barred)],
                    [self::el('extra', ['heading_style' => 'bar'], $barred)],
                ], ['margin_top' => 14]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'fill', 'header_background' => 'accent', 'row_style' => 'grid'], ['margin_top' => 12])),
                self::summary(
                    [self::el('totals', ['rows_style' => 'boxed', 'grand_style' => 'fill'])],
                    ['margin_top' => 10, 'border' => 'box', 'border_color' => 'soft', 'padding_y' => 8, 'padding_x' => 10],
                ),
                ...self::ending([], ['variant' => 'box']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function elegant(): array
    {
        return [
            'page' => ['accent' => '#8b6b3d', 'font' => 'serif', 'margins' => 'wide', 'footer_align' => 'center', 'label_color' => 'accent', 'label_spacing' => 1.5],
            'rows' => [
                self::one([
                    self::el('logo', [], ['align' => 'center']),
                    self::el('company', [
                        'name_size' => 16, 'name_bold' => false, 'name_uppercase' => true, 'name_letter_spacing' => 2.5, 'name_color' => 'accent', 'address' => 'inline',
                    ], ['align' => 'center', 'color' => 'muted', 'margin_top' => 4]),
                ]),
                self::one(self::el('divider', ['line' => 'double', 'thickness' => 3, 'color' => 'accent'], ['margin_top' => 12])),
                self::one([
                    self::el('title', [], ['align' => 'center', 'font_size' => 15, 'weight' => 'normal', 'uppercase' => true, 'letter_spacing' => 6, 'color' => 'text']),
                    self::el('stamp', [], ['align' => 'center', 'margin_top' => 6]),
                    self::el('details', ['layout' => 'inline'], ['align' => 'center', 'margin_top' => 6]),
                ], ['margin_top' => 12]),
                self::row('55-45', [[self::el('customer')], [self::el('extra')]], ['margin_top' => 18, 'gap' => 20]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', [
                    'header_style' => 'lines', 'header_color' => 'accent', 'header_line' => 'accent', 'header_bold' => false, 'line_style' => 'dotted', 'section_style' => 'italic',
                ], ['margin_top' => 16])),
                self::summary(
                    [self::el('totals', ['grand_style' => 'double', 'grand_size' => 12.5])],
                    ['margin_top' => 10, 'border' => 'top', 'border_color' => 'soft', 'padding_y' => 6],
                    ['margin_top' => 14],
                ),
                ...self::ending(['align' => 'center', 'italic' => true, 'color' => 'accent', 'font_size' => 10.5, 'margin_top' => 22]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fresh(): array
    {
        $tinted = ['background' => 'faint', 'radius' => 6, 'padding_y' => 8, 'padding_x' => 10];

        return [
            'page' => ['accent' => '#2563eb', 'decoration' => 'top_line', 'footer_border' => 'none'],
            'rows' => [
                self::row('60-40', [
                    [self::el('logo', ['height' => 48]), self::el('company', ['name_size' => 14, 'address' => 'inline'], ['margin_top' => 8])],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 28, 'uppercase' => true, 'letter_spacing' => 1]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 55], ['align' => 'right', 'margin_top' => 10]),
                    ],
                ], ['margin_top' => 8]),
                self::row('50-50', [
                    [self::el('customer')],
                    [self::el('amount_due', ['value_size' => 20])],
                ], ['margin_top' => 22]),
                self::one(self::el('extra', [], ['margin_top' => 12, ...$tinted])),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'plain', 'header_color' => 'muted', 'header_line' => 'grey'], ['margin_top' => 14])),
                self::summary([self::el('totals', ['grand_style' => 'soft', 'grand_size' => 13])], ['margin_top' => 10, ...$tinted], ['margin_top' => 14], '50-50'),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function slate(): array
    {
        return [
            'page' => ['accent' => '#334155', 'footer_border' => 'none'],
            'rows' => [
                self::row('60-40', [
                    [self::el('logo', ['height' => 44]), self::el('company', ['name_size' => 15, 'address' => 'inline'], ['margin_top' => 6])],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 24, 'uppercase' => true, 'letter_spacing' => 2, 'color' => 'auto']),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 55], ['align' => 'right', 'margin_top' => 8]),
                    ],
                ], ['background' => 'accent', 'padding_y' => 18, 'padding_x' => 20, 'radius' => 6, 'gap' => 20]),
                self::row('50-50', [[self::el('customer')], [self::el('extra')]], ['margin_top' => 18, 'gap' => 20]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'light', 'header_background' => 'grey', 'header_line' => 'accent'], ['margin_top' => 14])),
                self::summary([self::el('totals', ['grand_style' => 'fill'])], ['margin_top' => 10, ...self::BOXED, 'radius' => 4]),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function ledger(): array
    {
        $barred = ['border' => 'box', 'border_color' => 'grey'];

        return [
            'page' => ['accent' => '#166534', 'footer_border' => 'line'],
            'rows' => [
                self::row('55-45', [
                    [self::el('logo', ['height' => 44]), self::el('company', [], ['margin_top' => 6])],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 17, 'uppercase' => true, 'letter_spacing' => 3]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 50], ['align' => 'right', 'margin_top' => 8, 'font' => 'mono']),
                    ],
                ]),
                self::one(self::el('divider', ['color' => 'dark'], ['margin_top' => 12])),
                self::row('50-50', [
                    [self::el('customer', ['heading_style' => 'bar', 'bar_background' => 'faint'], $barred)],
                    [self::el('extra', ['heading_style' => 'bar', 'bar_background' => 'faint'], $barred)],
                ], ['margin_top' => 14]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'fill', 'header_background' => 'soft', 'header_color' => 'accent', 'row_style' => 'grid'], ['margin_top' => 12])),
                self::summary(
                    [self::el('totals', ['rows_style' => 'boxed', 'grand_style' => 'box'], ['font' => 'mono'])],
                    ['margin_top' => 10, ...self::BOXED, 'radius' => 0],
                ),
                ...self::ending([], ['variant' => 'box']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function horizon(): array
    {
        $line = ['margin_top' => 10, 'border' => 'left', 'border_color' => 'accent', 'border_width' => 2, 'padding_x' => 10];

        return [
            'page' => ['accent' => '#ea580c', 'footer_border' => 'line'],
            'rows' => [
                self::row('60-40', [
                    [self::el('title', [], ['font_size' => 32, 'color' => 'text']), self::el('stamp', [], ['margin_top' => 6])],
                    [self::el('logo', ['height' => 50], ['align' => 'right'])],
                ]),
                self::one(self::el('divider', ['thickness' => 3, 'color' => 'accent', 'width' => 12], ['margin_top' => 6])),
                self::row('33-33-34', [
                    [self::el('company', ['heading' => 'From', 'name_size' => 11, 'contact' => 'stacked', 'tax_numbers' => 'stacked'])],
                    [self::el('customer')],
                    [self::el('details', ['layout' => 'stacked', 'heading' => 'Details'])],
                ], ['margin_top' => 22, 'gap' => 18]),
                self::one(self::el('extra', [], $line)),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'plain', 'header_color' => 'text', 'header_line' => 'accent'], ['margin_top' => 16])),
                self::summary([self::el('totals', ['grand_style' => 'line', 'grand_size' => 13])], $line, ['margin_top' => 14], '50-50'),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function noir(): array
    {
        return [
            'page' => ['accent' => '#000000', 'text' => '#111111', 'footer_border' => 'line'],
            'rows' => [
                self::row('60-40', [
                    [self::el('logo', ['height' => 44]), self::el('company', [], ['margin_top' => 6])],
                    [self::el('title', [], ['align' => 'right', 'font_size' => 34, 'uppercase' => true, 'letter_spacing' => 3, 'color' => 'text'])],
                ]),
                self::one(self::el('divider', ['thickness' => 3, 'color' => 'accent'], ['margin_top' => 10])),
                self::row('60-40', [
                    [self::el('customer')],
                    [self::el('stamp', [], ['align' => 'right']), self::el('details', ['label_width' => 55], ['align' => 'right', 'margin_top' => 4])],
                ], ['margin_top' => 16]),
                self::one(self::el('extra', [], ['margin_top' => 12, ...self::BOXED])),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'fill', 'header_background' => 'accent'], ['margin_top' => 14])),
                self::summary([self::el('totals', ['grand_style' => 'fill', 'grand_size' => 13])], ['margin_top' => 10, ...self::BOXED]),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function breeze(): array
    {
        $rounded = ['radius' => 8, 'padding_y' => 10, 'padding_x' => 12];

        return [
            'page' => ['accent' => '#0891b2', 'footer_border' => 'none', 'label_color' => 'accent'],
            'rows' => [
                self::row('60-40', [
                    [self::el('logo', ['height' => 50]), self::el('company', ['address' => 'inline'], ['margin_top' => 6])],
                    [
                        self::el('title', [], ['align' => 'right', 'font_size' => 22]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 55], ['align' => 'right', 'margin_top' => 8]),
                    ],
                ]),
                self::row('50-50', [
                    [self::el('customer', [], ['background' => 'faint', ...$rounded])],
                    [self::el('amount_due', ['value_size' => 18], ['background' => 'soft', ...$rounded])],
                ], ['margin_top' => 16, 'gap' => 14]),
                self::one(self::el('extra', [], ['margin_top' => 12, 'background' => 'faint', ...$rounded])),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'fill', 'header_background' => 'soft', 'header_color' => 'accent', 'row_style' => 'striped'], ['margin_top' => 14])),
                self::summary(
                    [self::el('totals', ['grand_style' => 'fill'], ['background' => 'faint', 'radius' => 8, 'padding_y' => 8, 'padding_x' => 10])],
                    ['margin_top' => 10, 'background' => 'faint', ...$rounded],
                    ['margin_top' => 14, 'gap' => 14],
                    '50-50',
                ),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summit(): array
    {
        return [
            'page' => ['accent' => '#7c3aed', 'decoration' => 'left_stripe', 'footer_border' => 'none', 'label_color' => 'accent'],
            'rows' => [
                self::row('65-35', [
                    [self::el('company', ['name_size' => 16, 'name_color' => 'accent', 'address' => 'inline'])],
                    [self::el('logo', ['height' => 50], ['align' => 'right'])],
                ]),
                self::one([
                    self::el('title', [], ['font_size' => 20, 'uppercase' => true, 'letter_spacing' => 2]),
                    self::el('stamp', [], ['margin_top' => 6]),
                    self::el('details', ['layout' => 'columns'], ['margin_top' => 10]),
                ], ['margin_top' => 18]),
                self::row('50-50', [[self::el('customer')], [self::el('extra')]], ['margin_top' => 16, 'gap' => 20]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'lines', 'header_color' => 'accent', 'header_line' => 'accent'], ['margin_top' => 14])),
                self::summary(
                    [self::el('totals', ['grand_style' => 'soft'])],
                    ['margin_top' => 10, 'border' => 'box', 'border_color' => 'soft', 'radius' => 4, 'padding_y' => 8, 'padding_x' => 10],
                ),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function atlas(): array
    {
        $barred = ['border' => 'box', 'border_color' => 'soft'];

        return [
            'page' => ['accent' => '#9f1239', 'footer_border' => 'accent'],
            'rows' => [
                self::row('45-55', [
                    self::cell([
                        self::el('logo', ['height' => 44]),
                        self::el('company', ['name_size' => 14, 'address' => 'inline', 'contact' => 'stacked'], ['margin_top' => 6]),
                    ], ['background' => 'accent', 'padding_y' => 16, 'padding_x' => 16]),
                    self::cell([
                        self::el('title', [], ['align' => 'right', 'font_size' => 26]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 6]),
                        self::el('details', ['label_width' => 55], ['align' => 'right', 'margin_top' => 8]),
                    ], ['valign' => 'middle']),
                ], ['gap' => 20]),
                self::row('50-50', [
                    [self::el('customer', ['heading_style' => 'bar'], $barred)],
                    [self::el('extra', ['heading_style' => 'bar'], $barred)],
                ], ['margin_top' => 18]),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'light', 'header_background' => 'faint', 'header_color' => 'accent', 'header_line' => 'accent'], ['margin_top' => 14])),
                self::summary([self::el('totals', ['grand_style' => 'fill'])], ['margin_top' => 10, ...self::BOXED, 'border_color' => 'soft']),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function prism(): array
    {
        return [
            'page' => ['accent' => '#4f46e5', 'footer_border' => 'line'],
            'rows' => [
                self::row('60-40', [
                    [self::el('logo', ['height' => 48]), self::el('company', [], ['margin_top' => 6])],
                    [
                        self::el('title', ['fit' => true], [
                            'align' => 'right', 'font_size' => 13, 'uppercase' => true, 'letter_spacing' => 2, 'color' => 'auto',
                            'background' => 'accent', 'padding_y' => 5, 'padding_x' => 14, 'radius' => 12,
                        ]),
                        self::el('stamp', [], ['align' => 'right', 'margin_top' => 8]),
                        self::el('details', ['label_width' => 55], ['align' => 'right', 'margin_top' => 10]),
                    ],
                ]),
                self::row('50-50', [
                    [self::el('customer')],
                    [self::el('amount_due', ['value_size' => 20, 'value_color' => 'accent'])],
                ], ['margin_top' => 20]),
                self::one(self::el('extra', [], ['margin_top' => 12, 'background' => 'faint', 'radius' => 6, 'padding_y' => 8, 'padding_x' => 10])),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'fill', 'header_background' => 'faint', 'header_color' => 'accent', 'row_style' => 'striped'], ['margin_top' => 14])),
                self::summary(
                    [self::el('totals', ['grand_style' => 'fill'])],
                    ['margin_top' => 10, 'border' => 'box', 'border_color' => 'soft', 'radius' => 6, 'padding_y' => 8, 'padding_x' => 10],
                ),
                ...self::ending(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function clarity(): array
    {
        return [
            'page' => ['accent' => '#0369a1', 'footer_align' => 'center', 'footer_border' => 'line'],
            'rows' => [
                self::one(self::el('logo', ['height' => 50], ['align' => 'center'])),
                self::one([
                    self::el('title', [], ['align' => 'center', 'font_size' => 22, 'color' => 'text']),
                    self::el('stamp', [], ['align' => 'center', 'margin_top' => 6]),
                    self::el('details', ['layout' => 'inline'], ['align' => 'center', 'margin_top' => 4]),
                ], ['margin_top' => 10]),
                self::one(self::el('divider', ['color' => 'grey'], ['margin_top' => 14])),
                self::row('50-50', [
                    [self::el('company', ['heading' => 'From', 'name_size' => 11, 'contact' => 'stacked', 'tax_numbers' => 'stacked'])],
                    [self::el('customer')],
                ], ['margin_top' => 14, 'gap' => 20]),
                self::one(self::el('extra', [], ['margin_top' => 12, ...self::BOXED])),
                self::one(self::el('heading', [], ['margin_top' => 12])),
                self::one(self::el('items', ['header_style' => 'light', 'header_background' => 'grey', 'header_line' => 'grey'], ['margin_top' => 16])),
                self::summary([self::el('totals', ['grand_style' => 'line', 'grand_size' => 13])], ['margin_top' => 10, ...self::BOXED], ['margin_top' => 14], '50-50'),
                ...self::ending(),
            ],
        ];
    }

    /**
     * The row below the items: amount in words, bank details and QR code on the left, totals on the right.
     *
     * @param  list<array<string, mixed>>  $totals
     * @param  array<string, mixed>  $payment  the style of the bank details
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private static function summary(array $totals, array $payment, array $style = ['margin_top' => 12], string $layout = '55-45'): array
    {
        return self::row($layout, [
            [self::el('words'), self::el('payment', [], $payment), self::el('qr', [], ['margin_top' => 10])],
            $totals,
        ], [...$style, 'keep_together' => true]);
    }

    /**
     * The end of a document: terms, acceptance of a quotation, signature lines, closing note and footer text.
     *
     * @param  array<string, mixed>  $closing  the style of the closing note
     * @param  array<string, mixed>  $signature  the settings of the signature lines
     * @return list<array<string, mixed>>
     */
    private static function ending(array $closing = [], array $signature = []): array
    {
        return [
            self::one(self::el('terms', [], ['margin_top' => 16])),
            self::row('55-45', [[], [self::el('acceptance', [], ['margin_top' => 18, ...self::BOXED])]]),
            self::one(self::el('signature', $signature, ['margin_top' => 24])),
            self::one(self::el('closing', [], ['margin_top' => 18, ...$closing])),
            self::one(self::el('footer_text', [], ['margin_top' => 14])),
        ];
    }

    /**
     * A row with cells; a cell is a list of elements or made with cell().
     *
     * @param  list<array<mixed>>  $cells
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private static function row(string $layout, array $cells, array $style = []): array
    {
        return [
            'layout' => $layout,
            'style' => $style,
            'cells' => array_map(fn (array $cell) => array_key_exists('elements', $cell) ? $cell : ['elements' => $cell], $cells),
        ];
    }

    /**
     * A one-column row with one element or a list of elements.
     *
     * @param  array<mixed>  $elements
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private static function one(array $elements, array $style = []): array
    {
        return self::row('full', [array_key_exists('type', $elements) ? [$elements] : $elements], $style);
    }

    /**
     * A cell with its own style.
     *
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private static function cell(array $elements, array $style): array
    {
        return ['style' => $style, 'elements' => $elements];
    }

    /**
     * An element.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private static function el(string $type, array $settings = [], array $style = []): array
    {
        return ['type' => $type, 'settings' => $settings, 'style' => $style];
    }
}
