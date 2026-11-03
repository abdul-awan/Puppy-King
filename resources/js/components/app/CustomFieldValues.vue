<script setup lang="ts">
import DetailItem from '@/components/app/DetailItem.vue';
import { formatDate } from '@/lib/format';
import type { CustomFieldDefinition, CustomFieldValues } from '@/types';

defineProps<{
    fields: CustomFieldDefinition[];
    values: CustomFieldValues;
}>();

const display = (
    field: CustomFieldDefinition,
    value: string | number | boolean | null | undefined,
): string | null => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (field.type === 'checkbox') {
        return value ? 'Yes' : 'No';
    }

    if (field.type === 'date') {
        return formatDate(String(value));
    }

    return String(value);
};
</script>

<template>
    <dl v-if="fields.length > 0">
        <DetailItem
            v-for="field in fields"
            :key="field.id"
            :label="field.label"
        >
            <a
                v-if="field.type === 'url' && values[field.key]"
                :href="String(values[field.key])"
                target="_blank"
                rel="noopener"
                class="text-primary hover:underline"
            >
                {{ values[field.key] }}
            </a>
            <template v-else>
                {{ display(field, values[field.key]) ?? '—' }}
            </template>
        </DetailItem>
    </dl>
</template>
