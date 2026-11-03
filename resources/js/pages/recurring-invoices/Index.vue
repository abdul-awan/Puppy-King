<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Repeat } from '@lucide/vue';
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
import { create, index, show } from '@/routes/recurring-invoices';
import type { Paginated, RecurringInvoice, Team } from '@/types';

const props = defineProps<{
    profiles: Paginated<RecurringInvoice>;
    filters: { search: string; status: string | null };
    summary: { active_count: number; monthly_value: string };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Recurring invoices',
                href: index(props.currentTeam.slug),
            },
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
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'ended', label: 'Ended' },
];
</script>

<template>
    <Head title="Recurring invoices" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Recurring invoices"
            description="Invoices created automatically every week, month, quarter or year."
        >
            <template #actions>
                <Button v-if="canEdit('invoicing')" size="sm" as-child>
                    <Link :href="create(slug).url"
                        ><Plus /> New recurring invoice</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Recurring revenue per month
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.monthly_value, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.active_count }} active, incl. tax
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search name or customer…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statuses"
                placeholder="All"
                class="lg:w-40"
            />
        </div>

        <EmptyState
            v-if="profiles.data.length === 0"
            :icon="Repeat"
            title="No recurring invoices"
            description="Set up a recurring invoice for retainers, maintenance contracts or rent."
        >
            <Button
                v-if="canEdit('invoicing') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"
                    ><Plus /> New recurring invoice</Link
                >
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="hidden md:table-cell">
                            Repeats
                        </TableHead>
                        <TableHead>Next invoice</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="profile in profiles.data"
                        :key="profile.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, profile.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ profile.title }}
                            </Link>
                        </TableCell>
                        <TableCell class="text-sm">{{
                            profile.customer_name
                        }}</TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            profile.frequency_label
                        }}</TableCell>
                        <TableCell class="text-sm">{{
                            formatDate(profile.next_date)
                        }}</TableCell>
                        <TableCell>
                            <Pill :color="profile.status_color">{{
                                profile.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap"
                        >
                            {{ formatMoney(profile.total, profile.currency) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="profiles.meta" :links="profiles.links" />
    </div>
</template>
