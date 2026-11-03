<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import CreditNoteForm from '@/components/invoicing/CreditNoteForm.vue';
import { edit, index, show } from '@/routes/credit-notes';
import type { CreditNote, TaxRateOption, Team } from '@/types';

defineProps<{
    creditNote: CreditNote;
    taxRates: TaxRateOption[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; creditNote: CreditNote }) => ({
        breadcrumbs: [
            { title: 'Credit notes', href: index(props.currentTeam.slug) },
            {
                title: props.creditNote.number ?? 'Draft credit note',
                href: show([props.currentTeam.slug, props.creditNote.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.creditNote.id]),
            },
        ],
    }),
});

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
</script>

<template>
    <Head title="Edit credit note" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <PageHeader title="Edit draft credit note" />
        <InputError :message="errors.credit_note" />
        <CreditNoteForm :credit-note="creditNote" :tax-rates="taxRates" />
    </div>
</template>
