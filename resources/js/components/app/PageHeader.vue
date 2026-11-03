<script setup lang="ts">
defineProps<{
    title: string;
    description?: string | null;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="truncate text-xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <slot name="badges" />
            </div>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
            <slot name="meta" />
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
