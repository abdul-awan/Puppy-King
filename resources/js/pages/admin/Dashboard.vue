<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Users } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import BarChart from '@/components/reports/BarChart.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatMonth } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import {
    index as companiesIndex,
    show as showCompany,
} from '@/routes/admin/companies';
import { index as usersIndex, show as showUser } from '@/routes/admin/users';

const props = defineProps<{
    stats: {
        companies: number;
        companies_new: number;
        companies_suspended: number;
        companies_active: number;
        users: number;
        users_new: number;
        users_unverified: number;
        admins: number;
        invoices_month: number;
    };
    signups: { month: string; companies: number; users: number }[];
    recentCompanies: {
        slug: string;
        name: string;
        members_count: number;
        suspended: boolean;
        created_at: string | null;
    }[];
    recentUsers: {
        id: number;
        name: string;
        email: string;
        verified: boolean;
        created_at: string | null;
    }[];
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Platform admin', href: dashboard() }],
    }),
});

const companyChart = computed(() =>
    props.signups.map((month) => ({
        key: month.month,
        label: formatMonth(month.month, true),
        value: month.companies,
    })),
);

const userChart = computed(() =>
    props.signups.map((month) => ({
        key: month.month,
        label: formatMonth(month.month, true),
        value: month.users,
    })),
);
</script>

<template>
    <Head title="Platform admin" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Platform administration"
            description="Every company and user of the platform."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="companiesIndex().url"
                        ><Building2 /> Companies</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="usersIndex().url"><Users /> Users</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <ReportFigure
                label="Companies"
                :value="String(stats.companies)"
                :hint="`${stats.companies_new} new this month`"
            />
            <ReportFigure
                label="Active in the last 30 days"
                :value="String(stats.companies_active)"
                :hint="`${stats.companies_suspended} suspended`"
            />
            <ReportFigure
                label="Users"
                :value="String(stats.users)"
                :hint="`${stats.users_new} new this month · ${stats.users_unverified} unconfirmed`"
            />
            <ReportFigure
                label="Platform administrators"
                :value="String(stats.admins)"
            />
            <ReportFigure
                label="Invoices issued this month"
                :value="String(stats.invoices_month)"
                hint="By all companies together"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>New companies per month</CardTitle>
                </CardHeader>
                <CardContent>
                    <BarChart
                        :items="companyChart"
                        label="New companies per month"
                    />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>New users per month</CardTitle>
                </CardHeader>
                <CardContent>
                    <BarChart :items="userChart" label="New users per month" />
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle>Newest companies</CardTitle>
                    <Link
                        :href="companiesIndex().url"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        >All companies</Link
                    >
                </CardHeader>
                <CardContent>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="company in recentCompanies"
                            :key="company.slug"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="showCompany(company.slug).url"
                                    class="font-medium hover:underline"
                                    >{{ company.name }}</Link
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ company.members_count }} member{{
                                        company.members_count === 1 ? '' : 's'
                                    }}
                                    · since
                                    {{ formatDate(company.created_at) }}</span
                                >
                            </span>
                            <Pill v-if="company.suspended" color="red"
                                >Suspended</Pill
                            >
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between gap-2 space-y-0"
                >
                    <CardTitle>Newest users</CardTitle>
                    <Link
                        :href="usersIndex().url"
                        class="text-sm text-muted-foreground hover:text-foreground"
                        >All users</Link
                    >
                </CardHeader>
                <CardContent>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="user in recentUsers"
                            :key="user.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="showUser(user.id).url"
                                    class="font-medium hover:underline"
                                    >{{ user.name }}</Link
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ user.email }} · since
                                    {{ formatDate(user.created_at) }}</span
                                >
                            </span>
                            <Pill v-if="!user.verified" color="amber"
                                >Unconfirmed</Pill
                            >
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
