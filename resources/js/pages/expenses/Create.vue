<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ExpenseForm from '@/components/expenses/ExpenseForm.vue';
import type { ExpensePrefill } from '@/components/expenses/ExpenseForm.vue';
import { create, index } from '@/routes/expenses';
import type { IdName, Option, TaxRateOption, Team } from '@/types';

defineProps<{
    prefill: ExpensePrefill;
    categories: IdName[];
    taxRates: TaxRateOption[];
    currencies: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Expenses', href: index(props.currentTeam.slug) },
            { title: 'New expense', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New expense" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="New expense"
            description="Enter a bill or receipt from a supplier."
        />
        <ExpenseForm v-bind="$props" />
    </div>
</template>
