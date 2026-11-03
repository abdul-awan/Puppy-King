<script setup lang="ts">
import { computed } from 'vue';
import type { CSSProperties } from 'vue';
import type { Box, Palette } from './styles';
import { lineCss, px, scale } from './styles';
import type { PageSettings, Sample, TemplateElement } from './types';
import { signatureLabels } from './visibility';

/**
 * One element drawn with the example document, like in the PDF
 * (resources/views/pdf/builder/elements/*). In the builder, elements without
 * anything to show on the example get a placeholder.
 */
const props = defineProps<{
    element: TemplateElement;
    sample: Sample;
    page: PageSettings;
    palette: Palette;
    bg: string | null;
    ink: string;
    inner: Box | null;
    images: Record<string, string>;
    editing: boolean;
}>();

const s = computed(() => props.element.settings);
const num = (key: string): number => Number(s.value[key] ?? 0);
const str = (key: string): string => String(s.value[key] ?? '');
const bool = (key: string): boolean => Boolean(s.value[key]);

const label = computed<CSSProperties>(() => ({
    fontSize: px(scale(props.page, 7.5)),
    textTransform: 'uppercase',
    letterSpacing: px(props.page.label_spacing),
    color: props.palette.text(props.page.label_color, props.bg),
}));
const muted = computed(() => props.palette.text('muted', props.bg));
const align = computed(() => String(props.element.style.align ?? 'left'));

// Company details.
const companyParts = computed(() =>
    (
        [
            ['address', str('address'), ', '],
            ['contact', str('contact'), '  ·  '],
            ['tax', str('tax_numbers'), '  ·  '],
        ] as const
    ).map(([part, mode, glue]) => ({
        mode,
        lines: props.sample.seller[part],
        glue,
    })),
);
const nameStyle = computed<CSSProperties>(() => ({
    fontSize: px(num('name_size')),
    color: props.palette.text(str('name_color'), props.bg),
    fontWeight: bool('name_bold') ? 'bold' : 'normal',
    textTransform: bool('name_uppercase') ? 'uppercase' : undefined,
    letterSpacing:
        num('name_letter_spacing') > 0
            ? px(num('name_letter_spacing'))
            : undefined,
}));

// Bars above the customer and the extra information.
const bar = computed<CSSProperties>(() => {
    const background =
        props.palette.background(str('bar_background')) ??
        props.palette.background('soft') ??
        '#f3f4f6';

    return {
        backgroundColor: background,
        color: props.palette.text('accent', background),
        fontSize: px(scale(props.page, 7.5)),
        fontWeight: 'bold',
        textTransform: 'uppercase',
        letterSpacing: px(props.page.label_spacing),
        padding: '4px 8px',
    };
});

// Items table.
const columns = computed(() => props.sample.view.columns);
const compact = computed(
    () =>
        str('compact') === 'on' ||
        (str('compact') === 'auto' && columns.value.length >= 9),
);
const table = computed(() => {
    const palette = props.palette;
    const style = str('header_style');
    const grid = str('row_style') === 'grid';
    const headerBackground =
        style === 'fill'
            ? (palette.background(str('header_background')) ?? palette.accent)
            : style === 'light'
              ? palette.background(str('header_background'))
              : null;
    const headerOn = headerBackground ?? props.bg;
    const headerInk =
        str('header_color') !== 'auto'
            ? palette.text(str('header_color'), headerOn)
            : style === 'fill'
              ? palette.text('auto', headerOn)
              : style === 'lines'
                ? palette.text('accent', headerOn)
                : style === 'plain'
                  ? palette.text('muted', headerOn)
                  : palette.text('text', headerOn);
    const headerLine = palette.line(str('header_line'));
    const rowLine = lineCss(palette, 1, str('line_style'), str('row_line'));
    const gridLine = lineCss(palette, 1, 'solid', str('row_line'));
    const padding = compact.value ? '5px 3px' : '6px 5px';

    const th: CSSProperties = {
        padding,
        fontSize: px(scale(props.page, compact.value ? 6.8 : 7.5)),
        color: headerInk,
        fontWeight: bool('header_bold') ? 'bold' : 'normal',
        textTransform: bool('header_uppercase') ? 'uppercase' : undefined,
        letterSpacing: bool('header_uppercase') ? '0.3px' : undefined,
        backgroundColor: headerBackground ?? undefined,
    };

    if (style === 'fill' && grid) {
        th.border = `1px solid ${headerBackground}`;
    } else if (style === 'light' || style === 'plain') {
        th.borderBottom = `1.5px solid ${headerLine}`;
    } else if (style === 'lines') {
        th.borderTop = `1px solid ${headerLine}`;
        th.borderBottom = `1px solid ${headerLine}`;
    }

    if (grid && style !== 'fill') {
        th.borderLeft = gridLine;
        th.borderRight = gridLine;
    }

    const td: CSSProperties = {
        padding,
        fontSize: compact.value ? px(scale(props.page, 8.5)) : undefined,
    };

    if (str('row_style') === 'lines') {
        td.borderBottom = rowLine;
    } else if (grid) {
        td.border = gridLine;
    }

    const section: CSSProperties =
        str('section_style') === 'italic'
            ? { fontStyle: 'italic', color: palette.text('accent', props.bg) }
            : str('section_style') === 'band'
              ? {
                    fontWeight: 'bold',
                    backgroundColor: palette.background('faint') ?? undefined,
                }
              : { fontWeight: 'bold' };

    return {
        th,
        td,
        section,
        stripe:
            str('row_style') === 'striped' ? palette.background('faint') : null,
    };
});
const itemNumbers = computed(() => {
    let number = 0;

    return props.sample.view.rows.map((row) =>
        row.type === 'item' ? ++number : 0,
    );
});
const hasNumberColumn = computed(() =>
    columns.value.some((column) => column.key === 'number'),
);

// Totals.
const totals = computed(() => {
    const palette = props.palette;
    const accentInk = palette.text('accent', props.bg);
    const soft = palette.background('soft') ?? '#f3f4f6';
    const boxed = str('rows_style') === 'boxed';
    const line = lineCss(palette, 1, 'solid', 'grey');
    const grand: CSSProperties = {
        fontSize: px(num('grand_size')),
        fontWeight: 'bold',
    };

    switch (str('grand_style')) {
        case 'fill':
            Object.assign(grand, {
                backgroundColor: palette.accent,
                color: palette.onAccent(),
                padding: '7px 8px',
            });
            break;
        case 'soft':
            Object.assign(grand, {
                backgroundColor: soft,
                color: palette.text('accent', soft),
                padding: '6px 8px',
            });
            break;
        case 'double':
            Object.assign(grand, {
                borderTop: `3px double ${palette.accent}`,
                borderBottom: `3px double ${palette.accent}`,
                color: accentInk,
                padding: '6px 5px',
            });
            break;
        case 'box':
            Object.assign(grand, {
                border: `1.5px solid ${palette.accent}`,
                color: accentInk,
                padding: '6px 8px',
            });
            break;
        case 'plain':
            Object.assign(grand, { color: props.ink, padding: '6px 5px' });
            break;
        default:
            Object.assign(grand, {
                borderTop: `1.5px solid ${palette.accent}`,
                color: accentInk,
                padding: '6px 5px',
            });
    }

    const row: CSSProperties = { padding: boxed ? '4px 7px' : '3px 5px' };

    if (str('rows_style') === 'lines') {
        row.borderBottom = line;
    } else if (boxed) {
        row.border = line;
    }

    const width = num('width');
    const side =
        width < 100
            ? str('position') === 'center'
                ? (100 - width) / 2
                : 100 - width
            : 0;

    return {
        grand,
        row,
        labelColor: palette.text(str('label_color'), props.bg),
        width,
        side,
    };
});

// Signature lines.
const signature = computed(() => {
    const labels = signatureLabels(props.element, props.sample);
    const count = labels.length;
    const box = str('variant') === 'box';
    const cell: CSSProperties = {
        paddingTop: px(box ? num('space') + 24 : num('space')),
        textAlign: 'left',
        width: `${count === 1 ? 45 : (100 - 8 * (count - 1)) / count}%`,
    };

    if (box) {
        Object.assign(cell, {
            border: `1px solid ${props.palette.line('soft')}`,
            paddingLeft: '8px',
            paddingRight: '8px',
            paddingBottom: '6px',
        });
    }

    return {
        labels,
        cell,
        spacer: align.value === 'center' ? 27.5 : 55,
        line: props.palette.line('dark'),
    };
});

// Text with placeholders.
const text = computed(() =>
    Object.entries(props.sample.placeholders).reduce(
        (value, [placeholder, replacement]) =>
            value.split(placeholder).join(replacement),
        str('text'),
    ),
);

const dividerPlace = computed<CSSProperties>(() =>
    align.value === 'center'
        ? { marginLeft: 'auto', marginRight: 'auto' }
        : align.value === 'right'
          ? { marginLeft: 'auto' }
          : {},
);
const caption = computed(
    () =>
        str('caption') ||
        `Scan to open this ${props.sample.title.toLowerCase()} online.`,
);
</script>

<template>
    <!-- Logo -->
    <template v-if="element.type === 'logo'">
        <img
            v-if="sample.logo"
            :src="sample.logo"
            alt=""
            :style="{
                maxHeight: px(num('height')),
                maxWidth: px(num('height') * 4),
                display: 'inline-block',
            }"
        />
        <span
            v-else-if="editing"
            class="tb-placeholder"
            :style="{ height: px(Math.min(num('height'), 60)) }"
            >Your logo (add it in the company profile)</span
        >
    </template>

    <!-- Company details -->
    <template v-else-if="element.type === 'company'">
        <div v-if="str('heading')" :style="{ ...label, marginBottom: '2px' }">
            {{ str('heading') }}
        </div>
        <div v-if="bool('show_name') && sample.seller.name" :style="nameStyle">
            {{ sample.seller.name }}
        </div>
        <template v-for="part in companyParts" :key="part.glue + part.mode">
            <template v-if="part.mode === 'stacked'">
                <div v-for="line in part.lines" :key="line">{{ line }}</div>
            </template>
            <div v-else-if="part.mode === 'inline' && part.lines.length">
                {{ part.lines.join(part.glue) }}
            </div>
        </template>
    </template>

    <!-- Title -->
    <template v-else-if="element.type === 'title'">
        <span v-if="inner" :style="{ display: 'inline-block', ...inner.css }">{{
            sample.title
        }}</span>
        <template v-else>{{ sample.title }}</template>
    </template>

    <!-- Status stamp -->
    <span
        v-else-if="element.type === 'stamp' && sample.stamp"
        :style="{
            fontSize: px(num('size')),
            fontWeight: 'bold',
            padding: '3px 9px',
            border: `2px solid ${sample.stamp.color}`,
            borderRadius: '3px',
            letterSpacing: '1px',
            color: sample.stamp.color,
            backgroundColor: '#ffffff',
            display: 'inline-block',
        }"
        >{{ sample.stamp.text }}</span
    >

    <!-- Document details -->
    <template v-else-if="element.type === 'details'">
        <div v-if="str('heading')" :style="{ ...label, marginBottom: '3px' }">
            {{ str('heading') }}
        </div>
        <div v-if="str('layout') === 'inline'">
            <template v-for="(row, index) in sample.view.meta" :key="row.label">
                <span
                    :style="{ color: palette.text(str('label_color'), bg) }"
                    >{{ row.label }}</span
                >
                {{ ' ' }}
                <span
                    :style="{
                        fontWeight: bool('value_bold') ? 'bold' : 'normal',
                    }"
                    >{{ row.value }}</span
                >
                <span
                    v-if="index < sample.view.meta.length - 1"
                    :style="{ color: palette.text(str('label_color'), bg) }"
                    >&nbsp;&nbsp;·&nbsp;&nbsp;</span
                >
            </template>
        </div>
        <template v-else-if="str('layout') === 'stacked'">
            <div
                v-for="(row, index) in sample.view.meta"
                :key="row.label"
                :style="{ marginTop: index ? '5px' : undefined }"
            >
                <div
                    :style="{
                        color: palette.text(str('label_color'), bg),
                        fontSize: px(scale(page, 8)),
                    }"
                >
                    {{ row.label }}
                </div>
                <div
                    :style="{
                        fontWeight: bool('value_bold') ? 'bold' : 'normal',
                    }"
                >
                    {{ row.value }}
                </div>
            </div>
        </template>
        <table v-else-if="str('layout') === 'columns'" class="tb-table">
            <tbody>
                <tr>
                    <td
                        v-for="(row, index) in sample.view.meta"
                        :key="row.label"
                        :style="{
                            textAlign: align as CSSProperties['textAlign'],
                            paddingRight:
                                index < sample.view.meta.length - 1
                                    ? '12px'
                                    : 0,
                        }"
                    >
                        <div
                            :style="{
                                color: palette.text(str('label_color'), bg),
                                fontSize: px(scale(page, 7.5)),
                                textTransform: 'uppercase',
                                letterSpacing: px(page.label_spacing),
                            }"
                        >
                            {{ row.label }}
                        </div>
                        <div
                            :style="{
                                fontWeight: bool('value_bold')
                                    ? 'bold'
                                    : 'normal',
                                color: ink,
                            }"
                        >
                            {{ row.value }}
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table v-else class="tb-table">
            <tbody>
                <tr v-for="row in sample.view.meta" :key="row.label">
                    <td
                        :style="{
                            width: `${num('label_width')}%`,
                            color: palette.text(str('label_color'), bg),
                            textAlign: align as CSSProperties['textAlign'],
                            padding: '1px 8px 1px 0',
                        }"
                    >
                        {{ row.label }}
                    </td>
                    <td
                        :style="{
                            fontWeight: bool('value_bold') ? 'bold' : 'normal',
                            color: ink,
                            textAlign: align as CSSProperties['textAlign'],
                            padding: '1px 0',
                        }"
                    >
                        {{ row.value }}
                    </td>
                </tr>
            </tbody>
        </table>
    </template>

    <!-- Customer -->
    <template v-else-if="element.type === 'customer'">
        <template v-if="str('heading_style') === 'bar'">
            <div v-if="bool('show_heading')" :style="bar">
                {{ str('heading') || sample.customer.heading }}
            </div>
            <div style="padding: 7px 8px">
                <div
                    :style="{
                        fontSize: px(num('name_size')),
                        fontWeight: 'bold',
                        marginTop: '2px',
                    }"
                >
                    {{ sample.customer.name }}
                </div>
                <div
                    v-if="bool('show_address') && sample.customer.address"
                    class="whitespace-pre-line"
                >
                    {{ sample.customer.address }}
                </div>
                <template v-if="bool('show_tax_numbers')">
                    <div v-for="line in sample.customer.tax" :key="line">
                        {{ line }}
                    </div>
                </template>
            </div>
        </template>
        <template v-else>
            <div v-if="bool('show_heading')" :style="label">
                {{ str('heading') || sample.customer.heading }}
            </div>
            <div
                :style="{
                    fontSize: px(num('name_size')),
                    fontWeight: 'bold',
                    marginTop: '2px',
                }"
            >
                {{ sample.customer.name }}
            </div>
            <div
                v-if="bool('show_address') && sample.customer.address"
                class="whitespace-pre-line"
            >
                {{ sample.customer.address }}
            </div>
            <template v-if="bool('show_tax_numbers')">
                <div v-for="line in sample.customer.tax" :key="line">
                    {{ line }}
                </div>
            </template>
        </template>
    </template>

    <!-- Extra information -->
    <template v-else-if="element.type === 'extra'">
        <template v-if="sample.sideBox">
            <div
                v-if="bool('show_heading')"
                :style="str('heading_style') === 'bar' ? bar : label"
            >
                {{ sample.sideBox.label }}
            </div>
            <div
                :style="
                    str('heading_style') === 'bar' ? { padding: '7px 8px' } : {}
                "
            >
                <div
                    v-for="line in sample.sideBox.lines"
                    :key="line"
                    class="whitespace-pre-line"
                >
                    {{ line }}
                </div>
            </div>
        </template>
        <span v-else-if="editing" class="tb-placeholder"
            >Extra information shows here when a document has it, e.g. the
            reason of a credit note</span
        >
    </template>

    <!-- Subject and introduction -->
    <template v-else-if="element.type === 'heading'">
        <div
            v-if="bool('show_title') && sample.heading.title"
            :style="{ fontSize: px(num('title_size')), fontWeight: 'bold' }"
        >
            {{ sample.heading.title }}
        </div>
        <div
            v-if="bool('show_introduction') && sample.heading.introduction"
            class="whitespace-pre-line"
            :style="{
                marginTop:
                    bool('show_title') && sample.heading.title
                        ? '6px'
                        : undefined,
            }"
        >
            {{ sample.heading.introduction }}
        </div>
        <span
            v-if="
                editing &&
                !(bool('show_title') && sample.heading.title) &&
                !(bool('show_introduction') && sample.heading.introduction)
            "
            class="tb-placeholder"
            >The subject and introduction of a document</span
        >
    </template>

    <!-- Items table -->
    <table v-else-if="element.type === 'items'" class="tb-table">
        <thead>
            <tr>
                <th
                    v-for="column in columns"
                    :key="column.key"
                    :style="{
                        ...table.th,
                        textAlign: column.align,
                        width: column.width ?? undefined,
                    }"
                >
                    {{ column.label }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(row, index) in sample.view.rows" :key="index">
                <td
                    v-if="row.type === 'section'"
                    :colspan="columns.length"
                    :style="{
                        ...table.td,
                        ...table.section,
                        paddingTop: '9px',
                    }"
                >
                    {{ row.description }}
                </td>
                <template v-else-if="row.type === 'text'">
                    <td v-if="hasNumberColumn" :style="table.td" />
                    <td
                        :colspan="
                            hasNumberColumn
                                ? columns.length - 1
                                : columns.length
                        "
                        class="whitespace-pre-line"
                        :style="{
                            ...table.td,
                            fontStyle: 'italic',
                            color: muted,
                        }"
                    >
                        {{ row.description }}
                    </td>
                </template>
                <template v-else>
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        :style="{
                            ...table.td,
                            backgroundColor:
                                table.stripe && itemNumbers[index] % 2 === 0
                                    ? table.stripe
                                    : undefined,
                            textAlign: column.align,
                            whiteSpace:
                                column.align === 'right' ? 'nowrap' : undefined,
                            color: column.key === 'number' ? muted : undefined,
                        }"
                    >
                        <template v-if="column.key === 'description'">
                            <span
                                v-if="row.code"
                                :style="{
                                    color: muted,
                                    fontSize: px(scale(page, 8)),
                                }"
                                >{{ row.code }}&nbsp; </span
                            >{{ row.description }}
                            <div
                                v-if="bool('show_details') && row.details"
                                class="whitespace-pre-line"
                                :style="{
                                    color: muted,
                                    fontSize: px(scale(page, 8.5)),
                                    marginTop: '2px',
                                }"
                            >
                                {{ row.details }}
                            </div>
                        </template>
                        <template v-else>{{
                            row.cells?.[column.key] ?? ''
                        }}</template>
                    </td>
                </template>
            </tr>
        </tbody>
    </table>

    <!-- Totals -->
    <table v-else-if="element.type === 'totals'" class="tb-table">
        <tbody>
            <tr>
                <td
                    v-if="totals.side > 0 && str('position') !== 'left'"
                    :style="{ width: `${totals.side}%` }"
                />
                <td :style="{ width: `${totals.width}%` }">
                    <table class="tb-table">
                        <tbody>
                            <tr
                                v-for="row in sample.view.totals"
                                :key="row.label"
                            >
                                <template v-if="row.style === 'grand'">
                                    <td :style="totals.grand">
                                        {{ row.label }}
                                    </td>
                                    <td
                                        :style="{
                                            ...totals.grand,
                                            textAlign: 'right',
                                            whiteSpace: 'nowrap',
                                        }"
                                    >
                                        {{ row.value }}
                                    </td>
                                </template>
                                <template v-else>
                                    <td
                                        :style="{
                                            ...totals.row,
                                            color:
                                                row.style === 'strong'
                                                    ? ink
                                                    : totals.labelColor,
                                            fontWeight:
                                                row.style === 'strong'
                                                    ? 'bold'
                                                    : undefined,
                                        }"
                                    >
                                        {{ row.label }}
                                    </td>
                                    <td
                                        :style="{
                                            ...totals.row,
                                            textAlign: 'right',
                                            whiteSpace: 'nowrap',
                                            fontWeight:
                                                row.style === 'strong'
                                                    ? 'bold'
                                                    : undefined,
                                        }"
                                    >
                                        {{ row.value }}
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-if="bool('show_tax_note') && sample.view.tax_note"
                        :style="{
                            marginTop: '4px',
                            textAlign: 'right',
                            fontSize: px(scale(page, 8)),
                            color: muted,
                        }"
                    >
                        {{ sample.view.tax_note }}
                    </div>
                </td>
                <td
                    v-if="totals.side > 0 && str('position') !== 'right'"
                    :style="{ width: `${totals.side}%` }"
                />
            </tr>
        </tbody>
    </table>

    <!-- Amount due -->
    <template v-else-if="element.type === 'amount_due' && sample.highlight">
        <div :style="label">{{ str('label') || sample.highlight.label }}</div>
        <div
            :style="{
                fontSize: px(num('value_size')),
                fontWeight: 'bold',
                color: palette.text(str('value_color'), bg),
                marginTop: '2px',
                whiteSpace: 'nowrap',
            }"
        >
            {{ sample.highlight.value }}
        </div>
    </template>

    <!-- Amount in words -->
    <template v-else-if="element.type === 'words'">
        <div v-if="bool('show_label') && str('label')" :style="label">
            {{ str('label') }}
        </div>
        <div style="margin-top: 2px">{{ sample.view.amount_in_words }}</div>
    </template>

    <!-- Bank details -->
    <template v-else-if="element.type === 'payment'">
        <template v-if="sample.payment">
            <div v-if="bool('show_label')" :style="label">
                {{ str('label') || sample.payment.label }}
            </div>
            <div v-for="line in sample.payment.lines" :key="line">
                {{ line }}
            </div>
        </template>
        <span v-else-if="editing" class="tb-placeholder"
            >Bank details show on invoices</span
        >
    </template>

    <!-- QR code -->
    <template v-else-if="element.type === 'qr' && sample.qr">
        <table v-if="str('caption_position') === 'right'" class="tb-table">
            <tbody>
                <tr>
                    <td
                        :style="{
                            width: px(num('size') + 10),
                            verticalAlign: 'middle',
                        }"
                    >
                        <img
                            :src="sample.qr"
                            alt=""
                            :style="{
                                width: px(num('size')),
                                height: px(num('size')),
                            }"
                        />
                    </td>
                    <td :style="{ verticalAlign: 'middle', color: muted }">
                        {{ caption }}
                    </td>
                </tr>
            </tbody>
        </table>
        <template v-else>
            <img
                :src="sample.qr"
                alt=""
                :style="{
                    width: px(num('size')),
                    height: px(num('size')),
                    display: 'inline-block',
                }"
            />
            <div
                v-if="str('caption_position') === 'below'"
                :style="{ marginTop: '2px', color: muted }"
            >
                {{ caption }}
            </div>
        </template>
    </template>

    <!-- Terms -->
    <template v-else-if="element.type === 'terms'">
        <template v-if="sample.view.terms">
            <div v-if="bool('show_label') && str('label')" :style="label">
                {{ str('label') }}
            </div>
            <div class="whitespace-pre-line" style="margin-top: 2px">
                {{ sample.view.terms }}
            </div>
        </template>
        <span v-else-if="editing" class="tb-placeholder"
            >The terms of the document</span
        >
    </template>

    <!-- Signature lines -->
    <table v-else-if="element.type === 'signature'" class="tb-table">
        <tbody>
            <tr>
                <td
                    v-if="signature.labels.length === 1 && align !== 'left'"
                    :style="{ width: `${signature.spacer}%` }"
                />
                <template
                    v-for="(name, index) in signature.labels"
                    :key="index"
                >
                    <td v-if="index > 0" style="width: 8%" />
                    <td :style="signature.cell">
                        <div
                            :style="{
                                borderTop: `1px solid ${signature.line}`,
                                paddingTop: '3px',
                            }"
                        >
                            {{ name }}
                        </div>
                        <div
                            v-if="
                                bool('show_company') &&
                                index === signature.labels.length - 1 &&
                                sample.seller.name
                            "
                            :style="{ color: muted }"
                        >
                            For {{ sample.seller.name }}
                        </div>
                    </td>
                </template>
                <td
                    v-if="signature.labels.length === 1 && align !== 'right'"
                    :style="{ width: `${signature.spacer}%` }"
                />
            </tr>
        </tbody>
    </table>

    <!-- Customer acceptance -->
    <template v-else-if="element.type === 'acceptance'">
        <div :style="label">Accepted by the customer</div>
        <span class="tb-placeholder" style="height: 40px"
            >Signature of the customer (accepted quotations)</span
        >
    </template>

    <!-- Closing note -->
    <template v-else-if="element.type === 'closing'">
        <div v-if="sample.closing" class="whitespace-pre-line">
            {{ sample.closing }}
        </div>
        <span v-else-if="editing" class="tb-placeholder"
            >Your closing note (set it in the invoice settings)</span
        >
    </template>

    <!-- Company footer text -->
    <template v-else-if="element.type === 'footer_text'">
        <div v-if="sample.companyFooter" class="whitespace-pre-line">
            {{ sample.companyFooter }}
        </div>
        <span v-else-if="editing" class="tb-placeholder"
            >The footer text of your company profile</span
        >
    </template>

    <!-- Text -->
    <div v-else-if="element.type === 'text'" class="whitespace-pre-line">
        {{ text }}
    </div>

    <!-- Image -->
    <template v-else-if="element.type === 'image'">
        <img
            v-if="images[str('image')]"
            :src="images[str('image')]"
            alt=""
            :style="{
                maxHeight: px(num('height')),
                maxWidth: '100%',
                display: 'inline-block',
            }"
        />
        <span
            v-else-if="editing"
            class="tb-placeholder"
            :style="{ height: px(Math.min(num('height'), 60)) }"
            >Choose an image in the settings</span
        >
    </template>

    <!-- Divider -->
    <div
        v-else-if="element.type === 'divider'"
        :style="{
            borderTop: lineCss(
                palette,
                num('thickness'),
                str('line'),
                str('color'),
            ),
            width: `${num('width')}%`,
            height: 0,
            ...dividerPlace,
        }"
    />

    <!-- Space -->
    <div
        v-else-if="element.type === 'spacer'"
        :style="{ height: px(num('height')) }"
    />
</template>
