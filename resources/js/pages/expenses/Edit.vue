<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ExpenseForm from '@/components/expenses/ExpenseForm.vue';
import { edit, index, show } from '@/routes/expenses';
import type { Expense, IdName, Option, TaxRateOption, Team } from '@/types';

defineProps<{
    expense: Expense;
    categories: IdName[];
    taxRates: TaxRateOption[];
    currencies: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; expense: Expense }) => ({
        breadcrumbs: [
            { title: 'Expenses', href: index(props.currentTeam.slug) },
            {
                title: props.expense.number ?? 'Expense',
                href: show([props.currentTeam.slug, props.expense.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.expense.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${expense.number}`" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit expense ${expense.number}`"
            :description="
                expense.status === 'approved'
                    ? 'Changing the amounts or supplier sends the expense back for approval.'
                    : undefined
            "
        />
        <ExpenseForm v-bind="$props" />
    </div>
</template>
