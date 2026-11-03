<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
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
import { formatDate, formatMoney } from '@/lib/format';
import { exportMethod, index, salesTax } from '@/routes/reports';
import type { Option, Team } from '@/types';

type TaxLine = {
    authority: string;
    authority_label: string;
    name: string;
    rate: string;
    net: string;
    tax: string;
};

const props = defineProps<{
    filters: { period: string | null; from: string | null; to: string | null };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    report: {
        output: TaxLine[];
        further_tax: string;
        input: TaxLine[];
        input_not_claimable: string;
        authorities: {
            authority: string;
            label: string;
            output_tax: string;
            input_tax: string;
            net: string;
        }[];
        withheld_by_customers: { income_tax: string; sales_tax: string };
        withheld_from_suppliers: { income_tax: string; sales_tax: string };
        totals: { output_tax: string; input_tax: string; net: string };
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Sales tax', href: salesTax(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => salesTax(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);
const net = computed(() => Number(props.report.totals.net));

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'sales-tax'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
            },
        }).url,
);
</script>

<template>
    <Head title="Sales tax report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Sales tax"
            :description="`${formatDate(range.from)} – ${formatDate(range.to)}, in ${currency}. Invoices and credit notes by their date, approved bills by their bill date.`"
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
            placeholder="Last month"
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <ReportFigure
                label="Output tax (on sales)"
                :value="money(report.totals.output_tax)"
                hint="Including further tax, after credit notes"
            />
            <ReportFigure
                label="Input tax (on purchases)"
                :value="money(report.totals.input_tax)"
                hint="Claimable tax on approved bills"
            />
            <ReportFigure
                :label="
                    net >= 0 ? 'Balance to pay' : 'Balance to carry forward'
                "
                :value="money(Math.abs(net))"
                :tone="net > 0 ? 'bad' : 'good'"
                hint="Output tax minus input tax, all authorities together"
            />
        </div>

        <Card v-if="report.authorities.length">
            <CardHeader>
                <CardTitle>Per tax authority</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Authority</TableHead>
                                <TableHead class="text-right"
                                    >Output tax</TableHead
                                >
                                <TableHead class="text-right"
                                    >Input tax</TableHead
                                >
                                <TableHead class="text-right"
                                    >Balance</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="authority in report.authorities"
                                :key="authority.authority"
                            >
                                <TableCell>{{ authority.label }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    money(authority.output_tax)
                                }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{
                                    money(authority.input_tax)
                                }}</TableCell>
                                <TableCell
                                    class="text-right font-medium tabular-nums"
                                    >{{ money(authority.net) }}</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    Every authority has its own return; whether input tax of one
                    can be set off against another depends on the rules of that
                    authority.
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Output tax (sales)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tax</TableHead>
                                    <TableHead class="text-right"
                                        >Taxable value</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Tax</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="line in report.output"
                                    :key="`${line.authority}-${line.rate}-${line.name}`"
                                >
                                    <TableCell>
                                        {{ line.name }}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ line.authority_label }}
                                        </p>
                                    </TableCell>
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ money(line.net) }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ money(line.tax) }}</TableCell
                                    >
                                </TableRow>
                                <TableRow
                                    v-if="Number(report.further_tax) !== 0"
                                >
                                    <TableCell>
                                        Further tax
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Supplies to unregistered buyers
                                        </p>
                                    </TableCell>
                                    <TableCell />
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{
                                            money(report.further_tax)
                                        }}</TableCell
                                    >
                                </TableRow>
                                <TableRow
                                    v-if="
                                        !report.output.length &&
                                        Number(report.further_tax) === 0
                                    "
                                >
                                    <TableCell
                                        colspan="3"
                                        class="text-center text-muted-foreground"
                                        >No sales in this period.</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Input tax (purchases)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tax</TableHead>
                                    <TableHead class="text-right"
                                        >Taxable value</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Tax</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="line in report.input"
                                    :key="`${line.authority}-${line.rate}-${line.name}`"
                                >
                                    <TableCell>
                                        {{ line.name }}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ line.authority_label }}
                                        </p>
                                    </TableCell>
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ money(line.net) }}</TableCell
                                    >
                                    <TableCell
                                        class="text-right tabular-nums"
                                        >{{ money(line.tax) }}</TableCell
                                    >
                                </TableRow>
                                <TableRow v-if="!report.input.length">
                                    <TableCell
                                        colspan="3"
                                        class="text-center text-muted-foreground"
                                        >No claimable tax on bills in this
                                        period.</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <p
                        v-if="Number(report.input_not_claimable) !== 0"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Not claimable (marked on the bills):
                        {{ money(report.input_not_claimable) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Withheld by your customers</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="text-muted-foreground">Income tax</span>
                        <span class="tabular-nums">{{
                            money(report.withheld_by_customers.income_tax)
                        }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="text-muted-foreground">Sales tax</span>
                        <span class="tabular-nums">{{
                            money(report.withheld_by_customers.sales_tax)
                        }}</span>
                    </div>
                    <p class="pt-2 text-xs text-muted-foreground">
                        On payments received in this period. Collect the
                        withholding certificates from these customers.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Withheld from your suppliers</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="text-muted-foreground">Income tax</span>
                        <span class="tabular-nums">{{
                            money(report.withheld_from_suppliers.income_tax)
                        }}</span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span class="text-muted-foreground">Sales tax</span>
                        <span class="tabular-nums">{{
                            money(report.withheld_from_suppliers.sales_tax)
                        }}</span>
                    </div>
                    <p class="pt-2 text-xs text-muted-foreground">
                        On approved bills of this period. As withholding agent
                        you deposit this with the tax authority.
                    </p>
                </CardContent>
            </Card>
        </div>

        <p class="text-xs text-muted-foreground">
            These figures help you prepare your returns; check them before you
            file.
        </p>
    </div>
</template>
