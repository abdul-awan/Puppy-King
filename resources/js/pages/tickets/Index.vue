<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LifeBuoy, MessageSquare, Plus } from '@lucide/vue';
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
import { formatRelative } from '@/lib/format';
import { create, index, show } from '@/routes/tickets';
import type { IdName, Option, Paginated, Team, Ticket } from '@/types';

const props = defineProps<{
    tickets: Paginated<Ticket>;
    filters: {
        search: string;
        status: string;
        assignee: string;
        priority: string | null;
    };
    users: IdName[];
    statuses: Option[];
    priorities: Option[];
    summary: { new: number; open: number; waiting: number; mine: number };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Tickets', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const statusOptions = [
    { value: 'open', label: 'Open tickets' },
    { value: 'all', label: 'All tickets' },
    ...props.statuses,
];

const assigneeOptions = [
    { value: 'all', label: 'Anyone' },
    { value: 'me', label: 'Assigned to me' },
    { value: 'unassigned', label: 'Nobody yet' },
    ...props.users.map((user) => ({
        value: String(user.id),
        label: user.name,
    })),
];

const cards = [
    { key: 'new', label: 'New', filter: { status: 'new', assignee: 'all' } },
    { key: 'open', label: 'Open', filter: { status: 'open', assignee: 'all' } },
    {
        key: 'waiting',
        label: 'Waiting for customer',
        filter: { status: 'waiting', assignee: 'all' },
    },
    { key: 'mine', label: 'Mine', filter: { status: 'open', assignee: 'me' } },
] as const;

const applyCard = (filter: { status: string; assignee: string }) => {
    filters.status = filter.status;
    filters.assignee = filter.assignee;
};
</script>

<template>
    <Head title="Tickets" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Tickets"
            description="Questions and problems of your customers."
        >
            <template #actions>
                <Button v-if="canEdit('tickets')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New ticket</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <button
                v-for="card in cards"
                :key="card.key"
                type="button"
                class="text-left"
                @click="applyCard(card.filter)"
            >
                <Card class="transition-colors hover:bg-muted/40">
                    <CardContent class="pt-6">
                        <p class="text-sm text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ summary[card.key] }}
                        </p>
                    </CardContent>
                </Card>
            </button>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search number, subject, customer…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statusOptions"
                class="lg:w-48"
            />
            <SelectInput
                v-model="filters.assignee"
                :options="assigneeOptions"
                class="lg:w-48"
            />
            <SelectInput
                v-model="filters.priority"
                :options="priorities"
                placeholder="Any priority"
                class="lg:w-40"
            />
        </div>

        <EmptyState
            v-if="tickets.data.length === 0"
            :icon="LifeBuoy"
            title="No tickets here"
            description="Tickets are questions from customers: log them after a call, or let customers use your support form."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Ticket</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Customer</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Handled by</TableHead
                        >
                        <TableHead>Status</TableHead>
                        <TableHead class="hidden text-right sm:table-cell">
                            Last activity
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="ticket in tickets.data" :key="ticket.id">
                        <TableCell>
                            <Link
                                :href="show([slug, ticket.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ ticket.subject }}
                            </Link>
                            <p
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                {{ ticket.number }}
                                <span class="inline-flex items-center gap-0.5">
                                    <MessageSquare class="size-3" />
                                    {{ ticket.messages_count ?? 0 }}
                                </span>
                                <Pill
                                    v-if="ticket.priority !== 'normal'"
                                    :color="ticket.priority_color"
                                    >{{ ticket.priority_label }}</Pill
                                >
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            ticket.customer_label
                        }}</TableCell>
                        <TableCell class="hidden text-sm lg:table-cell">{{
                            ticket.assignee?.name ?? '—'
                        }}</TableCell>
                        <TableCell>
                            <Pill :color="ticket.status_color">{{
                                ticket.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm text-muted-foreground sm:table-cell"
                        >
                            {{ formatRelative(ticket.updated_at) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="tickets.meta" :links="tickets.links" />
    </div>
</template>
