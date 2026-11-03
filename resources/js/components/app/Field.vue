<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

defineProps<{
    label?: string;
    id?: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="grid content-start gap-1.5">
        <Label v-if="label" :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>
        <slot />
        <p v-if="hint && !error" class="text-xs text-muted-foreground">
            {{ hint }}
        </p>
        <InputError :message="error" />
    </div>
</template>
