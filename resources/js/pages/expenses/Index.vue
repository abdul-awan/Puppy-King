<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Plus, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import Pill from '@/components/app/Pill.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { create, exportMethod, index, show } from '@/routes/expenses';
import type { Expense, IdName, Option, Paginated, Team } from '@/types';

const props = defineProps<{
    expenses: Paginated<Expense>;
    filters: {
        search: string;
        status: string | null;
        category: number | null;
        company: number | null;
        period: string | null;
        from: string | null;
        to: string | null;
    };
    periods: Option[];
    categories: IdName[];
    summary: {
        to_approve_count: number;
        to_pay_total: string;
        overdue_total: string;
        month_total: string;
        month_input_tax: string;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Expenses', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const statuses = [
    { value: 'pending', label: 'To approve' },
    { value: 'to_pay', label: 'To pay' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'paid', label: 'Paid' },
    { value: 'rejected', label: 'Rejected' },
];

const exportUrl = computed(() => {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== undefined && value !== '') {
            query[key] = value;
        }
    }

    return exportMethod(slug.value, { query }).url;
});
</script>

<template>
    <Head title="Expenses" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Expenses"
            description="Bills and receipts from your suppliers."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Purchase register</a>
                </Button>
                <Button v-if="canEdit('expenses')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New expense</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">To approve</p>
                    <p class="text-2xl font-semibold">
                        {{ summary.to_approve_count }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Bills waiting for approval
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">To pay</p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.to_pay_total, currency) }}
                    </p>
                    <p
                        class="text-xs"
                        :class="
                            Number(summary.overdue_total)
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ formatMoney(summary.overdue_total, currency) }}
                        overdue
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">This month</p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.month_total, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Bills dated this month
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Input tax this month
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.month_input_tax, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Claimable in your sales tax return
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search supplier, bill no., NTN…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                class="lg:w-40"
            />
            <SelectInput
                v-model="filters.category"
                :options="idOptions(categories)"
                placeholder="All categories"
                class="lg:w-56"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
            />
        </div>

        <EmptyState
            v-if="expenses.data.length === 0"
            :icon="Wallet"
            title="No expenses found"
            description="Enter your bills (rent, utilities, purchases…) to keep track of what you owe and the input tax you can claim."
        >
            <Button
                v-if="canEdit('expenses') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"><Plus /> New expense</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead class="hidden md:table-cell">Date</TableHead>
                        <TableHead class="hidden lg:table-cell">
                            Category
                        </TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="expense in expenses.data"
                        :key="expense.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, expense.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ expense.number }}
                            </Link>
                            <p
                                class="max-w-56 truncate text-xs text-muted-foreground"
                            >
                                {{ expense.title }}
                            </p>
                        </TableCell>
                        <TableCell class="text-sm">
                            {{ expense.supplier_name }}
                            <p
                                v-if="expense.bill_number"
                                class="text-xs text-muted-foreground"
                            >
                                Bill {{ expense.bill_number }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            formatDate(expense.date)
                        }}</TableCell>
                        <TableCell class="hidden text-sm lg:table-cell">{{
                            expense.category?.name ?? '—'
                        }}</TableCell>
                        <TableCell>
                            <Pill :color="expense.status_color">{{
                                expense.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap"
                        >
                            {{ formatMoney(expense.total, expense.currency) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="expenses.meta" :links="expenses.links" />
    </div>
</template>
