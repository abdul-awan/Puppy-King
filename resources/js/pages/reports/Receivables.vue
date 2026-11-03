<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, FileText } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
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
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { show as showContact } from '@/routes/contacts';
import { index as invoicesIndex } from '@/routes/invoices';
import { exportMethod, index, receivables } from '@/routes/reports';
import type { Team } from '@/types';

type Row = {
    name: string;
    company_id: number | null;
    contact_id: number | null;
    invoices: number;
    buckets: Record<string, string>;
    total: string;
};

const props = defineProps<{
    currency: string;
    buckets: Record<string, string>;
    report: {
        rows: Row[];
        totals: Record<string, string>;
        total: string;
        overdue: string;
        invoices: number;
    };
    today: string;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
            { title: 'Receivables', href: receivables(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();

const money = (value: string | number) => formatMoney(value, props.currency);
const bucketKeys = computed(() => Object.keys(props.buckets));

const customerUrl = (row: Row) =>
    row.company_id
        ? showCompany([slug.value, row.company_id]).url
        : row.contact_id
          ? showContact([slug.value, row.contact_id]).url
          : null;
</script>

<template>
    <Head title="Receivables report" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Receivables"
            :description="`Open invoices on ${formatDate(today)}, by how long they are overdue, in ${currency}.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportMethod([slug, 'receivables']).url"
                        ><Download /> Export</a
                    >
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <ReportFigure
                label="Customers owe you"
                :value="money(report.total)"
                :hint="`${report.invoices} open invoices`"
            />
            <ReportFigure
                label="Overdue"
                :value="money(report.overdue)"
                :tone="Number(report.overdue) > 0 ? 'bad' : 'default'"
            />
            <ReportFigure
                label="Over 90 days overdue"
                :value="money(report.totals.days_over_90 ?? 0)"
                :tone="
                    Number(report.totals.days_over_90 ?? 0) > 0
                        ? 'bad'
                        : 'default'
                "
            />
        </div>

        <EmptyState
            v-if="report.rows.length === 0"
            :icon="FileText"
            title="Nothing to collect"
            description="All issued invoices are paid."
        />

        <div v-else class="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Customer</TableHead>
                        <TableHead
                            v-for="key in bucketKeys"
                            :key="key"
                            class="text-right whitespace-nowrap"
                            >{{ buckets[key] }}</TableHead
                        >
                        <TableHead class="text-right">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="row in report.rows"
                        :key="`${row.company_id}-${row.contact_id}-${row.name}`"
                    >
                        <TableCell>
                            <Link
                                v-if="customerUrl(row)"
                                :href="customerUrl(row) ?? ''"
                                class="font-medium hover:underline"
                                >{{ row.name }}</Link
                            >
                            <span v-else class="font-medium">{{
                                row.name
                            }}</span>
                            <p class="text-xs text-muted-foreground">
                                {{ row.invoices }} invoice{{
                                    row.invoices === 1 ? '' : 's'
                                }}
                            </p>
                        </TableCell>
                        <TableCell
                            v-for="key in bucketKeys"
                            :key="key"
                            class="text-right tabular-nums"
                            :class="{
                                'text-muted-foreground':
                                    Number(row.buckets[key]) === 0,
                                'text-red-600 dark:text-red-400':
                                    key !== 'not_due' &&
                                    Number(row.buckets[key]) > 0,
                            }"
                            >{{
                                Number(row.buckets[key]) === 0
                                    ? '—'
                                    : money(row.buckets[key] ?? 0)
                            }}</TableCell
                        >
                        <TableCell
                            class="text-right font-medium tabular-nums"
                            >{{ money(row.total) }}</TableCell
                        >
                    </TableRow>
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell>Total</TableCell>
                        <TableCell
                            v-for="key in bucketKeys"
                            :key="key"
                            class="text-right tabular-nums"
                            >{{ money(report.totals[key] ?? 0) }}</TableCell
                        >
                        <TableCell class="text-right tabular-nums">{{
                            money(report.total)
                        }}</TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </div>

        <p class="text-sm text-muted-foreground">
            See the invoices themselves in
            <Link
                :href="
                    invoicesIndex(slug, { query: { status: 'overdue' } }).url
                "
                class="underline"
                >overdue invoices</Link
            >.
        </p>
    </div>
</template>
