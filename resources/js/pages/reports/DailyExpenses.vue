<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import BarChart from '@/components/reports/BarChart.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFilters } from '@/composables/useFilters';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney, formatMonth, parseDate } from '@/lib/format';
import { dailyExpenses, exportMethod, index } from '@/routes/reports';
import type { Option, Team } from '@/types';

type Row = { name: string; count: number; total: string };

const props = defineProps<{
    filters: { period: string | null; from: string | null; to: string | null };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    report: {
        categories: (Row & { id: number | null })[];
        methods: {
            method: string;
            label: string;
            count: number;
            total: string;
        }[];
        people: Row[];
        timeline: {
            unit: 'day' | 'month';
            items: { key: string; total: string }[];
        };
        totals: { count: number; total: string; cash: string; per_day: string };
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            {
                title: 'Daily expenses',
                href: dailyExpenses(props.currentTeam.slug),
            },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => dailyExpenses(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);

const share = (value: string) =>
    Number(props.report.totals.total) > 0
        ? Math.round((Number(value) / Number(props.report.totals.total)) * 100)
        : 0;

const dayLabel = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
});

const chart = computed(() =>
    props.report.timeline.items.map((item) => {
        const date = parseDate(item.key);

        return {
            key: item.key,
            label:
                props.report.timeline.unit === 'day'
                    ? date
                        ? dayLabel.format(date)
                        : item.key
                    : formatMonth(item.key, true),
            value: Number(item.total),
        };
    }),
);

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'daily-expenses'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
            },
        }).url,
);

const lists = computed(() => [
    { title: 'Per category', rows: props.report.categories, bar: true },
    {
        title: 'Paid with',
        rows: props.report.methods.map((method) => ({
            name: method.label,
            count: method.count,
            total: method.total,
        })),
        bar: true,
    },
    { title: 'Paid by', rows: props.report.people, bar: false },
]);
</script>

<template>
    <Head title="Daily expenses report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Daily expenses"
            :description="`Spending from ${formatDate(range.from)} to ${formatDate(range.to)}, in ${currency}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
            </template>
        </PageHeader>

        <PeriodFilter
            v-model:period="filters.period"
            v-model:from="filters.from"
            v-model:to="filters.to"
            :periods="periods"
            placeholder="This month"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure
                label="Spent"
                :value="money(report.totals.total)"
                :hint="`${report.totals.count} expenses`"
            />
            <ReportFigure
                label="Paid in cash"
                :value="money(report.totals.cash)"
            />
            <ReportFigure
                label="Per day"
                :value="money(report.totals.per_day)"
                hint="On average, up to today"
            />
            <ReportFigure
                label="Biggest category"
                :value="report.categories[0]?.name ?? '—'"
                :hint="
                    report.categories[0]
                        ? money(report.categories[0].total)
                        : null
                "
            />
        </div>

        <Card v-if="chart.length > 1">
            <CardHeader>
                <CardTitle>{{
                    report.timeline.unit === 'day' ? 'Per day' : 'Per month'
                }}</CardTitle>
            </CardHeader>
            <CardContent>
                <BarChart
                    :items="chart"
                    label="Daily expenses over time"
                    :format="(value) => money(value)"
                />
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card v-for="list in lists" :key="list.title">
                <CardHeader>
                    <CardTitle>{{ list.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!list.rows.length"
                        class="text-sm text-muted-foreground"
                    >
                        No expenses in this period.
                    </p>
                    <ul v-else class="space-y-3 text-sm">
                        <li v-for="row in list.rows" :key="row.name">
                            <div
                                class="flex items-baseline justify-between gap-2"
                            >
                                <span class="font-medium"
                                    >{{ row.name }}
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >· {{ row.count }}</span
                                    ></span
                                >
                                <span class="tabular-nums">{{
                                    money(row.total)
                                }}</span>
                            </div>
                            <div
                                v-if="list.bar"
                                class="mt-1 h-2 rounded-full bg-muted"
                            >
                                <div
                                    class="h-2 rounded-full bg-primary/75"
                                    :style="{ width: `${share(row.total)}%` }"
                                />
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
