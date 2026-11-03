<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import RecurringInvoiceForm from '@/components/invoicing/RecurringInvoiceForm.vue';
import type { RecurringPrefill } from '@/components/invoicing/RecurringInvoiceForm.vue';
import { create, index } from '@/routes/recurring-invoices';
import type {
    IdName,
    Option,
    PaymentTermOption,
    TaxRateOption,
    Team,
} from '@/types';

defineProps<{
    prefill: RecurringPrefill;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    paymentTerms: PaymentTermOption[];
    frequencies: Option[];
    furtherTaxRate: string | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Recurring invoices',
                href: index(props.currentTeam.slug),
            },
            { title: 'New', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New recurring invoice" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="New recurring invoice"
            description="For retainers, maintenance contracts (AMC), rent and other fixed monthly or yearly fees."
        />
        <RecurringInvoiceForm v-bind="$props" />
    </div>
</template>
