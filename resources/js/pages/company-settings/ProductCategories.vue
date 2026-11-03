<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/settings/product-categories';
import type { Team } from '@/types';

defineProps<{
    categories: {
        id: number;
        name: string;
        sort: number;
        products_count: number;
    }[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            {
                title: 'Product categories',
                href: index(props.currentTeam.slug),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const fields: ResourceField[] = [
    {
        key: 'name',
        label: 'Name',
        type: 'text',
        required: true,
        placeholder: 'e.g. Laptops',
    },
    { key: 'sort', label: 'Order', type: 'number' },
];
</script>

<template>
    <Head title="Product categories" />

    <ResourceList
        title="Product categories"
        description="Group your products and services."
        item-label="category"
        :items="categories"
        :columns="[
            { key: 'name', label: 'Name' },
            { key: 'products_count', label: 'Products' },
        ]"
        :fields="fields"
        :defaults="{ name: '', sort: null }"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
        :can-write="canEdit('settings')"
        empty-text="No categories yet."
    />
</template>
