<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileMinus } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import Pill from '@/components/app/Pill.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { index, show } from '@/routes/credit-notes';
import { show as showInvoice } from '@/routes/invoices';
import type { CreditNote, Paginated, Team } from '@/types';

const props = defineProps<{
    creditNotes: Paginated<CreditNote>;
    filters: { search: string; status: string | null };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Credit notes', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const statuses = [
    { value: 'draft', label: 'Draft' },
    { value: 'issued', label: 'Issued' },
];
</script>

<template>
    <Head title="Credit notes" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Credit notes"
            description="Corrections and returns on issued invoices. Start one from the invoice."
        />

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search number, invoice, customer…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                class="lg:w-40"
            />
        </div>

        <EmptyState
            v-if="creditNotes.data.length === 0"
            :icon="FileMinus"
            title="No credit notes"
            description="Open an issued invoice and choose “Credit note” to cancel it or correct part of it."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Invoice</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="hidden md:table-cell">Date</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="note in creditNotes.data" :key="note.id">
                        <TableCell>
                            <Link
                                :href="show([slug, note.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ note.number ?? 'Draft' }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <Link
                                v-if="note.invoice"
                                :href="showInvoice([slug, note.invoice.id]).url"
                                class="text-sm hover:underline"
                            >
                                {{ note.invoice.number }}
                            </Link>
                        </TableCell>
                        <TableCell class="text-sm">{{
                            note.customer.name
                        }}</TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            formatDate(note.date)
                        }}</TableCell>
                        <TableCell>
                            <Pill :color="note.status_color">{{
                                note.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap"
                        >
                            {{ formatMoney(note.total, note.currency) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="creditNotes.meta" :links="creditNotes.links" />
    </div>
</template>
