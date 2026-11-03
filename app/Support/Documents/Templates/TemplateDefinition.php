<?php

namespace App\Support\Documents\Templates;

use App\Enums\TemplateLayout;
use Generator;

/**
 * A template made with the builder: the page style and a list of rows. A row
 * has one to four columns (cells) and every cell holds elements in order
 * (logo, company details, items table, totals, text, ...), each with its own
 * settings and style.
 *
 * Templates are normalised on the way in: unknown elements and keys are
 * dropped, numbers are kept within their range and every colour is a token or
 * a #rrggbb value, so a stored template is always safe to render.
 *
 * @phpstan-type Element array{id: string, type: string, settings: array<string, mixed>, style: array<string, mixed>}
 * @phpstan-type Cell array{style: array<string, mixed>, elements: list<Element>}
 * @phpstan-type Row array{id: string, layout: string, style: array<string, mixed>, cells: list<Cell>}
 */
final readonly class TemplateDefinition
{
    public const VERSION = 2;

    /**
     * The column layouts of rows: the width of every column in %.
     *
     * @var array<string, list<int>>
     */
    public const LAYOUTS = [
        'full' => [100],
        '50-50' => [50, 50],
        '55-45' => [55, 45],
        '45-55' => [45, 55],
        '60-40' => [60, 40],
        '40-60' => [40, 60],
        '65-35' => [65, 35],
        '35-65' => [35, 65],
        '70-30' => [70, 30],
        '30-70' => [30, 70],
        '33-33-34' => [33, 33, 34],
        '25-50-25' => [25, 50, 25],
        '40-30-30' => [40, 30, 30],
        '30-40-30' => [30, 40, 30],
        '25-25-25-25' => [25, 25, 25, 25],
    ];

    public const MAX_ROWS = 40;

    public const MAX_ELEMENTS = 150;

    public const MAX_PER_CELL = 30;

    /**
     * The style fields of rows.
     *
     * @var list<string>
     */
    public const ROW_STYLE = ['background', 'padding_y', 'padding_x', 'margin_top', 'border', 'border_color', 'border_width', 'border_style', 'radius', 'gap', 'keep_together'];

    /**
     * The style fields of cells.
     *
     * @var list<string>
     */
    public const CELL_STYLE = ['background', 'padding_y', 'padding_x', 'border', 'border_color', 'border_width', 'border_style', 'radius', 'valign'];

    /**
     * @param  list<Row>  $rows
     */
    public function __construct(public PageStyle $page, public array $rows)
    {
        //
    }

    /**
     * Build a template from stored or submitted values.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $ids = [];
        $total = 0;
        $rows = [];
        $rowFields = ElementTypes::styleFields(self::ROW_STYLE);
        $cellFields = ElementTypes::styleFields(self::CELL_STYLE);

        foreach (array_slice(array_values(array_filter((array) ($data['rows'] ?? []), 'is_array')), 0, self::MAX_ROWS) as $row) {
            $id = ElementTypes::id($row['id'] ?? null, 'r', $ids);
            $layout = is_string($row['layout'] ?? null) && array_key_exists($row['layout'], self::LAYOUTS) ? $row['layout'] : 'full';
            $columns = count(self::LAYOUTS[$layout]);
            $input = array_values(array_filter((array) ($row['cells'] ?? []), 'is_array'));
            $cells = [];

            for ($index = 0; $index < $columns; $index++) {
                // The elements of cells beyond the layout (a row that lost columns) move to its last cell.
                $sources = $index === $columns - 1 ? array_slice($input, $index) : [$input[$index] ?? []];
                $elements = [];

                foreach ($sources as $source) {
                    foreach (array_values((array) ($source['elements'] ?? [])) as $element) {
                        if ($total >= self::MAX_ELEMENTS || count($elements) >= self::MAX_PER_CELL) {
                            break 2;
                        }

                        $normalized = ElementTypes::normalize($element, $ids);

                        if ($normalized !== null) {
                            $elements[] = $normalized;
                            $total++;
                        }
                    }
                }

                $cells[] = [
                    'style' => ElementTypes::normalizeFields($cellFields, (array) (($input[$index] ?? [])['style'] ?? [])),
                    'elements' => $elements,
                ];
            }

            $rows[] = [
                'id' => $id,
                'layout' => $layout,
                'style' => ElementTypes::normalizeFields($rowFields, (array) ($row['style'] ?? [])),
                'cells' => $cells,
            ];
        }

        return new self(PageStyle::fromArray((array) ($data['page'] ?? [])), $rows);
    }

    /**
     * Build the template of a stored own template: saved by the builder, or saved before the builder
     * existed (a layout with a colour, type and table style), which becomes the ready-made template
     * of that layout with those choices.
     *
     * @param  array<string, mixed>  $design
     */
    public static function stored(TemplateLayout $layout, array $design): self
    {
        if (isset($design['rows'])) {
            return self::fromArray($design);
        }

        $rowStyle = ['lines' => 'lines', 'striped' => 'striped', 'grid' => 'grid', 'minimal' => 'plain'][$design['table_style'] ?? ''] ?? null;
        $logoHeight = ['small' => 40, 'medium' => 60, 'large' => 90][$design['logo'] ?? ''] ?? null;

        return $layout->definition()
            ->withPage(array_intersect_key($design, array_flip(['accent', 'font', 'font_size', 'margins'])))
            ->mapElements(function (array $element) use ($rowStyle, $logoHeight, $design) {
                if ($element['type'] === 'logo') {
                    if (($design['logo'] ?? null) === 'none') {
                        return null;
                    }

                    $element['settings']['height'] = $logoHeight ?? $element['settings']['height'];
                }

                if ($element['type'] === 'items' && $rowStyle !== null) {
                    $element['settings']['row_style'] = $rowStyle;
                }

                return $element;
            });
    }

    /**
     * A simple starting point for a template of your own.
     */
    public static function blank(): self
    {
        return self::fromArray([
            'page' => [],
            'rows' => [
                ['layout' => '60-40', 'cells' => [
                    ['elements' => [['type' => 'logo'], ['type' => 'company']]],
                    ['elements' => [
                        ['type' => 'title', 'style' => ['align' => 'right']],
                        ['type' => 'stamp', 'style' => ['align' => 'right', 'margin_top' => 6]],
                        ['type' => 'details', 'style' => ['align' => 'right', 'margin_top' => 8]],
                    ]],
                ]],
                ['layout' => '55-45', 'style' => ['margin_top' => 18], 'cells' => [
                    ['elements' => [['type' => 'customer']]],
                    ['elements' => [['type' => 'extra']]],
                ]],
                ['layout' => 'full', 'cells' => [['elements' => [['type' => 'heading', 'style' => ['margin_top' => 12]]]]]],
                ['layout' => 'full', 'style' => ['margin_top' => 12], 'cells' => [['elements' => [['type' => 'items']]]]],
                ['layout' => '55-45', 'style' => ['margin_top' => 12, 'keep_together' => true], 'cells' => [
                    ['elements' => [['type' => 'words'], ['type' => 'payment', 'style' => ['margin_top' => 10, 'border' => 'box', 'radius' => 3, 'padding_y' => 8, 'padding_x' => 10]]]],
                    ['elements' => [['type' => 'totals']]],
                ]],
                ['layout' => 'full', 'cells' => [['elements' => [['type' => 'terms', 'style' => ['margin_top' => 16]]]]]],
            ],
        ]);
    }

    /**
     * Get the template as stored values.
     *
     * @return array{version: int, page: array<string, mixed>, rows: list<Row>}
     */
    public function toArray(): array
    {
        return ['version' => self::VERSION, 'page' => $this->page->toArray(), 'rows' => $this->rows];
    }

    /**
     * Get every element of the template, in order.
     *
     * @return Generator<int, Element>
     */
    public function elements(): Generator
    {
        foreach ($this->rows as $row) {
            foreach ($row['cells'] as $cell) {
                yield from $cell['elements'];
            }
        }
    }

    /**
     * Get the file names of the uploaded images the template uses.
     *
     * @return list<string>
     */
    public function imageNames(): array
    {
        $names = [];

        foreach ($this->elements() as $element) {
            if ($element['type'] === 'image' && $element['settings']['image'] !== '') {
                $names[] = (string) $element['settings']['image'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Get a copy with other page values.
     *
     * @param  array<string, mixed>  $changes
     */
    public function withPage(array $changes): self
    {
        return new self($this->page->with($changes), $this->rows);
    }

    /**
     * Get a copy with every element passed through a callback (returning null removes the element).
     *
     * @param  callable(Element): (Element|null)  $callback
     */
    public function mapElements(callable $callback): self
    {
        $rows = $this->rows;

        foreach ($rows as $r => $row) {
            foreach ($row['cells'] as $c => $cell) {
                $rows[$r]['cells'][$c]['elements'] = array_values(array_filter(array_map($callback, $cell['elements'])));
            }
        }

        return self::fromArray(['page' => $this->page->toArray(), 'rows' => $rows]);
    }
}
