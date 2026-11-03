<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock, Download } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatHours, formatMoney } from '@/lib/format';
import { exportMethod, index, time } from '@/routes/reports';
import type { Option, Team } from '@/types';

const props = defineProps<{
    filters: {
        period: string | null;
        from: string | null;
        to: string | null;
        group: string;
    };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    groups: Option[];
    onlyMine: boolean;
    report: {
        rows: {
            id: number | null;
            name: string;
            minutes: number;
            billable_minutes: number;
            value: string;
            invoiced: string;
            unbilled: string;
        }[];
        totals: {
            minutes: number;
            billable_minutes: number;
            value: string;
            invoiced: string;
            unbilled: string;
        };
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Time', href: time(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => time(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);

const groupLabel = computed(
    () =>
        props.groups.find((group) => group.value === props.filters.group)
            ?.label ?? 'Person',
);

const billablePercent = computed(() =>
    props.report.totals.minutes
        ? Math.round(
              (props.report.totals.billable_minutes /
                  props.report.totals.minutes) *
                  100,
          )
        : 0,
);

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'time'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
                group: props.filters.group,
            },
        }).url,
);
</script>

<template>
    <Head title="Time report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Time"
            :description="`${onlyMine ? 'Your hours' : 'Hours of the whole team'}, ${formatDate(range.from)} – ${formatDate(range.to)}. Values of billable hours in ${currency}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <SelectInput
                v-model="filters.group"
                :options="groups"
                class="w-44"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
                placeholder="This month"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure
                label="Hours"
                :value="formatHours(report.totals.minutes)"
            />
            <ReportFigure
                label="Billable hours"
                :value="formatHours(report.totals.billable_minutes)"
                :hint="`${billablePercent}% of all hours`"
            />
            <ReportFigure
                label="Value of billable hours"
                :value="money(report.totals.value)"
            />
            <ReportFigure
                label="Not invoiced yet"
                :value="money(report.totals.unbilled)"
                :tone="Number(report.totals.unbilled) > 0 ? 'bad' : 'default'"
            />
        </div>

        <EmptyState
            v-if="report.rows.length === 0"
            :icon="Clock"
            title="No hours in this period"
            description="Hours logged in time tracking, on tickets and on work orders show up here."
        />

        <div v-else class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ groupLabel }}</TableHead>
                        <TableHead class="text-right">Hours</TableHead>
                        <TableHead class="text-right">Billable</TableHead>
                        <TableHead class="text-right">Value</TableHead>
                        <TableHead class="text-right">Invoiced</TableHead>
                        <TableHead class="text-right">Not invoiced</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="row in report.rows"
                        :key="row.id ?? 'none'"
                    >
                        <TableCell class="font-medium">{{
                            row.name
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            formatHours(row.minutes)
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            formatHours(row.billable_minutes)
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            money(row.value)
                        }}</TableCell>
                        <TableCell
                            class="text-right text-muted-foreground tabular-nums"
                            >{{ money(row.invoiced) }}</TableCell
                        >
                        <TableCell class="text-right tabular-nums">{{
                            money(row.unbilled)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell>Total</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            formatHours(report.totals.minutes)
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            formatHours(report.totals.billable_minutes)
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            money(report.totals.value)
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            money(report.totals.invoiced)
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{
                            money(report.totals.unbilled)
                        }}</TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    </div>
</template>
