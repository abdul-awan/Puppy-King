<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Plus, ReceiptText } from '@lucide/vue';
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
import { create, exportMethod, index, show } from '@/routes/invoices';
import type { Invoice, Option, Paginated, Team } from '@/types';

const props = defineProps<{
    invoices: Paginated<Invoice>;
    filters: {
        search: string;
        status: string | null;
        company: number | null;
        period: string | null;
        from: string | null;
        to: string | null;
    };
    periods: Option[];
    summary: {
        outstanding_count: number;
        outstanding_total: string;
        overdue_count: number;
        overdue_total: string;
        received_this_month: string;
        draft_count: number;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Invoices', href: index(props.currentTeam.slug) },
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
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'partial', label: 'Partially paid' },
    { value: 'paid', label: 'Paid' },
    { value: 'credited', label: 'Credited' },
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
    <Head title="Invoices" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader title="Invoices" description="What your customers owe you.">
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
                <Button v-if="canEdit('invoicing')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New invoice</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Outstanding</p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.outstanding_total, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.outstanding_count }} open invoice(s)
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Overdue</p>
                    <p
                        class="text-2xl font-semibold"
                        :class="{ 'text-destructive': summary.overdue_count }"
                    >
                        {{ formatMoney(summary.overdue_total, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.overdue_count }} past the due date
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Received this month
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.received_this_month, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Including tax withheld by customers
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Drafts</p>
                    <p class="text-2xl font-semibold">
                        {{ summary.draft_count }}
                    </p>
                    <p class="text-xs text-muted-foreground">Not issued yet</p>
                </CardContent>
            </Card>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search number, customer, FBR no.…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                class="lg:w-44"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
            />
        </div>

        <EmptyState
            v-if="invoices.data.length === 0"
            :icon="ReceiptText"
            title="No invoices found"
            description="Create an invoice, issue it and send it to your customer."
        >
            <Button
                v-if="canEdit('invoicing') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"><Plus /> New invoice</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="hidden md:table-cell">Date</TableHead>
                        <TableHead class="hidden lg:table-cell">Due</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">
                            Balance
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="invoice in invoices.data"
                        :key="invoice.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, invoice.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ invoice.number ?? 'Draft' }}
                            </Link>
                            <p
                                v-if="invoice.title"
                                class="max-w-56 truncate text-xs text-muted-foreground"
                            >
                                {{ invoice.title }}
                            </p>
                        </TableCell>
                        <TableCell class="text-sm">{{
                            invoice.customer.name
                        }}</TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            formatDate(invoice.date)
                        }}</TableCell>
                        <TableCell
                            class="hidden text-sm lg:table-cell"
                            :class="{ 'text-destructive': invoice.is_overdue }"
                        >
                            {{ formatDate(invoice.due_date) }}
                            <span v-if="invoice.is_overdue" class="text-xs">
                                ({{ invoice.days_overdue }}d)
                            </span>
                        </TableCell>
                        <TableCell>
                            <Pill :color="invoice.status_color">{{
                                invoice.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap"
                        >
                            {{ formatMoney(invoice.total, invoice.currency) }}
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm whitespace-nowrap sm:table-cell"
                        >
                            {{
                                invoice.status === 'open'
                                    ? formatMoney(
                                          invoice.balance_due,
                                          invoice.currency,
                                      )
                                    : '—'
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="invoices.meta" :links="invoices.links" />
    </div>
</template>
