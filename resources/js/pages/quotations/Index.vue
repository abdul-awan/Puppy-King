<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
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
import { create, index, show } from '@/routes/quotations';
import type { Paginated, Quotation, Team } from '@/types';

const props = defineProps<{
    quotations: Paginated<Quotation>;
    filters: { search: string; status: string | null; company: number | null };
    summary: {
        open_count: number;
        open_total: string;
        accepted_this_month: string;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const { filters } = useFilters(
    {
        search: props.filters.search,
        status: props.filters.status,
        company: props.filters.company,
    },
    () => index(slug.value).url,
);

const statuses = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'declined', label: 'Declined' },
    { value: 'expired', label: 'Expired' },
];
</script>

<template>
    <Head title="Quotations" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Quotations"
            description="Offers you send to customers."
        >
            <template #actions>
                <Button v-if="canEdit('quotations')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New quotation</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Open quotations</p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.open_total, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.open_count }} waiting for an answer
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Accepted this month
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.accepted_this_month, currency) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search number, subject, customer…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statuses"
                placeholder="All statuses"
                class="lg:w-40"
            />
        </div>

        <EmptyState
            v-if="quotations.data.length === 0"
            :icon="FileText"
            title="No quotations found"
            description="Create a quotation and send it to your customer to accept online."
        >
            <Button
                v-if="canEdit('quotations') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"><Plus /> New quotation</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Number</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="hidden md:table-cell">Date</TableHead>
                        <TableHead class="hidden lg:table-cell"
                            >Valid until</TableHead
                        >
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="quotation in quotations.data"
                        :key="quotation.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, quotation.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ quotation.number }}
                            </Link>
                            <p
                                v-if="quotation.title"
                                class="max-w-56 truncate text-xs text-muted-foreground"
                            >
                                {{ quotation.title }}
                            </p>
                        </TableCell>
                        <TableCell class="text-sm">{{
                            quotation.customer.name
                        }}</TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            formatDate(quotation.date)
                        }}</TableCell>
                        <TableCell class="hidden text-sm lg:table-cell">{{
                            formatDate(quotation.valid_until)
                        }}</TableCell>
                        <TableCell>
                            <Pill :color="quotation.status_color">{{
                                quotation.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap"
                        >
                            {{
                                formatMoney(quotation.total, quotation.currency)
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="quotations.meta" :links="quotations.links" />
    </div>
</template>
