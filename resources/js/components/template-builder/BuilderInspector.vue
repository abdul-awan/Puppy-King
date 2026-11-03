<script setup lang="ts">
import { ArrowDown, ArrowUp, Copy, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SettingField from './SettingField.vue';
import type {
    FieldDefinition,
    PageChoices,
    PageSettings,
    StyleValues,
    UploadedImage,
} from './types';
import type { Builder } from './useTemplateBuilder';

/**
 * The settings of what is selected: an element (its content and style), a row
 * (its columns and style) or the page.
 */
const props = defineProps<{
    builder: Builder;
    pageChoices: PageChoices;
    images: UploadedImage[];
    uploadUrl: string;
    placeholders: Record<string, string>;
    gates: Record<string, boolean>;
}>();

const emit = defineEmits<{ uploaded: [image: UploadedImage] }>();

const registry = computed(() => props.builder.registry);
const element = computed(() => props.builder.selectedElement.value);
const type = computed(() =>
    element.value ? registry.value.types[element.value.type] : null,
);

// Settings that only matter for some choices of other settings.
const settingVisible = (
    key: string,
    settings: Record<string, unknown>,
): boolean => {
    switch (key) {
        case 'labels':
            return settings.mode === 'custom';
        case 'bar_background':
            return settings.heading_style === 'bar';
        case 'label_width':
            return settings.layout === 'table';
        case 'caption':
            return settings.caption_position !== 'none';
        case 'header_background':
            return (
                settings.header_style === 'fill' ||
                settings.header_style === 'light'
            );
        case 'position':
            return Number(settings.width ?? 100) < 100;
        case 'name_size':
        case 'name_color':
        case 'name_bold':
        case 'name_uppercase':
        case 'name_letter_spacing':
            return settings.show_name !== false;
        case 'heading':
            return settings.show_heading !== false;
        case 'label':
            return settings.show_label !== false;
        default:
            return true;
    }
};

const styleVisible = (key: string, style: StyleValues): boolean =>
    !['border_color', 'border_width', 'border_style'].includes(key) ||
    style.border !== 'none';

const textKeys = [
    'align',
    'font',
    'font_size',
    'color',
    'weight',
    'italic',
    'uppercase',
    'letter_spacing',
];
const boxKeys = [
    'background',
    'padding_y',
    'padding_x',
    'margin_top',
    'border',
    'border_color',
    'border_width',
    'border_style',
    'radius',
];

const styleGroups = computed(() => {
    const keys = type.value?.style ?? [];

    return [
        { title: 'Text', keys: textKeys.filter((key) => keys.includes(key)) },
        {
            title: 'Space, background and border',
            keys: boxKeys.filter((key) => keys.includes(key)),
        },
    ].filter((group) => group.keys.length > 0);
});

// Rows.
const row = computed(() =>
    props.builder.selection.value.kind === 'row'
        ? props.builder.selectedRow.value
        : null,
);
const cellIndex = ref(0);

watch(
    () => props.builder.selection.value,
    (selection) => {
        if (selection.kind === 'row') {
            cellIndex.value = selection.cell;
        }
    },
    { immediate: true },
);

const layouts = computed(() => Object.entries(props.builder.structure.layouts));

// The page.
const swatches = [
    '#1f3a5f',
    '#1e3a8a',
    '#2563eb',
    '#0369a1',
    '#0891b2',
    '#0f766e',
    '#166534',
    '#ea580c',
    '#9f1239',
    '#7c3aed',
    '#4f46e5',
    '#8b6b3d',
    '#334155',
    '#000000',
];

const pageFields = computed<
    Partial<Record<keyof PageSettings, FieldDefinition>>
>(() => ({
    font: {
        kind: 'choice',
        label: 'Type',
        options: props.pageChoices.fonts,
        default: 'sans',
    },
    font_size: {
        kind: 'choice',
        label: 'Text size',
        options: props.pageChoices.font_sizes,
        default: 'normal',
    },
    margins: {
        kind: 'choice',
        label: 'Page margins',
        options: props.pageChoices.margins,
        default: 'normal',
    },
    decoration: {
        kind: 'choice',
        label: 'Edge of the page',
        options: props.pageChoices.decorations,
        default: 'none',
    },
    footer_align: {
        kind: 'choice',
        label: 'Footer line text',
        options: props.pageChoices.footer_aligns,
        default: 'left',
    },
    footer_border: {
        kind: 'choice',
        label: 'Line above the footer',
        options: props.pageChoices.footer_borders,
        default: 'line',
    },
    label_color: {
        kind: 'color',
        label: 'Colour of the small labels',
        tokens: 'text',
        default: 'muted',
    },
    label_spacing: {
        kind: 'number',
        label: 'Letter spacing of the small labels (px)',
        min: 0,
        max: 4,
        step: 0.1,
        default: 0.5,
    },
}));

const page = computed(() => props.builder.definition.value.page);

const setPage = (key: keyof PageSettings, value: unknown) => {
    props.builder.updatePage(key, value as never);
};

const setHex = (key: 'accent' | 'text', value: string) => {
    if (/^#[0-9a-fA-F]{6}$/.test(value)) {
        props.builder.updatePage(key, value.toLowerCase());
    }
};
</script>

<template>
    <div class="space-y-5 text-sm">
        <!-- Element -->
        <template v-if="element && type">
            <div>
                <p class="text-base font-semibold">{{ type.label }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ type.description }}
                </p>
                <p
                    v-if="gates[element.type] === false"
                    class="mt-2 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    This element is switched off in the invoice settings, so it
                    does not show on documents until you switch it on there.
                </p>
                <div class="mt-2 flex flex-wrap gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        title="Move up"
                        @click="builder.moveElementBy(element.id, -1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        title="Move down"
                        @click="builder.moveElementBy(element.id, 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="builder.duplicateElement(element.id)"
                    >
                        <Copy /> Duplicate
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                        @click="builder.removeElement(element.id)"
                    >
                        <Trash2 /> Delete
                    </Button>
                </div>
            </div>

            <section v-if="Object.keys(type.settings).length" class="space-y-3">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Settings
                </p>
                <template v-for="(field, key) in type.settings" :key="key">
                    <SettingField
                        v-if="settingVisible(String(key), element.settings)"
                        :field="field"
                        :model-value="element.settings[key]"
                        :colors="registry.colors"
                        :images="images"
                        :upload-url="uploadUrl"
                        :placeholders="
                            element.type === 'text' ? placeholders : undefined
                        "
                        @update:model-value="
                            builder.updateSetting(
                                element.id,
                                String(key),
                                $event,
                            )
                        "
                        @uploaded="emit('uploaded', $event)"
                    />
                </template>
            </section>

            <section
                v-for="group in styleGroups"
                :key="group.title"
                class="space-y-3"
            >
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ group.title }}
                </p>
                <template v-for="key in group.keys" :key="key">
                    <SettingField
                        v-if="styleVisible(key, element.style)"
                        :field="registry.style[key]"
                        :model-value="element.style[key]"
                        :colors="registry.colors"
                        @update:model-value="
                            builder.updateStyle(
                                element.id,
                                key,
                                $event as string | number | boolean,
                            )
                        "
                    />
                </template>
            </section>
        </template>

        <!-- Row -->
        <template v-else-if="row">
            <div>
                <p class="text-base font-semibold">Row</p>
                <p class="text-xs text-muted-foreground">
                    Choose the columns of the row and how it looks.
                </p>
                <div class="mt-2 flex flex-wrap gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="builder.duplicateRow(row.id)"
                    >
                        <Copy /> Duplicate
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                        @click="builder.removeRow(row.id)"
                    >
                        <Trash2 /> Delete row
                    </Button>
                </div>
            </div>

            <section class="space-y-2">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Columns
                </p>
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        v-for="[key, widths] in layouts"
                        :key="key"
                        type="button"
                        class="rounded-md border p-1.5 transition hover:border-primary"
                        :class="{
                            'border-primary ring-1 ring-primary':
                                row.layout === key,
                        }"
                        :title="widths.join(' / ')"
                        @click="builder.setRowLayout(row.id, key)"
                    >
                        <span class="flex gap-0.5">
                            <span
                                v-for="(width, index) in widths"
                                :key="index"
                                class="h-4 rounded-sm bg-primary/20"
                                :style="{ width: `${width}%` }"
                            />
                        </span>
                    </button>
                </div>
                <p class="text-xs text-muted-foreground">
                    With fewer columns, the elements of the columns that go move
                    into the last one.
                </p>
            </section>

            <section class="space-y-3">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Row style
                </p>
                <template v-for="key in builder.structure.row_style" :key="key">
                    <SettingField
                        v-if="
                            styleVisible(key, row.style) &&
                            (key !== 'gap' || row.cells.length > 1)
                        "
                        :field="registry.style[key]"
                        :model-value="row.style[key]"
                        :colors="registry.colors"
                        @update:model-value="
                            builder.updateRowStyle(
                                row.id,
                                key,
                                $event as string | number | boolean,
                            )
                        "
                    />
                </template>
            </section>

            <section class="space-y-3">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Column style
                </p>
                <div v-if="row.cells.length > 1" class="flex flex-wrap gap-1">
                    <Button
                        v-for="(cell, index) in row.cells"
                        :key="index"
                        type="button"
                        size="sm"
                        :variant="cellIndex === index ? 'default' : 'outline'"
                        @click="cellIndex = index"
                    >
                        Column {{ index + 1 }}
                    </Button>
                </div>
                <template v-if="row.cells[cellIndex]">
                    <template
                        v-for="key in builder.structure.cell_style"
                        :key="key"
                    >
                        <SettingField
                            v-if="styleVisible(key, row.cells[cellIndex].style)"
                            :field="registry.style[key]"
                            :model-value="row.cells[cellIndex].style[key]"
                            :colors="registry.colors"
                            @update:model-value="
                                builder.updateCellStyle(
                                    row.id,
                                    cellIndex,
                                    key,
                                    $event as string | number | boolean,
                                )
                            "
                        />
                    </template>
                </template>
            </section>
        </template>

        <!-- Page -->
        <template v-else>
            <div>
                <p class="text-base font-semibold">Page</p>
                <p class="text-xs text-muted-foreground">
                    Colours and type of the whole template. Click an element or
                    a row on the page to change it.
                </p>
            </div>

            <section class="space-y-2">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Accent colour
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="swatch in swatches"
                        :key="swatch"
                        type="button"
                        class="size-6 rounded-full ring-offset-2 ring-offset-background"
                        :class="{
                            'ring-2 ring-primary': page.accent === swatch,
                        }"
                        :style="{ backgroundColor: swatch }"
                        :title="swatch"
                        @click="setHex('accent', swatch)"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        :value="page.accent"
                        class="size-8 cursor-pointer rounded border bg-transparent p-0.5"
                        @input="
                            setHex(
                                'accent',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                    <Input
                        :model-value="page.accent"
                        maxlength="7"
                        class="h-8 w-28 font-mono"
                        @update:model-value="setHex('accent', String($event))"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    Elements set to the accent colour change with it.
                </p>
            </section>

            <section class="space-y-2">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Text colour
                </p>
                <div class="flex items-center gap-2">
                    <input
                        type="color"
                        :value="page.text"
                        class="size-8 cursor-pointer rounded border bg-transparent p-0.5"
                        @input="
                            setHex(
                                'text',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                    <Input
                        :model-value="page.text"
                        maxlength="7"
                        class="h-8 w-28 font-mono"
                        @update:model-value="setHex('text', String($event))"
                    />
                </div>
            </section>

            <section class="space-y-3">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Type and page
                </p>
                <SettingField
                    v-for="(field, key) in pageFields"
                    :key="key"
                    :field="field!"
                    :model-value="page[key]"
                    :colors="registry.colors"
                    @update:model-value="setPage(key, $event)"
                />
            </section>
        </template>
    </div>
</template>
