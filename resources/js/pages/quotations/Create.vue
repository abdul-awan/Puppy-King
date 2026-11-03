<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import QuotationForm from '@/components/documents/QuotationForm.vue';
import { create, index } from '@/routes/quotations';
import type { IdName, Option, TaxRateOption, Team } from '@/types';

defineProps<{
    prefill: {
        deal: { id: number; title: string } | null;
        company: IdName | null;
        contact: IdName | null;
        apply_further_tax: boolean;
        title: string | null;
        currency: string;
        date: string;
        valid_until: string;
        department_id: number | null;
    };
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    furtherTaxRate: string | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index(props.currentTeam.slug) },
            { title: 'New quotation', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New quotation" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader title="New quotation" />
        <QuotationForm v-bind="$props" />
    </div>
</template>
