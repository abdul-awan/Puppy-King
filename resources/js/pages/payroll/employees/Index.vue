<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, HandCoins, IdCard, Plus } from '@lucide/vue';
import { computed } from 'vue';
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
import { create, exportMethod, index, show } from '@/routes/employees';
import { index as payrollIndex } from '@/routes/payroll';
import type { Employee, Paginated, SalaryEstimate, Team } from '@/types';

const props = defineProps<{
    employees: Paginated<Employee>;
    estimates: Record<string, SalaryEstimate>;
    filters: { search: string; status: string; department: string | null };
    departments: string[];
    summary: {
        current: number;
        monthly_gross: string;
        monthly_cost: string;
        left: number;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Employees', href: index(props.currentTeam.slug) },
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
    { value: 'current', label: 'Working here' },
    { value: 'left', label: 'Left' },
    { value: 'all', label: 'Everyone' },
];

const departmentOptions = computed(() =>
    props.departments.map((department) => ({
        value: department,
        label: department,
    })),
);

const exportUrl = computed(() => {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== undefined && value !== '') {
            query[key] = String(value);
        }
    }

    return exportMethod(slug.value, { query }).url;
});
</script>

<template>
    <Head title="Employees" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Employees"
            description="The people on your payroll, with their salary, tax and bank details."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="payrollIndex(slug).url"
                        ><HandCoins /> Payroll</Link
                    >
                </Button>
                <Button v-if="canEdit('payroll')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New employee</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Employees</p>
                    <p class="text-2xl font-semibold">{{ summary.current }}</p>
                    <p class="text-xs text-muted-foreground">
                        Working here now · {{ summary.left }} left
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Gross salaries a month
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatMoney(summary.monthly_gross, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Basic salaries and allowances
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Cost to the company a month
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatMoney(summary.monthly_cost, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        With EOBI and other employer contributions
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search name, code, CNIC, phone…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statuses"
                class="lg:w-44"
            />
            <SelectInput
                v-if="departments.length"
                v-model="filters.department"
                :options="departmentOptions"
                placeholder="All departments"
                class="lg:w-52"
            />
        </div>

        <EmptyState
            v-if="employees.data.length === 0"
            :icon="IdCard"
            title="No employees found"
            description="Add your employees with their salary to run the payroll and print payslips."
        >
            <Button
                v-if="canEdit('payroll') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"><Plus /> New employee</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Employee</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Department</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Joined</TableHead
                        >
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Gross a month</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="employee in employees.data"
                        :key="employee.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, employee.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ employee.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ employee.code }}
                                <template v-if="employee.designation">
                                    · {{ employee.designation }}
                                </template>
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            employee.department ?? '—'
                        }}</TableCell>
                        <TableCell class="hidden text-sm lg:table-cell">{{
                            formatDate(employee.joining_date)
                        }}</TableCell>
                        <TableCell>
                            <Pill :color="employee.status_color">{{
                                employee.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap tabular-nums"
                        >
                            {{
                                formatMoney(
                                    estimates[employee.id]?.gross ??
                                        employee.basic_salary,
                                    currency,
                                )
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="employees.meta" :links="employees.links" />
    </div>
</template>
