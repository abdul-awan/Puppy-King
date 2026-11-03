<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    color?: string | null;
    class?: HTMLAttributes['class'];
}>();

const palette: Record<string, string> = {
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    teal: 'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300',
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    purple: 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300',
    pink: 'bg-pink-100 text-pink-700 dark:bg-pink-950 dark:text-pink-300',
};

const colorClasses = computed(
    () => palette[props.color ?? 'gray'] ?? palette.gray,
);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                colorClasses,
                props.class,
            )
        "
    >
        <slot />
    </span>
</template>
