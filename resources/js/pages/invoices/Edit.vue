<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import InvoiceForm from '@/components/invoicing/InvoiceForm.vue';
import { edit, index, show } from '@/routes/invoices';
import type {
    IdName,
    Invoice,
    Option,
    PaymentTermOption,
    TaxRateOption,
    Team,
} from '@/types';

defineProps<{
    invoice: Invoice;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    paymentTerms: PaymentTermOption[];
    furtherTaxRate: string | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; invoice: Invoice }) => ({
        breadcrumbs: [
            { title: 'Invoices', href: index(props.currentTeam.slug) },
            {
                title: props.invoice.number ?? 'Draft invoice',
                href: show([props.currentTeam.slug, props.invoice.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.invoice.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head title="Edit draft invoice" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader title="Edit draft invoice" />
        <InvoiceForm v-bind="$props" />
    </div>
</template>
