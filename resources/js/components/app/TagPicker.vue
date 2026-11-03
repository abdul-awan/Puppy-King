<script setup lang="ts">
import { Check } from '@lucide/vue';
import Pill from '@/components/app/Pill.vue';
import type { Tag } from '@/types';

defineProps<{
    options: Tag[];
}>();

const model = defineModel<number[]>({ default: () => [] });

const toggle = (id: number) => {
    model.value = model.value.includes(id)
        ? model.value.filter((tagId) => tagId !== id)
        : [...model.value, id];
};
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <p v-if="options.length === 0" class="text-sm text-muted-foreground">
            No tags yet — create them under Company settings → Tags.
        </p>
        <button
            v-for="tag in options"
            :key="tag.id"
            type="button"
            class="rounded-full transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="model.includes(tag.id) ? '' : 'opacity-50 hover:opacity-80'"
            @click="toggle(tag.id)"
        >
            <Pill :color="tag.color">
                <Check v-if="model.includes(tag.id)" class="size-3" />
                {{ tag.name }}
            </Pill>
        </button>
    </div>
</template>
