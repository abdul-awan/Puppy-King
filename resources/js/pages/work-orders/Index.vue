<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ClipboardPen, Plus, ReceiptText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import Pill from '@/components/app/Pill.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatDate, formatDuration } from '@/lib/format';
import { create, index, invoice, show } from '@/routes/work-orders';
import type { IdName, Option, Paginated, Team, WorkOrder } from '@/types';

const props = defineProps<{
    workOrders: Paginated<WorkOrder>;
    filters: {
        search: string;
        status: string;
        assignee: string;
        company: number | null;
        period: string | null;
        from: string | null;
        to: string | null;
    };
    users: IdName[];
    periods: Option[];
    summary: { open: number; unsigned: number; to_invoice: number };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Work orders', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const statusOptions = [
    { value: 'all', label: 'All work orders' },
    { value: 'open', label: 'Open' },
    { value: 'unsigned', label: 'Completed, not signed' },
    { value: 'to_invoice', label: 'To invoice' },
    { value: 'invoiced', label: 'Invoiced' },
];

const assigneeOptions = [
    { value: 'all', label: 'Anyone' },
    { value: 'me', label: 'Done by me' },
    ...props.users.map((user) => ({
        value: String(user.id),
        label: user.name,
    })),
];

const cards = [
    { key: 'open', label: 'Open', status: 'open' },
    { key: 'unsigned', label: 'Not signed yet', status: 'unsigned' },
    { key: 'to_invoice', label: 'To invoice', status: 'to_invoice' },
] as const;

// Completed work orders of one customer can be put on one invoice together.
const canInvoice = computed(() => canEdit('invoicing'));
const selected = ref<number[]>([]);

const invoiceable = (workOrder: WorkOrder) =>
    workOrder.status === 'completed' && workOrder.invoice_id === null;

const toggle = (id: number, on: boolean) => {
    selected.value = on
        ? [...selected.value, id]
        : selected.value.filter((item) => item !== id);
};

watch(
    () => props.workOrders.data,
    () => {
        selected.value = [];
    },
);

const invoiceSelected = () => {
    router.post(invoice(slug.value).url, { work_orders: selected.value });
};
</script>

<template>
    <Head title="Work orders" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Work orders"
            description="Work done for your customers, with the hours, materials and signature."
        >
            <template #actions>
                <Button v-if="canEdit('work_orders')" size="sm" as-child>
                    <Link :href="create(slug).url"
                        ><Plus /> New work order</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <button
                v-for="card in cards"
                :key="card.key"
                type="button"
                class="text-left"
                @click="filters.status = card.status"
            >
                <Card class="transition-colors hover:bg-muted/40">
                    <CardContent class="pt-6">
                        <p class="text-sm text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ summary[card.key] }}
                        </p>
                    </CardContent>
                </Card>
            </button>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search number, job, customer, location…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statusOptions"
                class="lg:w-52"
            />
            <SelectInput
                v-model="filters.assignee"
                :options="assigneeOptions"
                class="lg:w-44"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
            />
        </div>

        <div
            v-if="selected.length"
            class="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 px-4 py-2 text-sm"
        >
            <span
                >{{ selected.length }} work order{{
                    selected.length === 1 ? '' : 's'
                }}
                selected</span
            >
            <Button size="sm" @click="invoiceSelected">
                <ReceiptText /> Put on one invoice
            </Button>
            <Button size="sm" variant="ghost" @click="selected = []"
                >Clear</Button
            >
            <InputError :message="errors.work_orders" />
        </div>

        <EmptyState
            v-if="workOrders.data.length === 0"
            :icon="ClipboardPen"
            title="No work orders here"
            description="A work order records a job for a customer: what was done, the hours and materials, and the customer's signature."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-if="canInvoice" class="w-8" />
                        <TableHead>Work order</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Customer</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Done by</TableHead
                        >
                        <TableHead class="hidden text-right sm:table-cell"
                            >Hours</TableHead
                        >
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="workOrder in workOrders.data"
                        :key="workOrder.id"
                    >
                        <TableCell v-if="canInvoice">
                            <Checkbox
                                v-if="invoiceable(workOrder)"
                                :model-value="selected.includes(workOrder.id)"
                                :aria-label="`Select ${workOrder.number}`"
                                @update:model-value="
                                    toggle(workOrder.id, Boolean($event))
                                "
                            />
                        </TableCell>
                        <TableCell>
                            <Link
                                :href="show([slug, workOrder.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ workOrder.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ workOrder.number }} ·
                                {{ formatDate(workOrder.date) }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            workOrder.customer_label
                        }}</TableCell>
                        <TableCell class="hidden text-sm lg:table-cell">{{
                            workOrder.assignee?.name ?? '—'
                        }}</TableCell>
                        <TableCell
                            class="hidden text-right text-sm tabular-nums sm:table-cell"
                        >
                            {{
                                workOrder.logged_minutes
                                    ? formatDuration(workOrder.logged_minutes)
                                    : '—'
                            }}
                        </TableCell>
                        <TableCell>
                            <Pill :color="workOrder.status_color">{{
                                workOrder.status_label
                            }}</Pill>
                            <p
                                v-if="workOrder.invoice"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{
                                    workOrder.invoice.number ?? 'Draft invoice'
                                }}
                            </p>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="workOrders.meta" :links="workOrders.links" />
    </div>
</template>
