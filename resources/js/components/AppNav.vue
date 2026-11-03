<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Banknote,
    Building2,
    CalendarDays,
    ChartColumn,
    ClipboardPen,
    Clock,
    Coins,
    ContactRound,
    FileMinus,
    FileText,
    FolderKanban,
    ChartGantt,
    Handshake,
    HandCoins,
    IdCard,
    LayoutGrid,
    LifeBuoy,
    ListChecks,
    Package,
    ReceiptText,
    Repeat,
    ScrollText,
    Settings2,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as calendarIndex } from '@/routes/calendar';
import { index as companiesIndex } from '@/routes/companies';
import { index as contactsIndex } from '@/routes/contacts';
import { index as creditNotesIndex } from '@/routes/credit-notes';
import { index as dailyExpensesIndex } from '@/routes/daily-expenses';
import { index as dealsIndex } from '@/routes/deals';
import { index as employeesIndex } from '@/routes/employees';
import { index as expensesIndex } from '@/routes/expenses';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as myPayslipsIndex } from '@/routes/my-payslips';
import { index as paymentsIndex } from '@/routes/payments';
import { index as payrollIndex } from '@/routes/payroll';
import { index as planningIndex } from '@/routes/planning';
import { index as productsIndex } from '@/routes/products';
import { index as projectsIndex } from '@/routes/projects';
import { index as tasksIndex } from '@/routes/tasks';
import { index as ticketsIndex } from '@/routes/tickets';
import { index as timeIndex } from '@/routes/time';
import { index as workOrdersIndex } from '@/routes/work-orders';
import { index as quotationsIndex } from '@/routes/quotations';
import { index as recurringIndex } from '@/routes/recurring-invoices';
import { index as reportsIndex } from '@/routes/reports';
import { edit as companySettings } from '@/routes/settings/general';
import type { ModuleKey } from '@/types';

type NavLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    module?: ModuleKey;
    exact?: boolean;
    activePrefix?: string;
};

type NavGroup = {
    label: string;
    items: NavLink[];
};

const page = usePage();
const { canView } = usePermissions();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const groups = computed<NavGroup[]>(() => {
    const slug = page.props.currentTeam?.slug;

    if (!slug) {
        return [];
    }

    const all: NavGroup[] = [
        {
            label: 'Overview',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(slug).url,
                    icon: LayoutGrid,
                    exact: true,
                },
                // Employees with a login see their own payslips.
                ...(page.props.hasPayslips
                    ? [
                          {
                              title: 'My payslips',
                              href: myPayslipsIndex(slug).url,
                              icon: ScrollText,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'CRM',
            items: [
                {
                    title: 'Companies',
                    href: companiesIndex(slug).url,
                    icon: Building2,
                    module: 'crm',
                },
                {
                    title: 'Contacts',
                    href: contactsIndex(slug).url,
                    icon: ContactRound,
                    module: 'crm',
                },
            ],
        },
        {
            label: 'Sales',
            items: [
                {
                    title: 'Deals',
                    href: dealsIndex(slug).url,
                    icon: Handshake,
                    module: 'deals',
                },
                {
                    title: 'Quotations',
                    href: quotationsIndex(slug).url,
                    icon: FileText,
                    module: 'quotations',
                },
            ],
        },
        {
            label: 'Invoicing',
            items: [
                {
                    title: 'Invoices',
                    href: invoicesIndex(slug).url,
                    icon: ReceiptText,
                    module: 'invoicing',
                },
                {
                    title: 'Payments',
                    href: paymentsIndex(slug).url,
                    icon: Banknote,
                    module: 'invoicing',
                },
                {
                    title: 'Credit notes',
                    href: creditNotesIndex(slug).url,
                    icon: FileMinus,
                    module: 'invoicing',
                },
                {
                    title: 'Recurring invoices',
                    href: recurringIndex(slug).url,
                    icon: Repeat,
                    module: 'invoicing',
                },
            ],
        },
        {
            label: 'Work',
            items: [
                {
                    title: 'Projects',
                    href: projectsIndex(slug).url,
                    icon: FolderKanban,
                    module: 'projects',
                },
                {
                    title: 'Tasks',
                    href: tasksIndex(slug).url,
                    icon: ListChecks,
                    module: 'projects',
                },
                {
                    title: 'Time',
                    href: timeIndex(slug).url,
                    icon: Clock,
                    module: 'time_tracking',
                },
                {
                    title: 'Work orders',
                    href: workOrdersIndex(slug).url,
                    icon: ClipboardPen,
                    module: 'work_orders',
                },
                {
                    title: 'Calendar',
                    href: calendarIndex(slug).url,
                    icon: CalendarDays,
                    module: 'calendar',
                },
                {
                    title: 'Planning',
                    href: planningIndex(slug).url,
                    icon: ChartGantt,
                    module: 'planning',
                },
            ],
        },
        {
            label: 'Support',
            items: [
                {
                    title: 'Tickets',
                    href: ticketsIndex(slug).url,
                    icon: LifeBuoy,
                    module: 'tickets',
                },
            ],
        },
        {
            label: 'Purchases',
            items: [
                {
                    title: 'Expenses',
                    href: expensesIndex(slug).url,
                    icon: Wallet,
                    module: 'expenses',
                },
                {
                    title: 'Daily expenses',
                    href: dailyExpensesIndex(slug).url,
                    icon: Coins,
                    module: 'daily_expenses',
                },
            ],
        },
        {
            label: 'Payroll',
            items: [
                {
                    title: 'Employees',
                    href: employeesIndex(slug).url,
                    icon: IdCard,
                    module: 'payroll',
                },
                {
                    title: 'Payroll',
                    href: payrollIndex(slug).url,
                    icon: HandCoins,
                    module: 'payroll',
                },
            ],
        },
        {
            label: 'Catalog',
            items: [
                {
                    title: 'Products',
                    href: productsIndex(slug).url,
                    icon: Package,
                    module: 'products',
                },
            ],
        },
        {
            label: 'Insights',
            items: [
                {
                    title: 'Reports',
                    href: reportsIndex(slug).url,
                    icon: ChartColumn,
                    module: 'reports',
                    activePrefix: `/${slug}/reports`,
                },
            ],
        },
        {
            label: 'Administration',
            items: [
                {
                    title: 'Company settings',
                    href: companySettings(slug).url,
                    icon: Settings2,
                    module: 'settings',
                    activePrefix: `/${slug}/settings`,
                },
            ],
        },
        // The people who run the service see every company and user.
        ...(page.props.auth.isPlatformAdmin
            ? [
                  {
                      label: 'Platform',
                      items: [
                          {
                              title: 'Platform admin',
                              href: adminDashboard().url,
                              icon: ShieldCheck,
                              activePrefix: '/admin',
                          },
                      ],
                  },
              ]
            : []),
    ];

    return all
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.module || canView(item.module),
            ),
        }))
        .filter((group) => group.items.length > 0);
});

const isActive = (item: NavLink) => {
    if (item.activePrefix) {
        return isCurrentOrParentUrl(item.activePrefix);
    }

    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
};
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
