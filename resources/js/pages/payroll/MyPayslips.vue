<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, FileText, ScrollText } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import Pill from '@/components/app/Pill.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { index, pdf } from '@/routes/my-payslips';
import type { Paginated, Payslip, Team } from '@/types';

defineProps<{
    payslips: Paginated<Payslip>;
    employees: { name: string; code: string }[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'My payslips', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const money = (value: string | number) => formatMoney(value, currency.value);
</script>

<template>
    <Head title="My payslips" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="My payslips"
            :description="
                employees.length
                    ? `Your salary slips as ${employees.map((employee) => `${employee.name} (${employee.code})`).join(', ')}.`
                    : 'Your salary slips.'
            "
        />

        <EmptyState
            v-if="payslips.data.length === 0"
            :icon="ScrollText"
            title="No payslips yet"
            description="Your payslips appear here once the payroll of a month is approved."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Month</TableHead>
                        <TableHead class="hidden text-right sm:table-cell"
                            >Gross</TableHead
                        >
                        <TableHead class="hidden text-right md:table-cell"
                            >Deductions</TableHead
                        >
                        <TableHead class="text-right">Net pay</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="w-24" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="payslip in payslips.data"
                        :key="payslip.id"
                    >
                        <TableCell>
                            <p class="font-medium">
                                {{ payslip.period_label }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ payslip.number }}
                            </p>
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm tabular-nums sm:table-cell"
                            >{{ money(payslip.gross) }}</TableCell
                        >
                        <TableCell
                            class="hidden text-right text-sm tabular-nums md:table-cell"
                            >{{ money(payslip.deductions) }}</TableCell
                        >
                        <TableCell
                            class="text-right text-sm font-semibold tabular-nums"
                            >{{ money(payslip.net) }}</TableCell
                        >
                        <TableCell>
                            <Pill :color="payslip.paid_on ? 'green' : 'blue'">
                                {{
                                    payslip.paid_on
                                        ? `Paid ${formatDate(payslip.paid_on)}`
                                        : 'To be paid'
                                }}
                            </Pill>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <a
                                    :href="pdf([slug, payslip.id]).url"
                                    target="_blank"
                                    rel="noopener"
                                    title="Open"
                                    ><FileText
                                /></a>
                            </Button>
                            <Button variant="ghost" size="icon-sm" as-child>
                                <a
                                    :href="
                                        pdf([slug, payslip.id], {
                                            query: { download: 1 },
                                        }).url
                                    "
                                    title="Download"
                                    ><Download
                                /></a>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="payslips.meta" :links="payslips.links" />
    </div>
</template>
