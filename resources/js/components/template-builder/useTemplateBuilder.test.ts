import { describe, expect, it } from 'vite-plus/test';
import type {
    PageSettings,
    Registry,
    Structure,
    TemplateDefinition,
} from './types';
import type { Builder } from './useTemplateBuilder';
import { useTemplateBuilder } from './useTemplateBuilder';

const field = (
    kind: 'number' | 'bool' | 'choice' | 'color',
    value: unknown,
) => ({
    kind,
    label: '',
    default: value,
});

const registry: Registry = {
    types: {
        text: {
            label: 'Text',
            description: '',
            icon: 'type',
            group: 'content',
            settings: {
                text: { kind: 'textarea', label: 'Text', default: 'Hello' },
            },
            style: ['margin_top'],
            defaults: { settings: { text: 'Hello' }, style: { margin_top: 0 } },
        },
        items: {
            label: 'Items table',
            description: '',
            icon: 'table',
            group: 'amounts',
            settings: {},
            style: ['margin_top'],
            defaults: { settings: {}, style: { margin_top: 0 } },
        },
    },
    groups: { content: 'Text', amounts: 'Amounts' },
    style: {
        margin_top: field('number', 0),
        gap: field('number', 16),
        keep_together: field('bool', false),
        background: field('color', 'none'),
        valign: field('choice', 'top'),
    },
    colors: { text: {}, line: {}, background: {} },
};

const structure: Structure = {
    layouts: { full: [100], '50-50': [50, 50], '33-33-34': [33, 33, 34] },
    row_style: ['margin_top', 'gap', 'keep_together'],
    cell_style: ['background', 'valign'],
    max_rows: 4,
    max_elements: 7,
};

const page: PageSettings = {
    accent: '#1f3a5f',
    text: '#1f2937',
    font: 'sans',
    font_size: 'normal',
    margins: 'normal',
    decoration: 'none',
    footer_align: 'left',
    footer_border: 'line',
    label_color: 'muted',
    label_spacing: 0.5,
};

const text = (id: string) => ({
    id,
    type: 'text',
    settings: { text: id },
    style: {},
});

// Row r1 has two columns (a, b | c); row r2 has one column (d).
const start = (): TemplateDefinition => ({
    version: 2,
    page,
    rows: [
        {
            id: 'r1',
            layout: '50-50',
            style: {},
            cells: [
                { style: {}, elements: [text('a'), text('b')] },
                { style: {}, elements: [text('c')] },
            ],
        },
        {
            id: 'r2',
            layout: 'full',
            style: {},
            cells: [{ style: {}, elements: [text('d')] }],
        },
    ],
});

// The ids per row, columns separated by "|".
const ids = (builder: Builder): string[] =>
    builder.definition.value.rows.map((row) =>
        row.cells
            .map((cell) => cell.elements.map((element) => element.id).join(','))
            .join('|'),
    );

const event = {
    preventDefault: () => {},
    stopPropagation: () => {},
} as unknown as DragEvent;

describe('the template builder', () => {
    it('moves elements by dragging them within a column and into another column', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.drag.value = { kind: 'element', elementId: 'a' };
        builder.dropTarget.value = {
            kind: 'cell',
            rowId: 'r1',
            cell: 0,
            index: 2,
        };
        builder.drop(event);
        expect(ids(builder)).toEqual(['b,a|c', 'd']);

        builder.drag.value = { kind: 'element', elementId: 'a' };
        builder.dropTarget.value = {
            kind: 'cell',
            rowId: 'r1',
            cell: 1,
            index: 0,
        };
        builder.drop(event);
        expect(ids(builder)).toEqual(['b|a,c', 'd']);
        expect(builder.drag.value).toBeNull();
        expect(builder.dropTarget.value).toBeNull();
    });

    it('drops an element between rows into a new row of its own', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.drag.value = { kind: 'element', elementId: 'c' };
        builder.dropTarget.value = { kind: 'gap', index: 2 };
        builder.drop(event);

        expect(ids(builder)).toEqual(['a,b|', 'd', 'c']);
        expect(builder.definition.value.rows[2].layout).toBe('full');
        expect(builder.selection.value).toEqual({
            kind: 'element',
            elementId: 'c',
        });
    });

    it('adds new elements where they are dropped or after the selection', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.drag.value = { kind: 'new-element', type: 'items' };
        builder.dropTarget.value = {
            kind: 'cell',
            rowId: 'r1',
            cell: 1,
            index: 1,
        };
        builder.drop(event);

        const added = builder.definition.value.rows[0].cells[1].elements[1];
        expect(added.type).toBe('items');
        expect(added.id).toMatch(/^e[a-z0-9]+$/);
        expect(builder.selection.value).toEqual({
            kind: 'element',
            elementId: added.id,
        });

        builder.select({ kind: 'element', elementId: 'a' });
        builder.addElement('text');
        expect(
            builder.definition.value.rows[0].cells[0].elements.map(
                (element) => element.type,
            ),
        ).toEqual(['text', 'text', 'text']);
        expect(
            builder.definition.value.rows[0].cells[0].elements[1].settings,
        ).toEqual({ text: 'Hello' });
    });

    it('moves, adds and changes rows', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.drag.value = { kind: 'row', rowId: 'r2' };
        builder.dropTarget.value = { kind: 'gap', index: 0 };
        builder.drop(event);
        expect(ids(builder)).toEqual(['d', 'a,b|c']);

        builder.drag.value = { kind: 'new-row', layout: '33-33-34' };
        builder.dropTarget.value = { kind: 'gap', index: 1 };
        builder.drop(event);
        expect(ids(builder)).toEqual(['d', '||', 'a,b|c']);

        // Fewer columns: the elements of the column that goes move into the last remaining one.
        builder.setRowLayout('r1', 'full');
        expect(ids(builder)).toEqual(['d', '||', 'a,b,c']);

        // A row cannot be dropped into a column.
        builder.drag.value = { kind: 'row', rowId: 'r1' };
        builder.dropTarget.value = {
            kind: 'cell',
            rowId: 'r2',
            cell: 0,
            index: 0,
        };
        builder.drop(event);
        expect(ids(builder)).toEqual(['d', '||', 'a,b,c']);
    });

    it('moves an element past the edge of its column into the next row', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.moveElementBy('b', 1);
        expect(ids(builder)).toEqual(['a|c', 'b,d']);

        builder.moveElementBy('b', -1);
        expect(ids(builder)).toEqual(['a,b|c', 'd']);
    });

    it('undoes and redoes changes', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.removeElement('a');
        builder.updateSetting('b', 'text', 'Changed');
        expect(ids(builder)).toEqual(['b|c', 'd']);

        builder.undo();
        expect(ids(builder)).toEqual(['a,b|c', 'd']);
        expect(
            builder.definition.value.rows[0].cells[0].elements[1].settings.text,
        ).toBe('b');
        expect(builder.canRedo.value).toBe(true);

        builder.redo();
        expect(ids(builder)).toEqual(['b|c', 'd']);
        expect(
            builder.definition.value.rows[0].cells[0].elements[0].settings.text,
        ).toBe('Changed');
    });

    it('duplicates with new ids and keeps to the limits', () => {
        const builder = useTemplateBuilder(start(), registry, structure);

        builder.duplicateElement('a');
        const copy = builder.definition.value.rows[0].cells[0].elements[1];
        expect(copy.id).not.toBe('a');
        expect(copy.settings).toEqual({ text: 'a' });

        // Copying row r1 (4 elements) would go past the limit of 7 elements, so nothing is copied.
        builder.duplicateRow('r1');
        expect(builder.elementCount.value).toBe(5);
        expect(builder.definition.value.rows.length).toBe(2);

        builder.addElement('text');
        builder.addElement('text');
        builder.addElement('text');
        expect(builder.elementCount.value).toBe(7);
        expect(builder.canAddElement.value).toBe(false);

        builder.addRow('full');
        builder.addRow('full');
        builder.addRow('full');
        expect(builder.definition.value.rows.length).toBe(4);
    });
});
