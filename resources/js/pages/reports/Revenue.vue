<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import BarChart from '@/components/reports/BarChart.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    formatDate,
    formatMoney,
    formatMonth,
    formatNumber,
} from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { show as showContact } from '@/routes/contacts';
import { show as showProduct } from '@/routes/products';
import { exportMethod, index, revenue } from '@/routes/reports';
import type { Option, Team } from '@/types';

const props = defineProps<{
    filters: { period: string | null; from: string | null; to: string | null };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    totals: {
        invoiced: string;
        credited: string;
        net: string;
        invoices: number;
    };
    months: {
        month: string;
        invoiced: string;
        credited: string;
        net: string;
    }[];
    customers: {
        name: string;
        company_id: number | null;
        contact_id: number | null;
        net: string;
    }[];
    products: {
        product_id: number | null;
        name: string;
        quantity: string;
        net: string;
    }[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Revenue', href: revenue(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => revenue(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);

const chart = computed(() =>
    props.months.map((month) => ({
        key: month.month,
        label: formatMonth(month.month, true),
        value: Number(month.net),
    })),
);

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'revenue'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
            },
        }).url,
);

const customerUrl = (customer: {
    company_id: number | null;
    contact_id: number | null;
}) =>
    customer.company_id
        ? showCompany([slug.value, customer.company_id]).url
        : customer.contact_id
          ? showContact([slug.value, customer.contact_id]).url
          : null;
</script>

<template>
    <Head title="Revenue report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Revenue"
            :description="`Issued invoices minus credit notes, excluding sales tax, in ${currency}. ${formatDate(range.from)} – ${formatDate(range.to)}.`"
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
            placeholder="This fiscal year"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure label="Net revenue" :value="money(totals.net)" />
            <ReportFigure
                label="Invoiced"
                :value="money(totals.invoiced)"
                :hint="`${totals.invoices} invoices`"
            />
            <ReportFigure label="Credited" :value="money(totals.credited)" />
            <ReportFigure
                label="Average per month"
                :value="
                    money(
                        months.length ? Number(totals.net) / months.length : 0,
                    )
                "
            />
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Per month</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <BarChart
                    :items="chart"
                    label="Net revenue per month"
                    :format="money"
                />
                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Month</TableHead>
                                <TableHead class="text-right"
                                    >Invoiced</TableHead
                                >
                                <TableHead class="text-right"
                                    >Credited</TableHead
                                >
                                <TableHead class="text-right">Net</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="month in months"
                                :key="month.month"
                            >
                                <TableCell>{{
                                    formatMonth(month.month)
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    money(month.invoiced)
                                }}</TableCell>
                                <TableCell
                                    class="text-right text-muted-foreground tabular-nums"
                                    >{{ money(month.credited) }}</TableCell
                                >
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                    >{{ money(month.net) }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Top customers</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!customers.length"
                        class="text-sm text-muted-foreground"
                    >
                        No revenue in this period.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="(customer, position) in customers"
                            :key="`${customer.company_id}-${customer.contact_id}-${customer.name}`"
                            class="flex items-center justify-between gap-3 py-2"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                    class="w-5 shrink-0 text-right text-muted-foreground tabular-nums"
                                    >{{ position + 1 }}</span
                                >
                                <Link
                                    v-if="customerUrl(customer)"
                                    :href="customerUrl(customer) ?? ''"
                                    class="truncate font-medium hover:underline"
                                    >{{ customer.name }}</Link
                                >
                                <span v-else class="truncate font-medium">{{
                                    customer.name
                                }}</span>
                            </span>
                            <span class="shrink-0 tabular-nums">{{
                                money(customer.net)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Top products &amp; services</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!products.length"
                        class="text-sm text-muted-foreground"
                    >
                        No revenue in this period.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="product in products"
                            :key="product.product_id ?? 'other'"
                            class="flex items-center justify-between gap-3 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    v-if="product.product_id"
                                    :href="
                                        showProduct([slug, product.product_id])
                                            .url
                                    "
                                    class="block truncate font-medium hover:underline"
                                    >{{ product.name }}</Link
                                >
                                <span
                                    v-else
                                    class="block truncate text-muted-foreground"
                                    >{{ product.name }}</span
                                >
                                <span
                                    v-if="product.product_id"
                                    class="text-xs text-muted-foreground"
                                    >{{
                                        formatNumber(product.quantity, 3)
                                    }}
                                    sold</span
                                >
                            </span>
                            <span class="shrink-0 tabular-nums">{{
                                money(product.net)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
