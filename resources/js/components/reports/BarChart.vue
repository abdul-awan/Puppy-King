<script setup lang="ts">
import { computed } from 'vue';

/**
 * A simple bar chart without a chart library: one bar per item, scaled to the highest value.
 * Values below zero get an empty bar; the exact amount shows on hover and in the table next to it.
 */
const props = withDefaults(
    defineProps<{
        items: { key: string; label: string; value: number }[];
        label: string;
        format?: (value: number) => string;
        height?: string;
    }>(),
    { format: (value: number) => String(value), height: 'h-44' },
);

const max = computed(() =>
    Math.max(0, ...props.items.map((item) => item.value)),
);

const percent = (value: number) =>
    max.value > 0 && value > 0
        ? Math.max(1.5, Math.round((value / max.value) * 100))
        : 0;
</script>

<template>
    <figure :aria-label="label" class="space-y-1">
        <div
            class="flex items-baseline justify-between text-xs text-muted-foreground"
        >
            <span>{{ format(max) }}</span>
        </div>
        <div
            class="flex items-end gap-1 border-b border-border sm:gap-2"
            :class="height"
        >
            <div
                v-for="item in items"
                :key="item.key"
                class="group relative flex h-full flex-1 items-end justify-center"
                :title="`${item.label}: ${format(item.value)}`"
            >
                <div
                    class="w-full max-w-10 rounded-t bg-primary/75 transition-colors group-hover:bg-primary"
                    :style="{ height: `${percent(item.value)}%` }"
                />
            </div>
        </div>
        <div class="flex gap-1 sm:gap-2">
            <span
                v-for="item in items"
                :key="item.key"
                class="flex-1 truncate text-center text-[11px] text-muted-foreground"
            >
                {{ item.label }}
            </span>
        </div>
    </figure>
</template>
