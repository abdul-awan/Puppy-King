<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { HandCoins, IdCard, Play, TriangleAlert } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import Pill from '@/components/app/Pill.vue';
import StartPayrollDialog from '@/components/payroll/StartPayrollDialog.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { index as employeesIndex } from '@/routes/employees';
import { index, show } from '@/routes/payroll';
import { edit as payrollSettings } from '@/routes/settings/payroll';
import type { Paginated, PayrollRun, Team } from '@/types';

defineProps<{
    runs: Paginated<PayrollRun>;
    summary: {
        employees: number;
        tax_year: number;
        tax_year_label: string;
        gross: string;
        income_tax: string;
        net: string;
        employer: string;
    };
    nextMonth: string;
    existingMonths: string[];
    payDay: number;
    taxTable: { tax_year: number; table_year: number | null };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Payroll', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit, canView } = usePermissions();
const starting = ref(false);

const money = (value: string | number) => formatMoney(value, currency.value);
</script>

<template>
    <Head title="Payroll" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Payroll"
            description="Pay your employees every month: payslips with allowances, deductions, EOBI and the income tax on salaries."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="employeesIndex(slug).url"
                        ><IdCard /> Employees</Link
                    >
                </Button>
                <Button
                    v-if="canEdit('payroll')"
                    size="sm"
                    @click="starting = true"
                >
                    <Play /> Run payroll
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="taxTable.table_year !== taxTable.tax_year"
            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <p>
                <template v-if="taxTable.table_year === null">
                    There are no income tax rates for salaries yet, so no tax is
                    deducted.
                </template>
                <template v-else>
                    There are no income tax rates for tax year
                    {{ taxTable.tax_year }} yet: payslips use the rates of tax
                    year {{ taxTable.table_year }}.
                </template>
                Check the salary slabs of the latest budget (Finance Act) and
                add them
                <Link
                    v-if="canView('settings')"
                    :href="payrollSettings(slug).url"
                    class="font-medium underline"
                    >in Company settings → Payroll</Link
                ><template v-else>in Company settings → Payroll</template>.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure
                label="Employees"
                :value="String(summary.employees)"
                hint="Working here now"
            />
            <ReportFigure
                label="Gross salaries"
                :value="money(summary.gross)"
                :hint="summary.tax_year_label"
            />
            <ReportFigure
                label="Income tax deducted"
                :value="money(summary.income_tax)"
                hint="To deposit with FBR (section 149)"
            />
            <ReportFigure
                label="Net salaries"
                :value="money(summary.net)"
                :hint="
                    Number(summary.employer)
                        ? `+ ${money(summary.employer)} employer contributions`
                        : 'Approved payrolls this tax year'
                "
            />
        </div>

        <EmptyState
            v-if="runs.data.length === 0"
            :icon="HandCoins"
            title="No payrolls yet"
            description="Add your employees, then run the payroll of a month to make their payslips."
        >
            <Button
                v-if="canEdit('payroll')"
                size="sm"
                @click="starting = true"
            >
                <Play /> Run payroll
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Month</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="hidden text-right sm:table-cell"
                            >Employees</TableHead
                        >
                        <TableHead class="hidden text-right md:table-cell"
                            >Gross</TableHead
                        >
                        <TableHead class="hidden text-right lg:table-cell"
                            >Income tax</TableHead
                        >
                        <TableHead class="text-right">Net pay</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="run in runs.data" :key="run.id">
                        <TableCell>
                            <Link
                                :href="show([slug, run.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ run.label }}
                            </Link>
                            <p
                                v-if="run.pay_date"
                                class="text-xs text-muted-foreground"
                            >
                                Pay date {{ formatDate(run.pay_date) }}
                            </p>
                        </TableCell>
                        <TableCell>
                            <Pill :color="run.status_color">{{
                                run.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm sm:table-cell"
                            >{{ run.employees_count }}</TableCell
                        >
                        <TableCell
                            class="hidden text-right text-sm tabular-nums md:table-cell"
                            >{{ money(run.gross) }}</TableCell
                        >
                        <TableCell
                            class="hidden text-right text-sm tabular-nums lg:table-cell"
                            >{{ money(run.income_tax) }}</TableCell
                        >
                        <TableCell
                            class="text-right text-sm font-medium tabular-nums"
                            >{{ money(run.net) }}</TableCell
                        >
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="runs.meta" :links="runs.links" />

        <StartPayrollDialog
            v-model:open="starting"
            :next-month="nextMonth"
            :existing-months="existingMonths"
            :pay-day="payDay"
        />
    </div>
</template>
