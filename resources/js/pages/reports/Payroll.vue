<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import Pill from '@/components/app/Pill.vue';
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
import { formatDate, formatMoney, formatMonth } from '@/lib/format';
import { show as showEmployee } from '@/routes/employees';
import { show as showRun } from '@/routes/payroll';
import { exportMethod, index, payroll } from '@/routes/reports';
import type { Option, Team } from '@/types';

const props = defineProps<{
    filters: { period: string | null; from: string | null; to: string | null };
    periods: Option[];
    range: { from: string; to: string };
    currency: string;
    report: {
        months: {
            id: number;
            month: string;
            label: string;
            status_label: string;
            status_color: string;
            employees: number;
            gross: string;
            income_tax: string;
            deductions: string;
            net: string;
            employer_cost: string;
        }[];
        employees: {
            id: number;
            name: string;
            code: string;
            payslips: number;
            gross: string;
            taxable: string;
            income_tax: string;
            deductions: string;
            net: string;
        }[];
        totals: {
            payslips: number;
            gross: string;
            income_tax: string;
            deductions: string;
            net: string;
            employer_cost: string;
            eobi_employee: string;
            eobi_employer: string;
        };
        drafts: number;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Payroll', href: payroll(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => payroll(slug.value).url,
);

const money = (value: string | number) => formatMoney(value, props.currency);

const chart = computed(() =>
    props.report.months.map((month) => ({
        key: month.month,
        label: formatMonth(month.month, true),
        value: Number(month.net),
    })),
);

const exportUrl = computed(
    () =>
        exportMethod([slug.value, 'payroll'], {
            query: {
                period: props.filters.period ?? undefined,
                from: props.filters.from ?? undefined,
                to: props.filters.to ?? undefined,
            },
        }).url,
);
</script>

<template>
    <Head title="Payroll report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Payroll"
            :description="`Approved payrolls of ${formatDate(range.from)} – ${formatDate(range.to)}, in ${currency}.`"
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

        <p
            v-if="report.drafts"
            class="text-sm text-amber-700 dark:text-amber-400"
        >
            {{ report.drafts }}
            {{ report.drafts === 1 ? 'payroll is' : 'payrolls are' }} still a
            draft and not counted.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure
                label="Gross salaries"
                :value="money(report.totals.gross)"
                :hint="`${report.totals.payslips} payslips`"
            />
            <ReportFigure
                label="Income tax withheld"
                :value="money(report.totals.income_tax)"
                hint="Section 149, to deposit with FBR"
            />
            <ReportFigure
                label="Net salaries"
                :value="money(report.totals.net)"
                :hint="`Deductions ${money(report.totals.deductions)}`"
            />
            <ReportFigure
                label="EOBI"
                :value="
                    money(
                        Number(report.totals.eobi_employee) +
                            Number(report.totals.eobi_employer),
                    )
                "
                :hint="`${money(report.totals.eobi_employee)} employees + ${money(report.totals.eobi_employer)} employer`"
            />
        </div>

        <Card v-if="chart.length > 1">
            <CardHeader>
                <CardTitle>Net salaries per month</CardTitle>
            </CardHeader>
            <CardContent>
                <BarChart
                    :items="chart"
                    label="Net salaries per month"
                    :format="(value) => money(value)"
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Per month</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!report.months.length"
                    class="text-sm text-muted-foreground"
                >
                    No approved payrolls in this period.
                </p>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Month</TableHead>
                            <TableHead class="hidden text-right sm:table-cell"
                                >Employees</TableHead
                            >
                            <TableHead class="text-right">Gross</TableHead>
                            <TableHead class="hidden text-right md:table-cell"
                                >Income tax</TableHead
                            >
                            <TableHead class="text-right">Net</TableHead>
                            <TableHead class="hidden text-right lg:table-cell"
                                >Employer</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="month in report.months"
                            :key="month.id"
                        >
                            <TableCell>
                                <Link
                                    :href="showRun([slug, month.id]).url"
                                    class="font-medium hover:underline"
                                    >{{ month.label }}</Link
                                >
                                <Pill
                                    :color="month.status_color"
                                    class="ml-2"
                                    >{{ month.status_label }}</Pill
                                >
                            </TableCell>
                            <TableCell
                                class="hidden text-right text-sm sm:table-cell"
                                >{{ month.employees }}</TableCell
                            >
                            <TableCell
                                class="text-right text-sm tabular-nums"
                                >{{ money(month.gross) }}</TableCell
                            >
                            <TableCell
                                class="hidden text-right text-sm tabular-nums md:table-cell"
                                >{{ money(month.income_tax) }}</TableCell
                            >
                            <TableCell
                                class="text-right text-sm font-medium tabular-nums"
                                >{{ money(month.net) }}</TableCell
                            >
                            <TableCell
                                class="hidden text-right text-sm tabular-nums lg:table-cell"
                                >{{ money(month.employer_cost) }}</TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Per employee</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="!report.employees.length"
                    class="text-sm text-muted-foreground"
                >
                    No payslips in this period.
                </p>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Employee</TableHead>
                            <TableHead class="hidden text-right sm:table-cell"
                                >Months</TableHead
                            >
                            <TableHead class="text-right">Gross</TableHead>
                            <TableHead class="hidden text-right md:table-cell"
                                >Taxable</TableHead
                            >
                            <TableHead class="text-right">Income tax</TableHead>
                            <TableHead class="hidden text-right lg:table-cell"
                                >Net</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="employee in report.employees"
                            :key="employee.id"
                        >
                            <TableCell>
                                <Link
                                    :href="
                                        showEmployee([slug, employee.id]).url
                                    "
                                    class="font-medium hover:underline"
                                    >{{ employee.name }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ employee.code }}
                                </p>
                            </TableCell>
                            <TableCell
                                class="hidden text-right text-sm sm:table-cell"
                                >{{ employee.payslips }}</TableCell
                            >
                            <TableCell
                                class="text-right text-sm tabular-nums"
                                >{{ money(employee.gross) }}</TableCell
                            >
                            <TableCell
                                class="hidden text-right text-sm tabular-nums md:table-cell"
                                >{{ money(employee.taxable) }}</TableCell
                            >
                            <TableCell
                                class="text-right text-sm tabular-nums"
                                >{{ money(employee.income_tax) }}</TableCell
                            >
                            <TableCell
                                class="hidden text-right text-sm font-medium tabular-nums lg:table-cell"
                                >{{ money(employee.net) }}</TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
