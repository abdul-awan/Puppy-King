<script setup lang="ts">
import {
    Banknote,
    Building,
    CaseSensitive,
    Heading,
    Image,
    ImagePlus,
    Info,
    Landmark,
    List,
    MessageSquare,
    Minus,
    MoveVertical,
    PanelBottom,
    PenLine,
    Pilcrow,
    QrCode,
    ScrollText,
    Sigma,
    Signature,
    Stamp,
    Table,
    Type,
    User,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import type { Builder } from './useTemplateBuilder';

/**
 * The elements and row layouts to drag onto the page (or click to add).
 */
const props = defineProps<{ builder: Builder }>();

const icons: Record<string, Component> = {
    image: Image,
    building: Building,
    heading: Heading,
    stamp: Stamp,
    list: List,
    user: User,
    info: Info,
    pilcrow: Pilcrow,
    table: Table,
    sigma: Sigma,
    banknote: Banknote,
    'case-sensitive': CaseSensitive,
    landmark: Landmark,
    'qr-code': QrCode,
    'scroll-text': ScrollText,
    signature: Signature,
    'pen-line': PenLine,
    'message-square': MessageSquare,
    'panel-bottom': PanelBottom,
    type: Type,
    'image-plus': ImagePlus,
    minus: Minus,
    'move-vertical': MoveVertical,
};

const tab = ref<'elements' | 'rows'>('elements');

const groups = computed(() =>
    Object.entries(props.builder.registry.groups).map(([key, label]) => ({
        key,
        label,
        types: Object.entries(props.builder.registry.types)
            .filter(([, definition]) => definition.group === key)
            .map(([type, definition]) => ({ type, ...definition })),
    })),
);

const layouts = computed(() =>
    Object.entries(props.builder.structure.layouts).map(([key, widths]) => ({
        key,
        widths,
        label:
            widths.length === 1
                ? 'One column'
                : `${widths.length} columns · ${widths.join(' / ')}`,
    })),
);

// A clicked row goes below the selected row (or at the end).
const addRowAfterSelection = (layout: string) => {
    const row = props.builder.selectedRow.value;
    const rows = props.builder.definition.value.rows;
    const index = row
        ? rows.findIndex((item) => item.id === row.id) + 1
        : rows.length;
    props.builder.addRow(layout, index);
};
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="grid grid-cols-2 gap-1 rounded-md bg-muted p-1 text-sm">
            <button
                type="button"
                class="rounded px-2 py-1"
                :class="
                    tab === 'elements'
                        ? 'bg-background font-medium shadow-xs'
                        : 'text-muted-foreground'
                "
                @click="tab = 'elements'"
            >
                Elements
            </button>
            <button
                type="button"
                class="rounded px-2 py-1"
                :class="
                    tab === 'rows'
                        ? 'bg-background font-medium shadow-xs'
                        : 'text-muted-foreground'
                "
                @click="tab = 'rows'"
            >
                Rows
            </button>
        </div>

        <p class="mt-3 text-xs text-muted-foreground">
            {{
                tab === 'elements'
                    ? 'Drag an element onto the page, or click it to add it below what is selected.'
                    : 'Drag a row between the rows of the page, or click it to add it below the selected row.'
            }}
        </p>

        <div class="mt-3 min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
            <template v-if="tab === 'elements'">
                <div v-for="group in groups" :key="group.key">
                    <p
                        class="mb-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.label }}
                    </p>
                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            v-for="item in group.types"
                            :key="item.type"
                            type="button"
                            draggable="true"
                            class="flex cursor-grab flex-col items-center gap-1 rounded-md border bg-background p-2 text-center text-xs transition hover:border-primary hover:bg-accent active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-50"
                            :title="item.description"
                            :disabled="!builder.canAddElement.value"
                            @dragstart="
                                builder.startDrag(
                                    { kind: 'new-element', type: item.type },
                                    $event,
                                )
                            "
                            @dragend="builder.endDrag()"
                            @click="builder.addElement(item.type)"
                        >
                            <component
                                :is="icons[item.icon] ?? Type"
                                class="size-4 text-muted-foreground"
                            />
                            <span class="leading-tight">{{ item.label }}</span>
                        </button>
                    </div>
                </div>
            </template>

            <div v-else class="grid gap-1.5">
                <button
                    v-for="layout in layouts"
                    :key="layout.key"
                    type="button"
                    draggable="true"
                    class="cursor-grab rounded-md border bg-background p-2 text-left transition hover:border-primary hover:bg-accent active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!builder.canAddRow.value"
                    @dragstart="
                        builder.startDrag(
                            { kind: 'new-row', layout: layout.key },
                            $event,
                        )
                    "
                    @dragend="builder.endDrag()"
                    @click="addRowAfterSelection(layout.key)"
                >
                    <div class="flex gap-1">
                        <span
                            v-for="(width, index) in layout.widths"
                            :key="index"
                            class="h-5 rounded-sm bg-primary/15 ring-1 ring-primary/30"
                            :style="{ width: `${width}%` }"
                        />
                    </div>
                    <span class="mt-1 block text-xs text-muted-foreground">{{
                        layout.label
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
