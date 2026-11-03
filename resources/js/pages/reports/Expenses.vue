<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFilters } from '@/composables/useFilters';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { expenses, exportMethod, index } from '@/routes/reports';
import type { Option, Team } from '@/types';

const props = defineProps<{
    filters: { period: string | null; from: string | null; to: string | null };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    report: {
        categories: {
            id: number | null;
            name: string;
            count: number;
            net: string;
            tax: string;
            total: string;
        }[];
        suppliers: {
            name: string;
            company_id: number | null;
            count: number;
            total: string;
        }[];
        totals: { count: number; net: string; tax: string; total: string };
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Expenses', href: expenses(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => expenses(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);

const share = (value: string) =>
    Number(props.report.totals.total) > 0
        ? Math.round((Number(value) / Number(props.report.totals.total)) * 100)
        : 0;

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'expenses'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
            },
        }).url,
);
</script>

<template>
    <Head title="Expenses report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Expenses"
            :description="`Approved bills dated ${formatDate(range.from)} – ${formatDate(range.to)}, in ${currency}.`"
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
            <ReportFigure label="Bills" :value="String(report.totals.count)" />
            <ReportFigure
                label="Excluding tax"
                :value="money(report.totals.net)"
            />
            <ReportFigure label="Sales tax" :value="money(report.totals.tax)" />
            <ReportFigure label="Total" :value="money(report.totals.total)" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Per category</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!report.categories.length"
                        class="text-sm text-muted-foreground"
                    >
                        No approved bills in this period.
                    </p>
                    <ul v-else class="space-y-3 text-sm">
                        <li
                            v-for="category in report.categories"
                            :key="category.id ?? 'none'"
                        >
                            <div
                                class="flex items-baseline justify-between gap-2"
                            >
                                <span class="font-medium"
                                    >{{ category.name }}
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >· {{ category.count }} bills</span
                                    ></span
                                >
                                <span class="tabular-nums">{{
                                    money(category.total)
                                }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-muted">
                                <div
                                    class="h-2 rounded-full bg-primary/75"
                                    :style="{
                                        width: `${share(category.total)}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Top suppliers</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!report.suppliers.length"
                        class="text-sm text-muted-foreground"
                    >
                        No approved bills in this period.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="supplier in report.suppliers"
                            :key="`${supplier.company_id}-${supplier.name}`"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0 truncate">
                                <Link
                                    v-if="supplier.company_id"
                                    :href="
                                        showCompany([slug, supplier.company_id])
                                            .url
                                    "
                                    class="font-medium hover:underline"
                                    >{{ supplier.name }}</Link
                                >
                                <span v-else class="font-medium">{{
                                    supplier.name
                                }}</span>
                                <span class="text-muted-foreground">
                                    · {{ supplier.count }} bills</span
                                >
                            </span>
                            <span class="shrink-0 tabular-nums">{{
                                money(supplier.total)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
