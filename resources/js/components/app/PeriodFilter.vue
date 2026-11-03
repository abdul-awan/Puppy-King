<script setup lang="ts">
import SelectInput from '@/components/app/SelectInput.vue';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

/**
 * A period select (this month, this fiscal year, …) with from/to dates for "custom".
 */
defineProps<{
    periods: Option[];
    placeholder?: string;
}>();

const period = defineModel<string | null | undefined>('period');
const from = defineModel<string | null | undefined>('from');
const to = defineModel<string | null | undefined>('to');
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <SelectInput
            v-model="period"
            :options="periods"
            :placeholder="placeholder ?? 'Any date'"
            class="w-44"
        />
        <template v-if="period === 'custom'">
            <Input
                v-model="from"
                type="date"
                class="w-40"
                aria-label="From date"
            />
            <span class="text-sm text-muted-foreground">to</span>
            <Input v-model="to" type="date" class="w-40" aria-label="To date" />
        </template>
    </div>
</template>
