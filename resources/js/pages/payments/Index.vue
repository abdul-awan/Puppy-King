<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Banknote } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
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
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { show as showInvoice } from '@/routes/invoices';
import { index } from '@/routes/payments';
import type { InvoicePayment, Option, Paginated, Team } from '@/types';

const props = defineProps<{
    payments: Paginated<InvoicePayment>;
    filters: {
        search: string;
        method: string | null;
        period: string | null;
        from: string | null;
        to: string | null;
    };
    periods: Option[];
    methods: Option[];
    totals: {
        received: string;
        income_tax_withheld: string;
        sales_tax_withheld: string;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Payments', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);
</script>

<template>
    <Head title="Payments" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Payments"
            description="Money received on invoices, with the tax your customers withheld."
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Received</p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(totals.received, currency) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Income tax withheld
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(totals.income_tax_withheld, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Claim as tax already paid in your income tax return
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Sales tax withheld
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(totals.sales_tax_withheld, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Deposited by your customers on your behalf
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search invoice, customer, reference…"
            />
            <SelectInput
                v-model="filters.method"
                :options="methods"
                placeholder="All methods"
                class="lg:w-52"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
            />
        </div>

        <EmptyState
            v-if="payments.data.length === 0"
            :icon="Banknote"
            title="No payments found"
            description="Payments you record on invoices appear here."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Invoice</TableHead>
                        <TableHead class="hidden md:table-cell">
                            Method
                        </TableHead>
                        <TableHead class="text-right">Received</TableHead>
                        <TableHead class="hidden text-right lg:table-cell">
                            Income tax
                        </TableHead>
                        <TableHead class="hidden text-right lg:table-cell">
                            Sales tax
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="payment in payments.data"
                        :key="payment.id"
                    >
                        <TableCell class="text-sm">{{
                            formatDate(payment.date)
                        }}</TableCell>
                        <TableCell>
                            <Link
                                v-if="payment.invoice"
                                :href="
                                    showInvoice([slug, payment.invoice.id]).url
                                "
                                class="font-medium hover:underline"
                            >
                                {{ payment.invoice.number }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ payment.invoice?.customer }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">
                            {{ payment.method_label }}
                            <p
                                v-if="payment.reference"
                                class="text-xs text-muted-foreground"
                            >
                                {{ payment.reference }}
                            </p>
                        </TableCell>
                        <TableCell
                            class="text-right text-sm font-medium whitespace-nowrap"
                        >
                            {{
                                formatMoney(
                                    payment.amount,
                                    payment.invoice?.currency ?? currency,
                                )
                            }}
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm whitespace-nowrap lg:table-cell"
                        >
                            {{
                                Number(payment.income_tax_withheld)
                                    ? formatMoney(
                                          payment.income_tax_withheld,
                                          payment.invoice?.currency ?? currency,
                                      )
                                    : '—'
                            }}
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm whitespace-nowrap lg:table-cell"
                        >
                            {{
                                Number(payment.sales_tax_withheld)
                                    ? formatMoney(
                                          payment.sales_tax_withheld,
                                          payment.invoice?.currency ?? currency,
                                      )
                                    : '—'
                            }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="payments.meta" :links="payments.links" />
    </div>
</template>
