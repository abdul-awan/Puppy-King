<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import QuotationForm from '@/components/documents/QuotationForm.vue';
import { edit, index, show } from '@/routes/quotations';
import type { IdName, Option, Quotation, TaxRateOption, Team } from '@/types';

defineProps<{
    quotation: Quotation;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    furtherTaxRate: string | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; quotation: Quotation }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index(props.currentTeam.slug) },
            {
                title: props.quotation.number ?? 'Quotation',
                href: show([props.currentTeam.slug, props.quotation.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.quotation.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${quotation.number}`" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit quotation ${quotation.number}`" />
        <QuotationForm v-bind="$props" />
    </div>
</template>
