<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    ChartColumn,
    Clock,
    Coins,
    FileText,
    HandCoins,
    Handshake,
    Landmark,
    ReceiptText,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useTeam } from '@/composables/useTeam';
import {
    dailyExpenses,
    expenses,
    index,
    payroll,
    pipeline,
    receivables,
    revenue,
    salesTax,
    time,
} from '@/routes/reports';
import type { Team } from '@/types';

const props = defineProps<{
    reports: string[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Reports', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();

type Report = {
    key: string;
    title: string;
    description: string;
    icon: LucideIcon;
    href: string;
};

const cards = computed<Report[]>(() =>
    [
        {
            key: 'revenue',
            title: 'Revenue',
            description:
                'Turnover per month, customer and product, after credit notes.',
            icon: ReceiptText,
            href: revenue(slug.value).url,
        },
        {
            key: 'receivables',
            title: 'Receivables',
            description:
                'What customers still owe you, by how long it is overdue.',
            icon: FileText,
            href: receivables(slug.value).url,
        },
        {
            key: 'sales-tax',
            title: 'Sales tax',
            description:
                'Output and input tax per tax authority, further tax and withholding, for your returns.',
            icon: Landmark,
            href: salesTax(slug.value).url,
        },
        {
            key: 'pipeline',
            title: 'Sales pipeline',
            description:
                'Open deals per phase, deals won and lost, win rate and lost reasons.',
            icon: Handshake,
            href: pipeline(slug.value).url,
        },
        {
            key: 'time',
            title: 'Time',
            description:
                'Hours per person, project, customer or work type, and what is not invoiced yet.',
            icon: Clock,
            href: time(slug.value).url,
        },
        {
            key: 'expenses',
            title: 'Expenses',
            description: 'Approved bills per category and supplier.',
            icon: Wallet,
            href: expenses(slug.value).url,
        },
        {
            key: 'daily-expenses',
            title: 'Daily expenses',
            description:
                'Day-to-day spending per category, way of paying, person and day.',
            icon: Coins,
            href: dailyExpenses(slug.value).url,
        },
        {
            key: 'payroll',
            title: 'Payroll',
            description:
                'Salaries, income tax withheld and EOBI per month and per employee.',
            icon: HandCoins,
            href: payroll(slug.value).url,
        },
    ].filter((report) => props.reports.includes(report.key)),
);
</script>

<template>
    <Head title="Reports" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Reports"
            description="Insight in your figures. Amounts are in your company's currency."
        />

        <EmptyState
            v-if="cards.length === 0"
            :icon="ChartColumn"
            title="No reports for you"
            description="Reports show the figures of modules you have access to. Ask an owner of your company for access."
        />

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="report in cards"
                :key="report.key"
                :href="report.href"
                class="block"
            >
                <Card class="h-full transition-colors hover:bg-muted/40">
                    <CardContent class="flex gap-4 pt-6">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <component :is="report.icon" class="size-5" />
                        </div>
                        <div>
                            <p class="font-medium">{{ report.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ report.description }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
