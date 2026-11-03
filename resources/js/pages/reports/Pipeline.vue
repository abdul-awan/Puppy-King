<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFilters } from '@/composables/useFilters';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { exportMethod, index, pipeline } from '@/routes/reports';
import type { Option, Team } from '@/types';

type Group = { name: string; count: number; value: string };

const props = defineProps<{
    filters: {
        period: string | null;
        from: string | null;
        to: string | null;
        pipeline: number | null;
    };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    report: {
        pipelines: { id: number; name: string }[];
        pipeline_id: number | null;
        currency: string;
        phases: {
            id: number;
            name: string;
            probability: number;
            count: number;
            value: string;
            weighted: string;
        }[];
        open: { count: number; value: string; weighted: string };
        won: { count: number; value: string };
        lost: { count: number; value: string };
        win_rate: number | null;
        average_won: string;
        lost_reasons: Group[];
        sources: Group[];
        people: Group[];
        other_currency: number;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Sales pipeline', href: pipeline(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => pipeline(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);

const maxPhaseValue = computed(() =>
    Math.max(1, ...props.report.phases.map((phase) => Number(phase.value))),
);

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'pipeline'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
                pipeline: props.report.pipeline_id ?? undefined,
            },
        }).url,
);

const groups = computed(() => [
    {
        key: 'lost_reasons',
        title: 'Why deals were lost',
        empty: 'No deals lost in this period.',
        items: props.report.lost_reasons,
    },
    {
        key: 'sources',
        title: 'Won deals per source',
        empty: 'No deals won in this period.',
        items: props.report.sources,
    },
    {
        key: 'people',
        title: 'Won deals per person',
        empty: 'No deals won in this period.',
        items: props.report.people,
    },
]);
</script>

<template>
    <Head title="Sales pipeline report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Sales pipeline"
            :description="`Open deals now; won and lost deals closed ${formatDate(range.from)} – ${formatDate(range.to)}. Amounts of deals in ${currency}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <SelectInput
                v-if="report.pipelines.length > 1"
                v-model="filters.pipeline"
                :options="idOptions(report.pipelines)"
                class="w-48"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
                placeholder="This quarter"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure
                label="Open deals"
                :value="money(report.open.value)"
                :hint="`${report.open.count} deals`"
            />
            <ReportFigure
                label="Weighted by chance"
                :value="money(report.open.weighted)"
                hint="Value × chance of the phase or deal"
            />
            <ReportFigure
                label="Won in the period"
                :value="money(report.won.value)"
                :hint="`${report.won.count} deals · average ${money(report.average_won)}`"
                tone="good"
            />
            <ReportFigure
                label="Win rate"
                :value="report.win_rate === null ? '—' : `${report.win_rate}%`"
                :hint="`${report.won.count} won, ${report.lost.count} lost`"
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Open deals per phase</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!report.phases.length"
                    class="text-sm text-muted-foreground"
                >
                    This pipeline has no phases.
                </p>
                <ul v-else class="space-y-3 text-sm">
                    <li v-for="phase in report.phases" :key="phase.id">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-medium"
                                >{{ phase.name }}
                                <span class="font-normal text-muted-foreground"
                                    >· {{ phase.count }} deals ·
                                    {{ phase.probability }}% chance</span
                                ></span
                            >
                            <span class="tabular-nums">{{
                                money(phase.value)
                            }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-primary/75"
                                :style="{
                                    width: `${Math.round((Number(phase.value) / maxPhaseValue) * 100)}%`,
                                }"
                            />
                        </div>
                    </li>
                </ul>
                <p
                    v-if="report.other_currency"
                    class="mt-4 text-xs text-muted-foreground"
                >
                    {{ report.other_currency }} open deal(s) in another currency
                    are not included in these amounts.
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card v-for="group in groups" :key="group.key">
                <CardHeader>
                    <CardTitle>{{ group.title }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!group.items.length"
                        class="text-sm text-muted-foreground"
                    >
                        {{ group.empty }}
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="item in group.items"
                            :key="item.name"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0 truncate"
                                >{{ item.name }}
                                <span class="text-muted-foreground"
                                    >({{ item.count }})</span
                                ></span
                            >
                            <span class="shrink-0 tabular-nums">{{
                                money(item.value)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
