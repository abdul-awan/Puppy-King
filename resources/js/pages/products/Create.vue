<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ProductForm from '@/components/products/ProductForm.vue';
import { create, index } from '@/routes/products';
import type {
    CustomFieldDefinition,
    IdName,
    Option,
    TaxRateOption,
    Team,
} from '@/types';

defineProps<{
    types: Option[];
    categories: IdName[];
    taxRates: TaxRateOption[];
    units: string[];
    customFields: CustomFieldDefinition[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Products', href: index(props.currentTeam.slug) },
            { title: 'New product', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New product" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader title="New product" />
        <ProductForm v-bind="$props" />
    </div>
</template>
