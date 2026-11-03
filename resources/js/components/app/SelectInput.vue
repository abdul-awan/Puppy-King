<script setup lang="ts" generic="T extends string | number">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

type OptionItem = {
    value: T;
    label: string;
};

const props = defineProps<{
    options: OptionItem[];
    placeholder?: string;
    id?: string;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<T | null | undefined>();
</script>

<template>
    <select
        :id="id"
        v-model="model"
        :disabled="disabled"
        :class="
            cn(
                'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30 [&>option]:bg-background',
                props.class,
            )
        "
    >
        <option v-if="placeholder !== undefined" :value="null">
            {{ placeholder }}
        </option>
        <option
            v-for="option in options"
            :key="String(option.value)"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
