<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ProductForm from '@/components/products/ProductForm.vue';
import { edit, index, show } from '@/routes/products';
import type {
    CustomFieldDefinition,
    IdName,
    Option,
    Product,
    TaxRateOption,
    Team,
} from '@/types';

defineProps<{
    product: Product;
    types: Option[];
    categories: IdName[];
    taxRates: TaxRateOption[];
    units: string[];
    customFields: CustomFieldDefinition[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; product: Product }) => ({
        breadcrumbs: [
            { title: 'Products', href: index(props.currentTeam.slug) },
            {
                title: props.product.name,
                href: show([props.currentTeam.slug, props.product.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.product.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit ${product.name}`" />
        <ProductForm v-bind="$props" />
    </div>
</template>
