// The state of the template builder: the template being made, what is selected,
// undo and redo, and dragging and dropping of elements and rows (native HTML
// drag and drop; every action also has a button, for touch screens and keyboards).
import { computed, ref, watch } from 'vue';
import type {
    DragPayload,
    DropTarget,
    PageSettings,
    Registry,
    Selection,
    StyleValues,
    Structure,
    TemplateCell,
    TemplateDefinition,
    TemplateElement,
    TemplateRow,
} from './types';

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

let counter = 0;

// Ids only need to be unique within a template (the server keeps them when valid).
const newId = (prefix: 'e' | 'r'): string =>
    `${prefix}${Date.now().toString(36).slice(-6)}${(counter++).toString(36)}`;

export function useTemplateBuilder(
    initial: TemplateDefinition,
    registry: Registry,
    structure: Structure,
) {
    const definition = ref<TemplateDefinition>(clone(initial));
    const selection = ref<Selection>({ kind: 'page' });
    const drag = ref<DragPayload | null>(null);
    const dropTarget = ref<DropTarget | null>(null);

    // ----- Undo and redo: snapshots of the template, taken shortly after each change.
    const past = ref<string[]>([]);
    const future = ref<string[]>([]);
    let snapshot = JSON.stringify(definition.value);
    let timer: ReturnType<typeof setTimeout> | undefined;

    const record = (): void => {
        clearTimeout(timer);
        timer = undefined;
        const current = JSON.stringify(definition.value);

        if (current === snapshot) {
            return;
        }

        past.value = [...past.value.slice(-79), snapshot];
        future.value = [];
        snapshot = current;
    };

    watch(
        definition,
        () => {
            clearTimeout(timer);
            timer = setTimeout(record, 400);
        },
        { deep: true },
    );

    const restore = (json: string): void => {
        snapshot = json;
        definition.value = JSON.parse(json) as TemplateDefinition;
        keepSelectionValid();
    };

    const undo = (): void => {
        record();
        const previous = past.value.at(-1);

        if (previous === undefined) {
            return;
        }

        past.value = past.value.slice(0, -1);
        future.value = [...future.value, JSON.stringify(definition.value)];
        restore(previous);
    };

    const redo = (): void => {
        record();
        const next = future.value.at(-1);

        if (next === undefined) {
            return;
        }

        future.value = future.value.slice(0, -1);
        past.value = [...past.value, JSON.stringify(definition.value)];
        restore(next);
    };

    const canUndo = computed(() => past.value.length > 0);
    const canRedo = computed(() => future.value.length > 0);

    // ----- Finding things.
    const rowById = (rowId: string): TemplateRow | null =>
        definition.value.rows.find((row) => row.id === rowId) ?? null;

    const locate = (elementId: string) => {
        for (const [rowIndex, row] of definition.value.rows.entries()) {
            for (const [cell, item] of row.cells.entries()) {
                const index = item.elements.findIndex(
                    (element) => element.id === elementId,
                );

                if (index !== -1) {
                    return {
                        row,
                        rowIndex,
                        cell,
                        index,
                        element: item.elements[index],
                    };
                }
            }
        }

        return null;
    };

    const selectedElement = computed(() =>
        selection.value.kind === 'element'
            ? (locate(selection.value.elementId)?.element ?? null)
            : null,
    );

    const selectedRow = computed(() => {
        const current = selection.value;

        if (current.kind === 'row') {
            return rowById(current.rowId);
        }

        return current.kind === 'element'
            ? (locate(current.elementId)?.row ?? null)
            : null;
    });

    const elementCount = computed(() =>
        definition.value.rows.reduce(
            (total, row) =>
                total +
                row.cells.reduce((sum, cell) => sum + cell.elements.length, 0),
            0,
        ),
    );

    const select = (next: Selection): void => {
        selection.value = next;
    };

    const keepSelectionValid = (): void => {
        const current = selection.value;

        if (
            (current.kind === 'element' && !locate(current.elementId)) ||
            (current.kind === 'row' && !rowById(current.rowId))
        ) {
            selection.value = { kind: 'page' };
        }
    };

    // ----- New elements, cells and rows with the defaults of the registry.
    const defaults = (keys: string[]): StyleValues =>
        Object.fromEntries(
            keys.map((key) => [
                key,
                registry.style[key]?.default as string | number | boolean,
            ]),
        );

    const blankElement = (type: string): TemplateElement => ({
        id: newId('e'),
        type,
        settings: clone(registry.types[type].defaults.settings),
        style: clone(registry.types[type].defaults.style),
    });

    const blankCell = (): TemplateCell => ({
        style: defaults(structure.cell_style),
        elements: [],
    });

    const blankRow = (
        layout: string,
        elements: TemplateElement[] = [],
    ): TemplateRow => {
        const cells = (structure.layouts[layout] ?? [100]).map(() =>
            blankCell(),
        );
        cells[0].elements = elements;

        return {
            id: newId('r'),
            layout,
            style: {
                ...defaults(structure.row_style),
                margin_top: definition.value.rows.length > 0 ? 10 : 0,
            },
            cells,
        };
    };

    const canAddElement = computed(
        () => elementCount.value < structure.max_elements,
    );
    const canAddRow = computed(
        () => definition.value.rows.length < structure.max_rows,
    );

    // Where a clicked element goes: after the selected element, at the end of the selected cell, or in a new row.
    const defaultTarget = (): DropTarget | null => {
        const current = selection.value;

        if (current.kind === 'element') {
            const place = locate(current.elementId);

            if (place) {
                return {
                    kind: 'cell',
                    rowId: place.row.id,
                    cell: place.cell,
                    index: place.index + 1,
                };
            }
        }

        if (current.kind === 'row') {
            const row = rowById(current.rowId);

            if (row) {
                const cell = Math.min(current.cell, row.cells.length - 1);

                return {
                    kind: 'cell',
                    rowId: row.id,
                    cell,
                    index: row.cells[cell].elements.length,
                };
            }
        }

        return null;
    };

    const insert = (
        element: TemplateElement,
        target: DropTarget | null,
    ): void => {
        if (target?.kind === 'cell') {
            const row = rowById(target.rowId);

            if (row && row.cells[target.cell]) {
                row.cells[target.cell].elements.splice(
                    target.index,
                    0,
                    element,
                );

                return;
            }
        }

        const rows = definition.value.rows;
        rows.splice(
            target?.kind === 'gap' ? target.index : rows.length,
            0,
            blankRow('full', [element]),
        );
    };

    // ----- Elements.
    const addElement = (
        type: string,
        target: DropTarget | null = null,
    ): void => {
        if (!registry.types[type] || !canAddElement.value) {
            return;
        }

        if (target?.kind === 'gap' && !canAddRow.value) {
            return;
        }

        const element = blankElement(type);
        insert(element, target ?? defaultTarget());
        selection.value = { kind: 'element', elementId: element.id };
    };

    const moveElement = (elementId: string, target: DropTarget): void => {
        const place = locate(elementId);

        if (!place) {
            return;
        }

        const elements = place.row.cells[place.cell].elements;

        if (
            target.kind === 'cell' &&
            target.rowId === place.row.id &&
            target.cell === place.cell
        ) {
            const index =
                target.index > place.index ? target.index - 1 : target.index;

            if (index !== place.index) {
                elements.splice(place.index, 1);
                elements.splice(index, 0, place.element);
            }

            return;
        }

        if (target.kind === 'gap' && !canAddRow.value) {
            return;
        }

        elements.splice(place.index, 1);
        insert(place.element, target);
        selection.value = { kind: 'element', elementId };
    };

    const moveElementBy = (elementId: string, delta: -1 | 1): void => {
        const place = locate(elementId);

        if (!place) {
            return;
        }

        const elements = place.row.cells[place.cell].elements;
        const index = place.index + delta;

        if (index >= 0 && index < elements.length) {
            elements.splice(place.index, 1);
            elements.splice(index, 0, place.element);

            return;
        }

        // Past the edge of its cell: into the row above or below (same column, or its last column).
        const neighbour = definition.value.rows[place.rowIndex + delta];

        if (!neighbour) {
            return;
        }

        elements.splice(place.index, 1);
        const cell =
            neighbour.cells[Math.min(place.cell, neighbour.cells.length - 1)];

        if (delta < 0) {
            cell.elements.push(place.element);
        } else {
            cell.elements.unshift(place.element);
        }
    };

    const removeElement = (elementId: string): void => {
        const place = locate(elementId);

        if (!place) {
            return;
        }

        place.row.cells[place.cell].elements.splice(place.index, 1);
        selection.value = {
            kind: 'row',
            rowId: place.row.id,
            cell: place.cell,
        };
    };

    const duplicateElement = (elementId: string): void => {
        const place = locate(elementId);

        if (!place || !canAddElement.value) {
            return;
        }

        const copy = { ...clone(place.element), id: newId('e') };
        place.row.cells[place.cell].elements.splice(place.index + 1, 0, copy);
        selection.value = { kind: 'element', elementId: copy.id };
    };

    const updateSetting = (
        elementId: string,
        key: string,
        value: unknown,
    ): void => {
        const place = locate(elementId);

        if (place) {
            place.element.settings[key] = value;
        }
    };

    const updateStyle = (
        elementId: string,
        key: string,
        value: string | number | boolean,
    ): void => {
        const place = locate(elementId);

        if (place) {
            place.element.style[key] = value;
        }
    };

    // ----- Rows and cells.
    const addRow = (
        layout: string,
        index: number = definition.value.rows.length,
    ): void => {
        if (!structure.layouts[layout] || !canAddRow.value) {
            return;
        }

        const row = blankRow(layout);
        definition.value.rows.splice(index, 0, row);
        selection.value = { kind: 'row', rowId: row.id, cell: 0 };
    };

    const moveRow = (rowId: string, toIndex: number): void => {
        const rows = definition.value.rows;
        const from = rows.findIndex((row) => row.id === rowId);

        if (from === -1) {
            return;
        }

        const index = Math.max(
            0,
            Math.min(toIndex > from ? toIndex - 1 : toIndex, rows.length - 1),
        );

        if (index !== from) {
            const [row] = rows.splice(from, 1);
            rows.splice(index, 0, row);
        }
    };

    const removeRow = (rowId: string): void => {
        const rows = definition.value.rows;
        const index = rows.findIndex((row) => row.id === rowId);

        if (index !== -1) {
            rows.splice(index, 1);
            selection.value = { kind: 'page' };
        }
    };

    const duplicateRow = (rowId: string): void => {
        const rows = definition.value.rows;
        const index = rows.findIndex((row) => row.id === rowId);

        if (index === -1 || !canAddRow.value) {
            return;
        }

        const copy = clone(rows[index]);
        const count = copy.cells.reduce(
            (sum, cell) => sum + cell.elements.length,
            0,
        );

        if (elementCount.value + count > structure.max_elements) {
            return;
        }

        copy.id = newId('r');
        copy.cells.forEach((cell) =>
            cell.elements.forEach((element) => (element.id = newId('e'))),
        );
        rows.splice(index + 1, 0, copy);
        selection.value = { kind: 'row', rowId: copy.id, cell: 0 };
    };

    const setRowLayout = (rowId: string, layout: string): void => {
        const row = rowById(rowId);
        const widths = structure.layouts[layout];

        if (!row || !widths) {
            return;
        }

        // Fewer columns: the elements of the columns that go move into the new last column.
        const cells = row.cells.slice(0, widths.length);

        for (const extra of row.cells.slice(widths.length)) {
            cells[widths.length - 1].elements.push(...extra.elements);
        }

        while (cells.length < widths.length) {
            cells.push(blankCell());
        }

        row.cells = cells;
        row.layout = layout;

        if (selection.value.kind === 'row' && selection.value.rowId === rowId) {
            selection.value = {
                kind: 'row',
                rowId,
                cell: Math.min(selection.value.cell, widths.length - 1),
            };
        }
    };

    const updateRowStyle = (
        rowId: string,
        key: string,
        value: string | number | boolean,
    ): void => {
        const row = rowById(rowId);

        if (row) {
            row.style[key] = value;
        }
    };

    const updateCellStyle = (
        rowId: string,
        cell: number,
        key: string,
        value: string | number | boolean,
    ): void => {
        const row = rowById(rowId);

        if (row?.cells[cell]) {
            row.cells[cell].style[key] = value;
        }
    };

    const updatePage = <K extends keyof PageSettings>(
        key: K,
        value: PageSettings[K],
    ): void => {
        definition.value.page[key] = value;
    };

    const replace = (next: TemplateDefinition): void => {
        definition.value = clone(next);
        selection.value = { kind: 'page' };
    };

    // ----- Dragging and dropping.
    const startDrag = (payload: DragPayload, event: DragEvent): void => {
        drag.value = payload;

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed =
                payload.kind === 'new-element' || payload.kind === 'new-row'
                    ? 'copy'
                    : 'move';
            // Firefox only starts dragging when there is data.
            event.dataTransfer.setData('text/plain', payload.kind);
        }
    };

    const endDrag = (): void => {
        drag.value = null;
        dropTarget.value = null;
    };

    const draggingElement = (): boolean =>
        drag.value?.kind === 'new-element' || drag.value?.kind === 'element';

    // Over a cell: the element goes before the first element whose middle is below the pointer.
    const onCellDragOver = (
        event: DragEvent,
        rowId: string,
        cell: number,
    ): void => {
        if (!draggingElement()) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const container = event.currentTarget as HTMLElement;
        const children = Array.from(container.children).filter(
            (child): child is HTMLElement =>
                child instanceof HTMLElement &&
                child.dataset.elementId !== undefined,
        );
        let index = children.length;

        for (const [position, child] of children.entries()) {
            const rect = child.getBoundingClientRect();

            if (event.clientY < rect.top + rect.height / 2) {
                index = position;
                break;
            }
        }

        dropTarget.value = { kind: 'cell', rowId, cell, index };
    };

    // Between rows: rows are moved there; elements get a new row of their own.
    const onGapDragOver = (event: DragEvent, index: number): void => {
        if (!drag.value) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        dropTarget.value = { kind: 'gap', index };
    };

    const drop = (event: DragEvent): void => {
        event.preventDefault();
        event.stopPropagation();

        const payload = drag.value;
        const target = dropTarget.value;
        endDrag();

        if (!payload || !target) {
            return;
        }

        switch (payload.kind) {
            case 'new-element':
                addElement(payload.type, target);
                break;
            case 'element':
                moveElement(payload.elementId, target);
                break;
            case 'row':
                if (target.kind === 'gap') {
                    moveRow(payload.rowId, target.index);
                }
                break;
            case 'new-row':
                if (target.kind === 'gap') {
                    addRow(payload.layout, target.index);
                }
                break;
        }
    };

    return {
        definition,
        selection,
        drag,
        dropTarget,
        registry,
        structure,
        canUndo,
        canRedo,
        canAddElement,
        canAddRow,
        selectedElement,
        selectedRow,
        elementCount,
        locate,
        select,
        undo,
        redo,
        addElement,
        moveElement,
        moveElementBy,
        removeElement,
        duplicateElement,
        updateSetting,
        updateStyle,
        addRow,
        moveRow,
        removeRow,
        duplicateRow,
        setRowLayout,
        updateRowStyle,
        updateCellStyle,
        updatePage,
        replace,
        startDrag,
        endDrag,
        onCellDragOver,
        onGapDragOver,
        drop,
    };
}

export type Builder = ReturnType<typeof useTemplateBuilder>;
