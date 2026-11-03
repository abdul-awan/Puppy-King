<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed px-6 py-12 text-center"
    >
        <div
            v-if="icon"
            class="flex size-11 items-center justify-center rounded-full bg-muted"
        >
            <component :is="icon" class="size-5 text-muted-foreground" />
        </div>
        <div class="space-y-1">
            <p class="font-medium">{{ title }}</p>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
