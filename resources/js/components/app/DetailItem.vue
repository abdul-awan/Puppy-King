<script setup lang="ts">
defineProps<{
    label: string;
    value?: string | number | null;
}>();
</script>

<template>
    <div class="grid grid-cols-[8.5rem_1fr] gap-2 py-1.5 text-sm">
        <dt class="text-muted-foreground">{{ label }}</dt>
        <dd class="min-w-0 break-words">
            <slot>
                <span
                    v-if="value !== null && value !== undefined && value !== ''"
                >
                    {{ value }}
                </span>
                <span v-else class="text-muted-foreground">—</span>
            </slot>
        </dd>
    </div>
</template>
