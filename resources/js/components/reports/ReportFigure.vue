<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

/**
 * A key figure at the top of a report.
 */
defineProps<{
    label: string;
    value: string;
    hint?: string | null;
    tone?: 'default' | 'good' | 'bad';
}>();
</script>

<template>
    <Card>
        <CardContent class="pt-6">
            <p class="text-sm text-muted-foreground">{{ label }}</p>
            <p
                class="mt-1 text-2xl font-semibold tabular-nums"
                :class="{
                    'text-red-600 dark:text-red-400': tone === 'bad',
                    'text-green-700 dark:text-green-400': tone === 'good',
                }"
            >
                {{ value }}
            </p>
            <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
                {{ hint }}
            </p>
        </CardContent>
    </Card>
</template>
