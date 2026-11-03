<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import RecurringInvoiceForm from '@/components/invoicing/RecurringInvoiceForm.vue';
import { edit, index, show } from '@/routes/recurring-invoices';
import type {
    IdName,
    Option,
    PaymentTermOption,
    RecurringInvoice,
    TaxRateOption,
    Team,
} from '@/types';

defineProps<{
    profile: RecurringInvoice;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    paymentTerms: PaymentTermOption[];
    frequencies: Option[];
    furtherTaxRate: string | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; profile: RecurringInvoice }) => ({
        breadcrumbs: [
            {
                title: 'Recurring invoices',
                href: index(props.currentTeam.slug),
            },
            {
                title: props.profile.title,
                href: show([props.currentTeam.slug, props.profile.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.profile.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${profile.title}`" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${profile.title}`"
            description="Changes apply to the next invoices; invoices created before stay as they are."
        />
        <RecurringInvoiceForm v-bind="$props" />
    </div>
</template>
