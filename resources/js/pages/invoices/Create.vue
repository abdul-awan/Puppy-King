<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import InvoiceForm from '@/components/invoicing/InvoiceForm.vue';
import type { InvoicePrefill } from '@/components/invoicing/InvoiceForm.vue';
import { create, index } from '@/routes/invoices';
import type {
    IdName,
    Option,
    PaymentTermOption,
    TaxRateOption,
    Team,
} from '@/types';

defineProps<{
    prefill: InvoicePrefill;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    paymentTerms: PaymentTermOption[];
    furtherTaxRate: string | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Invoices', href: index(props.currentTeam.slug) },
            { title: 'New invoice', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New invoice" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader title="New invoice" />
        <InvoiceForm v-bind="$props" />
    </div>
</template>
