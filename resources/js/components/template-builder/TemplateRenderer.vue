<script setup lang="ts">
import { ArrowDown, ArrowUp, Copy, GripVertical, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import type { CSSProperties } from 'vue';
import ElementPreview from './ElementPreview.vue';
import { Palette, box, fontFamily, pageMargins, px, scale } from './styles';
import type { Box } from './styles';
import type { Sample, TemplateDefinition, TemplateElement } from './types';
import type { Builder } from './useTemplateBuilder';
import { hasContent } from './visibility';

/**
 * A template drawn as a page with the example document. Without a builder it is
 * a picture (gallery); with a builder it is the canvas of the template builder:
 * elements can be selected, dragged and dropped.
 */
const props = defineProps<{
    definition: TemplateDefinition;
    sample: Sample;
    images?: Record<string, string>;
    gates?: Record<string, boolean>;
    builder?: Builder;
}>();

type DrawnElement = {
    element: TemplateElement;
    outer: Box;
    inner: Box | null;
    allowed: boolean;
};

const editing = computed(() => props.builder !== undefined);
const page = computed(() => props.definition.page);
const palette = computed(() => new Palette(page.value.accent, page.value.text));
const margins = computed(() => pageMargins(page.value));
const imageMap = computed(() => props.images ?? {});
const dragging = computed(() =>
    props.builder ? props.builder.drag.value !== null : false,
);

// The widths of the columns are in the name of the layout ("60-40", "full").
const widthsOf = (layout: string): number[] =>
    layout === 'full' ? [100] : layout.split('-').map(Number);

// Whether the invoice settings allow an element (switched-off elements are greyed out in the builder, hidden otherwise).
const allowed = (element: TemplateElement): boolean =>
    (props.gates ?? {})[element.type] !== false;

const drawElement = (
    element: TemplateElement,
    parent: string | null,
): DrawnElement => {
    const fit = element.type === 'title' && Boolean(element.settings.fit);
    const outerStyle = fit
        ? { align: element.style.align, margin_top: element.style.margin_top }
        : element.style;
    const innerStyle = Object.fromEntries(
        Object.entries(element.style).filter(
            ([key]) => key !== 'align' && key !== 'margin_top',
        ),
    );

    return {
        element,
        outer: box(outerStyle, palette.value, parent),
        inner: fit ? box(innerStyle, palette.value, parent) : null,
        allowed: allowed(element),
    };
};

// Rows, cells and elements with their styles, worked out once per change.
const rows = computed(() =>
    props.definition.rows.map((row) => {
        const rowBox = box(row.style, palette.value, null);
        const widths = widthsOf(row.layout);
        const gap = Number(row.style.gap ?? 0) / 2;
        const last = row.cells.length - 1;

        const cells = row.cells.map((cell, index) => {
            const cellBox = box(cell.style, palette.value, rowBox.background);
            const elements = (
                editing.value
                    ? cell.elements
                    : cell.elements.filter(
                          (element) =>
                              allowed(element) &&
                              hasContent(element, props.sample, imageMap.value),
                      )
            ).map((element) => drawElement(element, cellBox.background));

            return {
                index,
                box: cellBox,
                elements,
                td: {
                    width: `${widths[index] ?? 100}%`,
                    verticalAlign: String(cell.style.valign ?? 'top'),
                    paddingLeft: index > 0 ? px(gap) : 0,
                    paddingRight: index < last ? px(gap) : 0,
                } as CSSProperties,
            };
        });

        return {
            row,
            box: rowBox,
            cells,
            visible:
                editing.value || cells.some((cell) => cell.elements.length > 0),
        };
    }),
);

const pageStyle = computed<CSSProperties>(() => {
    const [top, right, bottom, left] = margins.value;

    return {
        width: '794px',
        minHeight: '1123px',
        padding: `${top}px ${right}px ${bottom + 20}px ${left}px`,
        fontFamily: fontFamily(page.value.font),
        fontSize: px(scale(page.value, 9.5)),
        lineHeight: 1.38,
        color: page.value.text,
        backgroundColor: '#ffffff',
        position: 'relative',
        boxSizing: 'border-box',
    };
});

const decoration = computed<CSSProperties | null>(() => {
    const background = page.value.accent;

    switch (page.value.decoration) {
        case 'top_band':
            return {
                top: 0,
                left: 0,
                right: 0,
                height: '9px',
                backgroundColor: background,
            };
        case 'top_line':
            return {
                top: 0,
                left: 0,
                right: 0,
                height: '4px',
                backgroundColor: background,
            };
        case 'bottom_band':
            return {
                bottom: 0,
                left: 0,
                right: 0,
                height: '9px',
                backgroundColor: background,
            };
        case 'left_stripe':
            return {
                top: 0,
                bottom: 0,
                left: 0,
                width: '8px',
                backgroundColor: background,
            };
        default:
            return null;
    }
});

const footerStyle = computed<CSSProperties>(() => {
    const [, right, , left] = margins.value;
    const style: CSSProperties = {
        position: 'absolute',
        left: px(left),
        right: px(right),
        bottom: '14px',
        fontSize: px(scale(page.value, 7)),
        color: '#9ca3af',
        textAlign: page.value.footer_align as CSSProperties['textAlign'],
        paddingTop: '3px',
    };

    if (page.value.footer_border === 'accent') {
        style.borderTop = `1.5px solid ${page.value.accent}`;
    } else if (page.value.footer_border === 'line') {
        style.borderTop = '1px solid #e5e7eb';
    }

    return style;
});

// Selection and drop targets of the builder.
const isSelected = (element: TemplateElement): boolean => {
    const selection = props.builder?.selection.value;

    return selection?.kind === 'element' && selection.elementId === element.id;
};

const isRowSelected = (rowId: string): boolean => {
    const selection = props.builder?.selection.value;

    return selection?.kind === 'row' && selection.rowId === rowId;
};

const dropIndex = (rowId: string, cell: number): number | null => {
    const target = props.builder?.dropTarget.value;

    return target?.kind === 'cell' &&
        target.rowId === rowId &&
        target.cell === cell
        ? target.index
        : null;
};

const gapActive = (index: number): boolean => {
    const target = props.builder?.dropTarget.value;

    return target?.kind === 'gap' && target.index === index;
};
</script>

<template>
    <div
        class="tb-page"
        :class="{ 'tb-editing': editing }"
        :style="pageStyle"
        @click.self="builder?.select({ kind: 'page' })"
    >
        <div
            v-if="decoration"
            :style="{ position: 'absolute', ...decoration }"
        />

        <template v-for="(drawn, rowIndex) in rows" :key="drawn.row.id">
            <!-- Drop zone above the row (for rows, new rows and elements) -->
            <div
                v-if="editing"
                class="tb-gap"
                :class="{
                    'tb-gap-open': dragging,
                    'tb-gap-active': gapActive(rowIndex),
                }"
                @dragover="builder?.onGapDragOver($event, rowIndex)"
                @drop="builder?.drop($event)"
            />

            <div
                v-if="drawn.visible"
                class="tb-row"
                :class="{ 'tb-row-selected': isRowSelected(drawn.row.id) }"
            >
                <!-- Row tools in the left margin -->
                <div v-if="builder" class="tb-row-tools">
                    <button
                        type="button"
                        class="tb-tool tb-handle"
                        title="Drag the row, or click to select it"
                        draggable="true"
                        @dragstart="
                            builder.startDrag(
                                { kind: 'row', rowId: drawn.row.id },
                                $event,
                            )
                        "
                        @dragend="builder.endDrag()"
                        @click.stop="
                            builder.select({
                                kind: 'row',
                                rowId: drawn.row.id,
                                cell: 0,
                            })
                        "
                    >
                        <GripVertical class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="tb-tool"
                        title="Move the row up"
                        :disabled="rowIndex === 0"
                        @click.stop="
                            builder.moveRow(drawn.row.id, rowIndex - 1)
                        "
                    >
                        <ArrowUp class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="tb-tool"
                        title="Move the row down"
                        :disabled="rowIndex === rows.length - 1"
                        @click.stop="
                            builder.moveRow(drawn.row.id, rowIndex + 2)
                        "
                    >
                        <ArrowDown class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="tb-tool"
                        title="Duplicate the row"
                        @click.stop="builder.duplicateRow(drawn.row.id)"
                    >
                        <Copy class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="tb-tool tb-danger"
                        title="Delete the row"
                        @click.stop="builder.removeRow(drawn.row.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>

                <table class="tb-table" :style="drawn.box.css">
                    <tbody>
                        <tr>
                            <td
                                v-for="cell in drawn.cells"
                                :key="cell.index"
                                :style="cell.td"
                                @click.self="
                                    builder?.select({
                                        kind: 'row',
                                        rowId: drawn.row.id,
                                        cell: cell.index,
                                    })
                                "
                            >
                                <div
                                    class="tb-cell"
                                    :class="{
                                        'tb-cell-empty':
                                            editing &&
                                            cell.elements.length === 0,
                                        'tb-cell-target':
                                            dropIndex(
                                                drawn.row.id,
                                                cell.index,
                                            ) !== null,
                                    }"
                                    :style="cell.box.css"
                                    @click.self="
                                        builder?.select({
                                            kind: 'row',
                                            rowId: drawn.row.id,
                                            cell: cell.index,
                                        })
                                    "
                                    @dragover="
                                        builder?.onCellDragOver(
                                            $event,
                                            drawn.row.id,
                                            cell.index,
                                        )
                                    "
                                    @drop="builder?.drop($event)"
                                >
                                    <template
                                        v-for="(item, index) in cell.elements"
                                        :key="item.element.id"
                                    >
                                        <div
                                            v-if="
                                                dropIndex(
                                                    drawn.row.id,
                                                    cell.index,
                                                ) === index
                                            "
                                            class="tb-drop-line"
                                        />
                                        <div
                                            :data-element-id="item.element.id"
                                            class="tb-element"
                                            :class="{
                                                'tb-element-selected':
                                                    isSelected(item.element),
                                                'tb-element-off':
                                                    editing && !item.allowed,
                                            }"
                                            :style="item.outer.css"
                                            :draggable="editing"
                                            @dragstart.stop="
                                                builder?.startDrag(
                                                    {
                                                        kind: 'element',
                                                        elementId:
                                                            item.element.id,
                                                    },
                                                    $event,
                                                )
                                            "
                                            @dragend="builder?.endDrag()"
                                            @click.stop="
                                                builder?.select({
                                                    kind: 'element',
                                                    elementId: item.element.id,
                                                })
                                            "
                                        >
                                            <ElementPreview
                                                :element="item.element"
                                                :sample="sample"
                                                :page="page"
                                                :palette="palette"
                                                :bg="
                                                    item.inner?.background ??
                                                    item.outer.background
                                                "
                                                :ink="
                                                    item.inner?.ink ??
                                                    item.outer.ink
                                                "
                                                :inner="item.inner"
                                                :images="imageMap"
                                                :editing="editing"
                                            />
                                            <span
                                                v-if="editing && !item.allowed"
                                                class="tb-off-badge"
                                                >Off in invoice settings</span
                                            >
                                            <div
                                                v-if="
                                                    builder &&
                                                    isSelected(item.element)
                                                "
                                                class="tb-element-tools"
                                                @click.stop
                                            >
                                                <span class="tb-element-name">{{
                                                    builder.registry.types[
                                                        item.element.type
                                                    ]?.label
                                                }}</span>
                                                <button
                                                    type="button"
                                                    class="tb-tool"
                                                    title="Move up"
                                                    @click="
                                                        builder.moveElementBy(
                                                            item.element.id,
                                                            -1,
                                                        )
                                                    "
                                                >
                                                    <ArrowUp class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="tb-tool"
                                                    title="Move down"
                                                    @click="
                                                        builder.moveElementBy(
                                                            item.element.id,
                                                            1,
                                                        )
                                                    "
                                                >
                                                    <ArrowDown
                                                        class="size-3.5"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="tb-tool"
                                                    title="Duplicate"
                                                    @click="
                                                        builder.duplicateElement(
                                                            item.element.id,
                                                        )
                                                    "
                                                >
                                                    <Copy class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="tb-tool tb-danger"
                                                    title="Delete"
                                                    @click="
                                                        builder.removeElement(
                                                            item.element.id,
                                                        )
                                                    "
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <div
                                        v-if="
                                            dropIndex(
                                                drawn.row.id,
                                                cell.index,
                                            ) === cell.elements.length
                                        "
                                        class="tb-drop-line"
                                    />
                                    <div
                                        v-if="
                                            editing &&
                                            cell.elements.length === 0
                                        "
                                        class="tb-cell-hint"
                                        @click="
                                            builder?.select({
                                                kind: 'row',
                                                rowId: drawn.row.id,
                                                cell: cell.index,
                                            })
                                        "
                                    >
                                        Drop elements here
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Drop zone below the last row -->
        <div
            v-if="editing"
            class="tb-gap tb-gap-last"
            :class="{
                'tb-gap-open': dragging,
                'tb-gap-active': gapActive(rows.length),
            }"
            @dragover="builder?.onGapDragOver($event, rows.length)"
            @drop="builder?.drop($event)"
        >
            <span v-if="rows.length === 0" class="tb-cell-hint"
                >Drag a row or an element here to start</span
            >
        </div>

        <div
            v-if="sample.footer && (gates?.footer ?? true)"
            :style="footerStyle"
        >
            {{ sample.footer }}
        </div>
    </div>
</template>

<style scoped>
.tb-page :deep(.tb-table) {
    width: 100%;
    border-collapse: collapse;
}

.tb-page :deep(td),
.tb-page :deep(th) {
    vertical-align: top;
    text-align: left;
}

.tb-page :deep(.tb-placeholder) {
    display: flex;
    min-height: 26px;
    align-items: center;
    justify-content: center;
    border: 1px dashed #cbd5e1;
    border-radius: 4px;
    padding: 4px 8px;
    color: #94a3b8;
    font-size: 10px;
    font-style: italic;
    font-weight: normal;
    text-align: center;
    text-transform: none;
    letter-spacing: normal;
}

.tb-row {
    position: relative;
}

.tb-editing .tb-row:hover {
    outline: 1px dashed #cbd5e1;
    outline-offset: 2px;
}

.tb-row-selected,
.tb-editing .tb-row-selected:hover {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

.tb-row-tools {
    position: absolute;
    top: 0;
    left: -30px;
    display: none;
    flex-direction: column;
    gap: 2px;
}

.tb-row:hover .tb-row-tools,
.tb-row-selected .tb-row-tools {
    display: flex;
}

.tb-tool {
    display: inline-flex;
    width: 22px;
    height: 22px;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    background: #ffffff;
    color: #334155;
    box-shadow: 0 0 0 1px #cbd5e1;
    cursor: pointer;
}

.tb-tool:hover {
    background: #eef2ff;
}

.tb-tool:disabled {
    cursor: default;
    opacity: 0.35;
}

.tb-handle {
    cursor: grab;
}

.tb-danger:hover {
    background: #fef2f2;
    color: #b91c1c;
}

.tb-cell {
    min-height: 18px;
}

.tb-cell-empty {
    min-height: 44px;
    border-radius: 4px;
    outline: 1px dashed #cbd5e1;
}

.tb-cell-target {
    outline: 1px dashed #6366f1;
    outline-offset: 2px;
}

.tb-cell-hint {
    display: flex;
    min-height: 40px;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 10px;
    font-style: normal;
    letter-spacing: normal;
    text-transform: none;
}

.tb-element {
    position: relative;
}

.tb-editing .tb-element {
    cursor: pointer;
}

.tb-editing .tb-element:hover {
    outline: 1px solid #a5b4fc;
    outline-offset: 1px;
}

.tb-element-selected,
.tb-editing .tb-element-selected:hover {
    outline: 2px solid #6366f1;
    outline-offset: 1px;
}

.tb-element-off > :not(.tb-off-badge):not(.tb-element-tools) {
    opacity: 0.4;
}

.tb-off-badge {
    position: absolute;
    top: -9px;
    left: 4px;
    border-radius: 999px;
    background: #fef3c7;
    color: #92400e;
    padding: 0 6px;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 9px;
    font-style: normal;
    font-weight: 600;
    line-height: 16px;
    letter-spacing: normal;
    text-transform: none;
    white-space: nowrap;
}

.tb-element-tools {
    position: absolute;
    top: -26px;
    right: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 2px;
    border-radius: 6px;
    background: #4f46e5;
    padding: 2px;
}

.tb-element-name {
    padding: 0 6px;
    color: #ffffff;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 10px;
    font-style: normal;
    font-weight: 600;
    letter-spacing: normal;
    text-transform: none;
    white-space: nowrap;
}

.tb-drop-line {
    height: 3px;
    margin: -1px 0;
    border-radius: 2px;
    background: #6366f1;
}

.tb-gap {
    height: 6px;
    border-radius: 4px;
    transition: height 0.1s;
}

.tb-gap-last {
    height: 36px;
}

.tb-gap-open {
    height: 18px;
    outline: 1px dashed #c7d2fe;
}

.tb-gap-active {
    height: 26px;
    background: #eef2ff;
    outline: 2px dashed #6366f1;
}
</style>
