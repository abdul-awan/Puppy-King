<script setup lang="ts">
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';

/**
 * A settings list of records that only have a name and an order.
 */
defineProps<{
    title: string;
    description?: string;
    itemLabel: string;
    items: { id: number; name: string; sort: number }[];
    storeUrl: string;
    updateUrl: (item: { id: number }) => string;
    destroyUrl: (item: { id: number }) => string;
    placeholder?: string;
}>();

const { canEdit } = usePermissions();

const fields: ResourceField[] = [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'sort', label: 'Order', type: 'number' },
];
</script>

<template>
    <ResourceList
        :title="title"
        :description="description"
        :item-label="itemLabel"
        :items="items"
        :columns="[{ key: 'name', label: 'Name' }]"
        :fields="fields"
        :defaults="{ name: '', sort: null }"
        :store-url="storeUrl"
        :update-url="updateUrl"
        :destroy-url="destroyUrl"
        :can-write="canEdit('settings')"
    />
</template>
