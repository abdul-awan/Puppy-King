<?php

namespace App\Support\Documents\Templates;

/**
 * The elements of the template builder: what each one shows, its settings and
 * the style fields it can use. This one list drives the settings panel of the
 * builder (it is sent to the browser), the validation of submitted templates
 * and the PDF renderer, so they cannot drift apart.
 *
 * Every value is normalised to a fixed list, a range, a #rrggbb colour or a
 * text of limited length, so it is safe to put into the CSS and HTML of a PDF.
 */
final class ElementTypes
{
    /**
     * Style fields of elements, rows and cells (each uses a subset).
     *
     * @var array<string, array<string, mixed>>
     */
    public const STYLE = [
        'align' => ['kind' => 'choice', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'], 'default' => 'left'],
        'font' => ['kind' => 'choice', 'label' => 'Type', 'options' => ['inherit' => 'Same as the page', 'sans' => 'Sans-serif', 'serif' => 'Serif', 'mono' => 'Monospace'], 'default' => 'inherit'],
        'font_size' => ['kind' => 'number', 'label' => 'Text size (px)', 'hint' => '0 uses the text size of the page', 'min' => 0, 'max' => 40, 'step' => 0.5, 'default' => 0],
        'color' => ['kind' => 'color', 'label' => 'Text colour', 'tokens' => 'text', 'default' => 'auto'],
        'weight' => ['kind' => 'choice', 'label' => 'Weight', 'options' => ['inherit' => 'As designed', 'normal' => 'Regular', 'bold' => 'Bold'], 'default' => 'inherit'],
        'italic' => ['kind' => 'bool', 'label' => 'Italic', 'default' => false],
        'uppercase' => ['kind' => 'bool', 'label' => 'Capitals', 'default' => false],
        'letter_spacing' => ['kind' => 'number', 'label' => 'Letter spacing (px)', 'min' => 0, 'max' => 12, 'step' => 0.1, 'default' => 0],
        'background' => ['kind' => 'color', 'label' => 'Background', 'tokens' => 'background', 'default' => 'none'],
        'padding_y' => ['kind' => 'number', 'label' => 'Inner space above and below (px)', 'min' => 0, 'max' => 40, 'step' => 1, 'default' => 0],
        'padding_x' => ['kind' => 'number', 'label' => 'Inner space left and right (px)', 'min' => 0, 'max' => 40, 'step' => 1, 'default' => 0],
        'margin_top' => ['kind' => 'number', 'label' => 'Space above (px)', 'min' => 0, 'max' => 80, 'step' => 1, 'default' => 0],
        'border' => ['kind' => 'choice', 'label' => 'Border', 'options' => ['none' => 'None', 'box' => 'All around', 'top' => 'Top', 'bottom' => 'Bottom', 'top_bottom' => 'Top and bottom', 'left' => 'Left'], 'default' => 'none'],
        'border_color' => ['kind' => 'color', 'label' => 'Border colour', 'tokens' => 'line', 'default' => 'grey'],
        'border_width' => ['kind' => 'number', 'label' => 'Border width (px)', 'min' => 0.5, 'max' => 6, 'step' => 0.5, 'default' => 1],
        'border_style' => ['kind' => 'choice', 'label' => 'Border style', 'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'], 'default' => 'solid'],
        'radius' => ['kind' => 'number', 'label' => 'Rounded corners (px)', 'min' => 0, 'max' => 20, 'step' => 1, 'default' => 0],
        // Rows and cells only.
        'gap' => ['kind' => 'number', 'label' => 'Space between the columns (px)', 'min' => 0, 'max' => 40, 'step' => 1, 'default' => 16],
        'keep_together' => ['kind' => 'bool', 'label' => 'Keep on one page', 'default' => false],
        'valign' => ['kind' => 'choice', 'label' => 'Vertical alignment', 'options' => ['top' => 'Top', 'middle' => 'Middle', 'bottom' => 'Bottom'], 'default' => 'top'],
    ];

    /**
     * Groups of the element palette.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'document' => 'Document',
        'amounts' => 'Amounts and payment',
        'content' => 'Text and images',
        'layout' => 'Layout',
    ];

    /** @var list<string> */
    private const TEXT_STYLE = ['align', 'font', 'font_size', 'color', 'weight', 'italic', 'uppercase', 'letter_spacing', 'background', 'padding_y', 'padding_x', 'margin_top', 'border', 'border_color', 'border_width', 'border_style', 'radius'];

    /** @var list<string> */
    private const BOX_STYLE = ['align', 'background', 'padding_y', 'padding_x', 'margin_top', 'border', 'border_color', 'border_width', 'border_style', 'radius'];

    /** @var list<string> */
    private const TABLE_STYLE = ['font', 'font_size', 'color', 'background', 'padding_y', 'padding_x', 'margin_top', 'border', 'border_color', 'border_width', 'border_style', 'radius'];

    /** @var array<string, string> */
    private const LINE_MODES = ['stacked' => 'One per line', 'inline' => 'All on one line', 'hidden' => 'Hidden'];

    /** @var array<string, string> */
    private const HEADING_STYLES = ['label' => 'Small label', 'bar' => 'Coloured bar'];

    /**
     * The element types, cached.
     *
     * @var array<string, array{label: string, description: string, icon: string, group: string, settings: array<string, array<string, mixed>>, style: list<string>, defaults: array<string, mixed>}>|null
     */
    private static ?array $types = null;

    /**
     * Get all element types.
     *
     * @return array<string, array{label: string, description: string, icon: string, group: string, settings: array<string, array<string, mixed>>, style: list<string>, defaults: array<string, mixed>}>
     */
    public static function all(): array
    {
        return self::$types ??= [
            'logo' => [
                'label' => 'Logo',
                'description' => 'The logo of the company profile.',
                'icon' => 'image',
                'group' => 'document',
                'settings' => [
                    'height' => self::number('Height (px)', 16, 160, 1, 60),
                ],
                'style' => self::BOX_STYLE,
                'defaults' => [],
            ],
            'company' => [
                'label' => 'Company details',
                'description' => 'Your name, address, contact details and tax numbers from the company profile.',
                'icon' => 'building',
                'group' => 'document',
                'settings' => [
                    'heading' => self::text('Small heading above', 40, '', 'e.g. From'),
                    'show_name' => self::bool('Show the company name', true),
                    'name_size' => self::number('Name size (px)', 7, 32, 0.5, 13),
                    'name_color' => self::color('Name colour', 'text', 'auto'),
                    'name_bold' => self::bool('Name in bold', true),
                    'name_uppercase' => self::bool('Name in capitals', false),
                    'name_letter_spacing' => self::number('Letter spacing of the name (px)', 0, 8, 0.1, 0),
                    'address' => self::choice('Address', self::LINE_MODES, 'stacked'),
                    'contact' => self::choice('Phone, e-mail and website', self::LINE_MODES, 'inline'),
                    'tax_numbers' => self::choice('NTN and STRN', self::LINE_MODES, 'inline'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'title' => [
                'label' => 'Document title',
                'description' => '“Invoice”, “Sales Tax Invoice”, “Quotation” … or your own titles from the invoice settings.',
                'icon' => 'heading',
                'group' => 'document',
                'settings' => [
                    'fit' => self::bool('Background only behind the text', false, 'With a background this makes a label (pill)'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => ['font_size' => 20, 'weight' => 'bold', 'color' => 'accent'],
            ],
            'stamp' => [
                'label' => 'Status stamp',
                'description' => 'DRAFT, PAID, ACCEPTED or CREDITED, when it applies.',
                'icon' => 'stamp',
                'group' => 'document',
                'settings' => [
                    'size' => self::number('Text size (px)', 7, 24, 0.5, 10.5),
                ],
                'style' => self::BOX_STYLE,
                'defaults' => [],
            ],
            'details' => [
                'label' => 'Document details',
                'description' => 'The number, the dates and other details of the document.',
                'icon' => 'list',
                'group' => 'document',
                'settings' => [
                    'layout' => self::choice('Layout', [
                        'table' => 'Labels and values side by side',
                        'stacked' => 'Each label above its value',
                        'columns' => 'Next to each other',
                        'inline' => 'All on one line',
                    ], 'table'),
                    'heading' => self::text('Small heading above', 40, '', 'e.g. Details'),
                    'label_width' => self::number('Width of the labels (%)', 20, 80, 1, 45),
                    'label_color' => self::color('Label colour', 'text', 'muted'),
                    'value_bold' => self::bool('Values in bold', true),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'customer' => [
                'label' => 'Customer',
                'description' => 'The name, address and tax numbers of the customer.',
                'icon' => 'user',
                'group' => 'document',
                'settings' => [
                    'show_heading' => self::bool('Show the heading', true),
                    'heading' => self::text('Heading', 40, '', 'Automatic: Bill to, Prepared for or Customer'),
                    'heading_style' => self::choice('Heading style', self::HEADING_STYLES, 'label'),
                    'bar_background' => self::color('Colour of the bar', 'background', 'soft'),
                    'name_size' => self::number('Name size (px)', 7, 24, 0.5, 11),
                    'show_address' => self::bool('Show the address', true),
                    'show_tax_numbers' => self::bool('Show NTN, STRN and CNIC', true),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'extra' => [
                'label' => 'Extra information',
                'description' => 'Extra details of a document, such as the reason of a credit note. Hidden when there are none.',
                'icon' => 'info',
                'group' => 'document',
                'settings' => [
                    'show_heading' => self::bool('Show the heading', true),
                    'heading_style' => self::choice('Heading style', self::HEADING_STYLES, 'label'),
                    'bar_background' => self::color('Colour of the bar', 'background', 'soft'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'heading' => [
                'label' => 'Subject and introduction',
                'description' => 'The subject you give a document (e.g. “Office furniture”) and its introduction text.',
                'icon' => 'pilcrow',
                'group' => 'document',
                'settings' => [
                    'show_title' => self::bool('Show the subject', true),
                    'title_size' => self::number('Subject size (px)', 7, 24, 0.5, 11),
                    'show_introduction' => self::bool('Show the introduction', true),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'items' => [
                'label' => 'Items table',
                'description' => 'The lines of the document. Which columns show is set in the invoice settings.',
                'icon' => 'table',
                'group' => 'amounts',
                'settings' => [
                    'header_style' => self::choice('Header', [
                        'fill' => 'Coloured background',
                        'light' => 'Light background with a line',
                        'lines' => 'Lines above and below',
                        'plain' => 'Line below',
                    ], 'light'),
                    'header_background' => self::color('Header background', 'background', 'grey'),
                    'header_color' => self::color('Header text colour', 'text', 'auto'),
                    'header_line' => self::color('Header line colour', 'line', 'accent'),
                    'header_bold' => self::bool('Header in bold', true),
                    'header_uppercase' => self::bool('Header in capitals', true),
                    'row_style' => self::choice('Rows', [
                        'lines' => 'Lines between the rows',
                        'striped' => 'Striped rows',
                        'grid' => 'Full grid',
                        'plain' => 'No lines',
                    ], 'lines'),
                    'row_line' => self::color('Line colour of the rows', 'line', 'grey'),
                    'line_style' => self::choice('Line style', ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted'], 'solid'),
                    'section_style' => self::choice('Section headers', [
                        'bold' => 'Bold',
                        'band' => 'Bold on a light band',
                        'italic' => 'Italic in the accent colour',
                    ], 'bold'),
                    'compact' => self::choice('Compact', ['auto' => 'When there are many columns', 'on' => 'Always', 'off' => 'Never'], 'auto'),
                    'show_details' => self::bool('Show the details under a description', true),
                ],
                'style' => ['font', 'font_size', 'color', 'margin_top'],
                'defaults' => [],
            ],
            'totals' => [
                'label' => 'Totals',
                'description' => 'Subtotal, discount, tax and total, and on invoices what is paid and still due.',
                'icon' => 'sigma',
                'group' => 'amounts',
                'settings' => [
                    'width' => self::number('Width (%)', 30, 100, 1, 100),
                    'position' => self::choice('Position', ['right' => 'Right', 'center' => 'Centre', 'left' => 'Left'], 'right'),
                    'rows_style' => self::choice('Rows', ['plain' => 'No lines', 'lines' => 'Lines between the rows', 'boxed' => 'In a ruled table'], 'plain'),
                    'grand_style' => self::choice('Total row', [
                        'line' => 'Line above',
                        'fill' => 'Coloured background',
                        'soft' => 'Light coloured background',
                        'double' => 'Double lines',
                        'box' => 'Box',
                        'plain' => 'Bold only',
                    ], 'line'),
                    'grand_size' => self::number('Size of the total (px)', 8, 24, 0.5, 12),
                    'label_color' => self::color('Label colour', 'text', 'muted'),
                    'show_tax_note' => self::bool('Show “All amounts include sales tax.” when tax is hidden', true),
                ],
                'style' => self::TABLE_STYLE,
                'defaults' => [],
            ],
            'amount_due' => [
                'label' => 'Amount due',
                'description' => 'The total, or the balance due of a part-paid invoice, in large type.',
                'icon' => 'banknote',
                'group' => 'amounts',
                'settings' => [
                    'label' => self::text('Label', 40, '', 'Automatic: Total or Balance due'),
                    'value_size' => self::number('Size of the amount (px)', 10, 40, 0.5, 16),
                    'value_color' => self::color('Colour of the amount', 'text', 'auto'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => ['align' => 'right'],
            ],
            'words' => [
                'label' => 'Amount in words',
                'description' => 'The total in words, e.g. “Rupees three lakh only”.',
                'icon' => 'case-sensitive',
                'group' => 'amounts',
                'settings' => [
                    'show_label' => self::bool('Show the label', true),
                    'label' => self::text('Label', 40, 'Amount in words'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'payment' => [
                'label' => 'Bank details',
                'description' => 'How to pay, from the company profile (on invoices).',
                'icon' => 'landmark',
                'group' => 'amounts',
                'settings' => [
                    'show_label' => self::bool('Show the label', true),
                    'label' => self::text('Label', 40, '', 'Payment details'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'qr' => [
                'label' => 'QR code',
                'description' => 'Opens the document online when it is scanned (issued documents).',
                'icon' => 'qr-code',
                'group' => 'amounts',
                'settings' => [
                    'size' => self::number('Size (px)', 40, 160, 1, 74),
                    'caption_position' => self::choice('Text', ['right' => 'Next to the code', 'below' => 'Below the code', 'none' => 'No text'], 'right'),
                    'caption' => self::text('Own text', 120, '', 'Scan to open this invoice online.'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'terms' => [
                'label' => 'Terms & conditions',
                'description' => 'The terms written on the document.',
                'icon' => 'scroll-text',
                'group' => 'content',
                'settings' => [
                    'show_label' => self::bool('Show the label', true),
                    'label' => self::text('Label', 40, 'Terms & conditions'),
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'signature' => [
                'label' => 'Signature lines',
                'description' => 'Lines (or boxes) to sign and stamp. Switched on in the invoice settings.',
                'icon' => 'signature',
                'group' => 'content',
                'settings' => [
                    'mode' => self::choice('Lines', ['auto' => 'As set in the invoice settings', 'custom' => 'My own labels'], 'auto'),
                    'labels' => ['kind' => 'labels', 'label' => 'Own labels (up to 3)', 'max' => 3, 'length' => 40, 'default' => ['Received by', 'Authorised signature']],
                    'variant' => self::choice('Style', ['line' => 'Line', 'box' => 'Box for stamp and signature'], 'line'),
                    'space' => self::number('Space to sign (px)', 10, 90, 1, 26),
                    'show_company' => self::bool('Write “For <company>” under the last line', true),
                ],
                'style' => ['align', 'font', 'font_size', 'color', 'margin_top'],
                'defaults' => ['align' => 'right'],
            ],
            'acceptance' => [
                'label' => 'Customer acceptance',
                'description' => 'The name and signature of the customer on an accepted quotation.',
                'icon' => 'pen-line',
                'group' => 'content',
                'settings' => [],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'closing' => [
                'label' => 'Closing note',
                'description' => 'The closing note of the invoice settings, e.g. “Thank you for your business!”.',
                'icon' => 'message-square',
                'group' => 'content',
                'settings' => [],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'footer_text' => [
                'label' => 'Company footer text',
                'description' => 'The footer text of the company profile.',
                'icon' => 'panel-bottom',
                'group' => 'content',
                'settings' => [],
                'style' => self::TEXT_STYLE,
                'defaults' => ['align' => 'center', 'color' => 'muted'],
            ],
            'text' => [
                'label' => 'Text',
                'description' => 'Your own text. Placeholders like {document_number} are filled in.',
                'icon' => 'type',
                'group' => 'content',
                'settings' => [
                    'text' => ['kind' => 'textarea', 'label' => 'Text', 'max' => 2000, 'default' => 'Thank you for your business!'],
                ],
                'style' => self::TEXT_STYLE,
                'defaults' => [],
            ],
            'image' => [
                'label' => 'Image',
                'description' => 'An image you upload, such as your stamp, a seal or a certificate.',
                'icon' => 'image-plus',
                'group' => 'content',
                'settings' => [
                    'image' => ['kind' => 'image', 'label' => 'Image', 'default' => ''],
                    'height' => self::number('Height (px)', 10, 260, 1, 60),
                ],
                'style' => self::BOX_STYLE,
                'defaults' => [],
            ],
            'divider' => [
                'label' => 'Divider',
                'description' => 'A horizontal line.',
                'icon' => 'minus',
                'group' => 'layout',
                'settings' => [
                    'line' => self::choice('Line', ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double'], 'solid'),
                    'thickness' => self::number('Thickness (px)', 0.5, 8, 0.5, 1),
                    'color' => self::color('Colour', 'line', 'grey'),
                    'width' => self::number('Width (%)', 5, 100, 1, 100),
                ],
                'style' => ['align', 'margin_top'],
                'defaults' => [],
            ],
            'spacer' => [
                'label' => 'Space',
                'description' => 'Empty space between elements.',
                'icon' => 'move-vertical',
                'group' => 'layout',
                'settings' => [
                    'height' => self::number('Height (px)', 2, 160, 1, 16),
                ],
                'style' => [],
                'defaults' => [],
            ],
        ];
    }

    /**
     * Determine if an element type exists.
     */
    public static function exists(mixed $type): bool
    {
        return is_string($type) && array_key_exists($type, self::all());
    }

    /**
     * Normalise a submitted element; null when it is not an element of a known type.
     *
     * @param  array<string, true>  $ids  the ids taken so far (updated)
     * @return array{id: string, type: string, settings: array<string, mixed>, style: array<string, mixed>}|null
     */
    public static function normalize(mixed $element, array &$ids): ?array
    {
        if (! is_array($element) || ! self::exists($element['type'] ?? null)) {
            return null;
        }

        $type = (string) $element['type'];
        $definition = self::all()[$type];

        return [
            'id' => self::id($element['id'] ?? null, 'e', $ids),
            'type' => $type,
            'settings' => self::normalizeFields($definition['settings'], (array) ($element['settings'] ?? [])),
            'style' => self::normalizeFields(self::styleFields($definition['style'], $definition['defaults']), (array) ($element['style'] ?? [])),
        ];
    }

    /**
     * Get the default settings and style of a new element of a type.
     *
     * @return array{settings: array<string, mixed>, style: array<string, mixed>}
     */
    public static function blank(string $type): array
    {
        $definition = self::all()[$type];

        return [
            'settings' => self::normalizeFields($definition['settings'], []),
            'style' => self::normalizeFields(self::styleFields($definition['style'], $definition['defaults']), []),
        ];
    }

    /**
     * Get the style fields with the given keys, with other defaults where given.
     *
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $defaults
     * @return array<string, array<string, mixed>>
     */
    public static function styleFields(array $keys, array $defaults = []): array
    {
        $fields = [];

        foreach ($keys as $key) {
            $fields[$key] = [...self::STYLE[$key], ...(array_key_exists($key, $defaults) ? ['default' => $defaults[$key]] : [])];
        }

        return $fields;
    }

    /**
     * Normalise values to their fields: unknown keys are dropped, missing or invalid values get the default.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeFields(array $fields, array $input): array
    {
        $values = [];

        foreach ($fields as $key => $field) {
            $values[$key] = self::normalizeField($field, $input[$key] ?? null);
        }

        return $values;
    }

    /**
     * Normalise one value to its field.
     *
     * @param  array<string, mixed>  $field
     */
    public static function normalizeField(array $field, mixed $value): mixed
    {
        $default = $field['default'];

        return match ($field['kind']) {
            'bool' => is_bool($value) ? $value : ($value === null ? $default : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default)),
            'choice' => (is_string($value) || is_int($value)) && array_key_exists((string) $value, (array) $field['options']) ? (string) $value : $default,
            'number' => is_numeric($value) ? self::clamp((float) $value, (float) $field['min'], (float) $field['max'], (float) $field['step']) : $default,
            'color' => self::normalizeColor($field, $value),
            'text' => is_string($value) ? mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)), 0, (int) $field['max']) : $default,
            'textarea' => is_string($value) ? mb_substr(trim((string) preg_replace(['/\r\n?/', '/[\x00-\x08\x0B-\x1F\x7F]/u'], ["\n", ''], $value)), 0, (int) $field['max']) : $default,
            'labels' => is_array($value) ? self::labels($value, (int) $field['max'], (int) $field['length']) : $default,
            'image' => is_string($value) && preg_match('/^[A-Za-z0-9-]{8,64}\.(png|jpe?g|gif)$/', $value) === 1 ? $value : '',
            default => $default,
        };
    }

    /**
     * Get the token lists of the colour fields.
     *
     * @return array<string, array<string, string>>
     */
    public static function colorTokens(): array
    {
        return [
            'text' => Palette::TEXTS,
            'line' => Palette::LINES,
            'background' => Palette::BACKGROUNDS,
        ];
    }

    /**
     * Get everything the builder in the browser needs to know about the elements.
     *
     * @return array<string, mixed>
     */
    public static function forBrowser(): array
    {
        $types = [];

        foreach (self::all() as $type => $definition) {
            $blank = self::blank($type);
            $types[$type] = [
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'group' => $definition['group'],
                'settings' => $definition['settings'],
                'style' => $definition['style'],
                'defaults' => $blank,
            ];
        }

        return [
            'types' => $types,
            'groups' => self::GROUPS,
            'style' => self::STYLE,
            'colors' => self::colorTokens(),
        ];
    }

    /**
     * Get a free id: the given one when it is valid and not taken, otherwise the next "<prefix><n>".
     *
     * @param  array<string, true>  $ids
     */
    public static function id(mixed $candidate, string $prefix, array &$ids): string
    {
        if (is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{1,24}$/', $candidate) === 1 && ! isset($ids[$candidate])) {
            return self::take($candidate, $ids);
        }

        for ($number = count($ids) + 1; isset($ids[$prefix.$number]); $number++) {
            // Find the next free number.
        }

        return self::take($prefix.$number, $ids);
    }

    /**
     * Mark an id as taken.
     *
     * @param  array<string, true>  $ids
     */
    private static function take(string $id, array &$ids): string
    {
        $ids[$id] = true;

        return $id;
    }

    /**
     * Normalise a colour: one of the tokens of its kind or a #rrggbb colour.
     *
     * @param  array<string, mixed>  $field
     */
    private static function normalizeColor(array $field, mixed $value): string
    {
        $tokens = self::colorTokens()[(string) $field['tokens']] ?? [];

        if (is_string($value) && array_key_exists($value, $tokens)) {
            return $value;
        }

        return Palette::isHex($value) ? strtolower((string) $value) : (string) $field['default'];
    }

    /**
     * Clamp a number to a range, on the steps of the field.
     */
    private static function clamp(float $value, float $min, float $max, float $step): int|float
    {
        $value = max($min, min($max, $value));
        $value = $step >= 1 ? round($value) : round(round($value / $step) * $step, 2);

        return $step >= 1 ? (int) $value : $value;
    }

    /**
     * Normalise a list of labels.
     *
     * @param  array<mixed>  $value
     * @return list<string>
     */
    private static function labels(array $value, int $max, int $length): array
    {
        $labels = [];

        foreach (array_values($value) as $label) {
            if (is_string($label) && trim($label) !== '' && count($labels) < $max) {
                $labels[] = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $label)), 0, $length);
            }
        }

        return $labels;
    }

    /**
     * A choice field.
     *
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private static function choice(string $label, array $options, string $default): array
    {
        return ['kind' => 'choice', 'label' => $label, 'options' => $options, 'default' => $default];
    }

    /**
     * An on/off field.
     *
     * @return array<string, mixed>
     */
    private static function bool(string $label, bool $default, ?string $hint = null): array
    {
        return array_filter(['kind' => 'bool', 'label' => $label, 'default' => $default, 'hint' => $hint], fn ($value) => $value !== null);
    }

    /**
     * A number field.
     *
     * @return array<string, mixed>
     */
    private static function number(string $label, float $min, float $max, float $step, float $default): array
    {
        return ['kind' => 'number', 'label' => $label, 'min' => $min, 'max' => $max, 'step' => $step, 'default' => $step >= 1 ? (int) $default : $default];
    }

    /**
     * A colour field.
     *
     * @return array<string, mixed>
     */
    private static function color(string $label, string $tokens, string $default): array
    {
        return ['kind' => 'color', 'label' => $label, 'tokens' => $tokens, 'default' => $default];
    }

    /**
     * A one-line text field.
     *
     * @return array<string, mixed>
     */
    private static function text(string $label, int $max, string $default, ?string $placeholder = null): array
    {
        return array_filter(['kind' => 'text', 'label' => $label, 'max' => $max, 'default' => $default, 'placeholder' => $placeholder], fn ($value) => $value !== null);
    }
}
