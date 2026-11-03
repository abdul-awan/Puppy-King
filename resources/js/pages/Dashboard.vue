<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    CalendarDays,
    ClipboardPen,
    Clock,
    Coins,
    FileText,
    HandCoins,
    Handshake,
    LifeBuoy,
    ReceiptText,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import Pill from '@/components/app/Pill.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import BarChart from '@/components/reports/BarChart.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import {
    formatDate,
    formatDuration,
    formatMoney,
    formatMonth,
    formatTime,
} from '@/lib/format';
import { dashboard } from '@/routes';
import { index as calendarIndex } from '@/routes/calendar';
import { index as dailyExpensesIndex } from '@/routes/daily-expenses';
import { index as dealsIndex, show as showDeal } from '@/routes/deals';
import { index as expensesIndex } from '@/routes/expenses';
import { index as invoicesIndex, show as showInvoice } from '@/routes/invoices';
import { index as payrollIndex, show as showPayroll } from '@/routes/payroll';
import { show as showProject } from '@/routes/projects';
import {
    index as quotationsIndex,
    show as showQuotation,
} from '@/routes/quotations';
import { revenue as revenueReport } from '@/routes/reports';
import { index as tasksIndex } from '@/routes/tasks';
import { index as ticketsIndex, show as showTicket } from '@/routes/tickets';
import { index as timeIndex } from '@/routes/time';
import {
    index as workOrdersIndex,
    show as showWorkOrder,
} from '@/routes/work-orders';
import type { DashboardInvitation, Team } from '@/types';

type MonthRevenue = {
    month: string;
    invoiced: string;
    credited: string;
    net: string;
};

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    currency?: string;
    sales?: {
        this_month: string;
        last_month: string;
        fiscal_year: string;
        fiscal_year_label: string;
        months: MonthRevenue[];
        outstanding: string;
        outstanding_count: number;
        overdue: string;
        overdue_count: number;
        overdue_invoices: {
            id: number;
            number: string | null;
            customer: string;
            balance: string;
            currency: string;
            days_overdue: number;
        }[];
    } | null;
    pipeline?: {
        open_count: number;
        open_value: string;
        weighted: string;
        won_count: number;
        won_value: string;
        closing: {
            id: number;
            title: string;
            company: string | null;
            value: string;
            currency: string;
            expected_close_date: string | null;
        }[];
    } | null;
    quotations?: {
        count: number;
        value: string;
        items: {
            id: number;
            number: string | null;
            customer: string;
            total: string;
            currency: string;
            valid_until: string | null;
        }[];
    } | null;
    bills?: { count: number; total: string; overdue_count: number } | null;
    dailyExpenses?: {
        today: string;
        month: string;
        cash_in_hand: string | null;
    } | null;
    payroll?: {
        employees: number;
        latest: {
            id: number;
            label: string;
            status: string;
            status_label: string;
            net: string;
        } | null;
    } | null;
    time?: { today: number; week: number; week_billable: number } | null;
    tasks?: {
        count: number;
        items: {
            id: number;
            title: string;
            project: { id: number; title: string } | null;
            due_date: string | null;
            is_overdue: boolean;
            priority: string;
            priority_label: string;
            priority_color: string;
        }[];
    } | null;
    events?:
        | {
              id: number;
              title: string;
              type: string;
              type_label: string;
              color: string;
              starts_at: string;
              ends_at: string;
              all_day: boolean;
              location: string | null;
          }[]
        | null;
    tickets?: {
        count: number;
        unassigned: number;
        items: {
            id: number;
            number: string | null;
            subject: string;
            customer: string;
            status_label: string;
            status_color: string;
        }[];
    } | null;
    workOrders?: {
        count: number;
        items: {
            id: number;
            number: string | null;
            title: string;
            customer: string;
            date: string;
        }[];
    } | null;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const { slug } = useTeam();
const { canView } = usePermissions();

const money = (value: string | number) =>
    formatMoney(value, props.currency ?? 'PKR');

const firstName = computed(
    () => page.props.auth.user?.name?.split(' ')[0] ?? '',
);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 18
          ? 'Good afternoon'
          : 'Good evening';
});

const todayLabel = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

type Figure = {
    key: string;
    label: string;
    icon: LucideIcon;
    value: string;
    hint: string;
    href: string;
    alert?: boolean;
};

const figures = computed(() => {
    const items: Figure[] = [];

    if (props.sales) {
        items.push({
            key: 'revenue',
            label: 'Revenue this month',
            icon: ReceiptText,
            value: money(props.sales.this_month),
            hint: `Last month ${money(props.sales.last_month)}`,
            href: invoicesIndex(slug.value).url,
        });
        items.push({
            key: 'outstanding',
            label: 'Customers owe you',
            icon: FileText,
            value: money(props.sales.outstanding),
            hint: props.sales.overdue_count
                ? `${money(props.sales.overdue)} overdue (${props.sales.overdue_count} invoices)`
                : `${props.sales.outstanding_count} open invoices, none overdue`,
            href: invoicesIndex(slug.value, {
                query: {
                    status: props.sales.overdue_count ? 'overdue' : 'open',
                },
            }).url,
            alert: props.sales.overdue_count > 0,
        });
    }

    if (props.pipeline) {
        items.push({
            key: 'pipeline',
            label: 'Open deals',
            icon: Handshake,
            value: money(props.pipeline.open_value),
            hint: `${props.pipeline.open_count} deals · weighted ${money(props.pipeline.weighted)}`,
            href: dealsIndex(slug.value).url,
        });
    }

    if (props.bills) {
        items.push({
            key: 'bills',
            label: 'Bills to pay',
            icon: Wallet,
            value: money(props.bills.total),
            hint: props.bills.overdue_count
                ? `${props.bills.overdue_count} overdue`
                : `${props.bills.count} bills, none overdue`,
            href: expensesIndex(slug.value).url,
            alert: props.bills.overdue_count > 0,
        });
    }

    if (props.dailyExpenses) {
        const cash = props.dailyExpenses.cash_in_hand;

        items.push({
            key: 'daily-expenses',
            label: 'Spent today',
            icon: Coins,
            value: money(props.dailyExpenses.today),
            hint:
                `This month ${money(props.dailyExpenses.month)}` +
                (cash !== null ? ` · cash in hand ${money(cash)}` : ''),
            href: dailyExpensesIndex(slug.value).url,
            alert: cash !== null && Number(cash) < 0,
        });
    }

    if (props.payroll) {
        const latest = props.payroll.latest;

        items.push({
            key: 'payroll',
            label: latest ? `Payroll ${latest.label}` : 'Payroll',
            icon: HandCoins,
            value: latest ? money(latest.net) : '—',
            hint: latest
                ? `${latest.status_label} · ${props.payroll.employees} employees`
                : `${props.payroll.employees} employees · no payroll yet`,
            href: latest
                ? showPayroll([slug.value, latest.id]).url
                : payrollIndex(slug.value).url,
            alert: latest?.status === 'draft',
        });
    }

    if (props.time) {
        items.push({
            key: 'time',
            label: 'Your hours this week',
            icon: Clock,
            value: formatDuration(props.time.week),
            hint: `${formatDuration(props.time.week_billable)} billable · today ${formatDuration(props.time.today)}`,
            href: timeIndex(slug.value).url,
        });
    }

    return items;
});

const chart = computed(() =>
    (props.sales?.months ?? []).map((month) => ({
        key: month.month,
        label: formatMonth(month.month, true),
        value: Number(month.net),
    })),
);

const showToday = computed(() => Boolean(props.events || props.tasks));

const hasAnything = computed(
    () =>
        figures.value.length > 0 ||
        props.tasks ||
        props.events ||
        props.tickets ||
        props.workOrders ||
        props.quotations,
);
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div class="space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-semibold">
                {{ greeting }}{{ firstName ? `, ${firstName}` : '' }}
            </h1>
            <p class="text-sm text-muted-foreground">{{ todayLabel }}</p>
        </div>

        <div
            v-if="figures.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
        >
            <Link
                v-for="figure in figures"
                :key="figure.key"
                :href="figure.href"
                class="block"
            >
                <Card class="h-full transition-colors hover:bg-muted/40">
                    <CardContent class="pt-6">
                        <p
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <component :is="figure.icon" class="size-4" />
                            {{ figure.label }}
                        </p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">
                            {{ figure.value }}
                        </p>
                        <p
                            class="mt-1 text-xs"
                            :class="
                                figure.alert
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ figure.hint }}
                        </p>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card v-if="sales" class="lg:col-span-2">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle>Revenue in the last 12 months</CardTitle>
                    <Link
                        v-if="canView('reports')"
                        :href="revenueReport(slug).url"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        >Revenue report</Link
                    >
                </CardHeader>
                <CardContent>
                    <BarChart
                        :items="chart"
                        label="Revenue per month"
                        :format="money"
                    />
                    <p class="mt-3 text-xs text-muted-foreground">
                        Excluding sales tax, after credit notes ·
                        {{ sales.fiscal_year_label }} so far:
                        {{ money(sales.fiscal_year) }}
                    </p>
                </CardContent>
            </Card>

            <Card v-if="showToday" :class="{ 'lg:col-span-3': !sales }">
                <CardHeader>
                    <CardTitle>Today</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div v-if="events">
                        <p
                            class="mb-2 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            <CalendarDays class="size-3.5" /> Calendar
                        </p>
                        <p
                            v-if="!events.length"
                            class="text-sm text-muted-foreground"
                        >
                            Nothing planned.
                        </p>
                        <ul v-else class="space-y-2 text-sm">
                            <li
                                v-for="event in events"
                                :key="event.id"
                                class="flex gap-3"
                            >
                                <span
                                    class="w-12 shrink-0 text-muted-foreground tabular-nums"
                                    >{{
                                        event.all_day
                                            ? 'All day'
                                            : formatTime(event.starts_at)
                                    }}</span
                                >
                                <span class="min-w-0">
                                    <Link
                                        :href="calendarIndex(slug).url"
                                        class="font-medium hover:underline"
                                        >{{ event.title }}</Link
                                    >
                                    <span class="text-muted-foreground">
                                        · {{ event.type_label
                                        }}<template v-if="event.location">
                                            · {{ event.location }}</template
                                        ></span
                                    >
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="tasks">
                        <p
                            class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Your tasks ({{ tasks.count }})
                        </p>
                        <p
                            v-if="!tasks.items.length"
                            class="text-sm text-muted-foreground"
                        >
                            Nothing due today.
                        </p>
                        <ul v-else class="space-y-2 text-sm">
                            <li
                                v-for="task in tasks.items"
                                :key="task.id"
                                class="flex items-start justify-between gap-2"
                            >
                                <span class="min-w-0">
                                    <Link
                                        :href="
                                            task.project
                                                ? showProject([
                                                      slug,
                                                      task.project.id,
                                                  ]).url
                                                : tasksIndex(slug).url
                                        "
                                        class="font-medium hover:underline"
                                        >{{ task.title }}</Link
                                    >
                                    <span
                                        v-if="task.project"
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ task.project.title }}</span
                                    >
                                </span>
                                <span
                                    class="shrink-0 text-xs"
                                    :class="
                                        task.is_overdue
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-muted-foreground'
                                    "
                                    >{{
                                        task.due_date
                                            ? formatDate(task.due_date)
                                            : 'Planned today'
                                    }}</span
                                >
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <Card v-if="sales">
                <CardHeader>
                    <CardTitle>Overdue invoices</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!sales.overdue_invoices.length"
                        class="text-sm text-muted-foreground"
                    >
                        No overdue invoices.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="invoice in sales.overdue_invoices"
                            :key="invoice.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="showInvoice([slug, invoice.id]).url"
                                    class="font-medium hover:underline"
                                    >{{ invoice.number }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ invoice.customer }} ·
                                    {{ invoice.days_overdue }} days late</span
                                >
                            </span>
                            <span class="shrink-0 tabular-nums">{{
                                formatMoney(invoice.balance, invoice.currency)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="quotations">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle>Waiting for an answer</CardTitle>
                    <Link
                        :href="quotationsIndex(slug).url"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        >{{ quotations.count }} ·
                        {{ money(quotations.value) }}</Link
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!quotations.items.length"
                        class="text-sm text-muted-foreground"
                    >
                        No quotations waiting for the customer.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="quotation in quotations.items"
                            :key="quotation.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="
                                        showQuotation([slug, quotation.id]).url
                                    "
                                    class="font-medium hover:underline"
                                    >{{ quotation.number }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ quotation.customer
                                    }}<template v-if="quotation.valid_until">
                                        · valid until
                                        {{
                                            formatDate(quotation.valid_until)
                                        }}</template
                                    ></span
                                >
                            </span>
                            <span class="shrink-0 tabular-nums">{{
                                formatMoney(quotation.total, quotation.currency)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="pipeline">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle>Deals to close this month</CardTitle>
                    <span class="text-sm text-muted-foreground"
                        >Won: {{ pipeline.won_count }}</span
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!pipeline.closing.length"
                        class="text-sm text-muted-foreground"
                    >
                        No deals expected to close this month.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="deal in pipeline.closing"
                            :key="deal.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="showDeal([slug, deal.id]).url"
                                    class="font-medium hover:underline"
                                    >{{ deal.title }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ deal.company ?? '—' }} ·
                                    {{
                                        formatDate(deal.expected_close_date)
                                    }}</span
                                >
                            </span>
                            <span class="shrink-0 tabular-nums">{{
                                formatMoney(deal.value, deal.currency)
                            }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="tickets">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle class="flex items-center gap-2"
                        ><LifeBuoy class="size-4" /> Your tickets</CardTitle
                    >
                    <Link
                        :href="ticketsIndex(slug).url"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        >{{ tickets.unassigned }} new, unassigned</Link
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!tickets.items.length"
                        class="text-sm text-muted-foreground"
                    >
                        No open tickets for you.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="ticket in tickets.items"
                            :key="ticket.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="showTicket([slug, ticket.id]).url"
                                    class="font-medium hover:underline"
                                    >{{ ticket.subject }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ ticket.number }} ·
                                    {{ ticket.customer }}</span
                                >
                            </span>
                            <Pill :color="ticket.status_color">{{
                                ticket.status_label
                            }}</Pill>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="workOrders">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle class="flex items-center gap-2"
                        ><ClipboardPen class="size-4" /> Your work
                        orders</CardTitle
                    >
                    <Link
                        :href="workOrdersIndex(slug).url"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        >{{ workOrders.count }} open</Link
                    >
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!workOrders.items.length"
                        class="text-sm text-muted-foreground"
                    >
                        No open work orders for you.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="workOrder in workOrders.items"
                            :key="workOrder.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="
                                        showWorkOrder([slug, workOrder.id]).url
                                    "
                                    class="font-medium hover:underline"
                                    >{{ workOrder.title }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ workOrder.number }} ·
                                    {{ workOrder.customer }}</span
                                >
                            </span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                                >{{ formatDate(workOrder.date) }}</span
                            >
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Card v-if="!hasAnything">
            <CardContent
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Welcome! Your dashboard fills up as your team starts working
                with customers, invoices and projects.
            </CardContent>
        </Card>
    </div>
</template>
