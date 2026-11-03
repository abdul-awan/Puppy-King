<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Handshake, KanbanSquare, List, Plus } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import Pill from '@/components/app/Pill.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import DealBoard from '@/components/deals/DealBoard.vue';
import { Button } from '@/components/ui/button';
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
import { idOptions } from '@/lib/options';
import { create, index, show } from '@/routes/deals';
import type { BoardPhase, Deal, IdName, Paginated, Team } from '@/types';

const props = defineProps<{
    filters: {
        view: 'board' | 'list';
        pipeline: number | null;
        search: string;
        responsible: number | null;
        status: 'open' | 'won' | 'lost';
    };
    pipelines: IdName[];
    board: BoardPhase[] | null;
    deals: Paginated<Deal> | null;
    users: IdName[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [{ title: 'Deals', href: index(props.currentTeam.slug) }],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const { filters } = useFilters(
    {
        view: props.filters.view,
        pipeline: props.filters.pipeline,
        search: props.filters.search,
        responsible: props.filters.responsible,
        status: props.filters.status,
    },
    () => index(slug.value).url,
);

const totals = computed(() => {
    const phases = props.board ?? [];

    return {
        count: phases.reduce((sum, phase) => sum + phase.count, 0),
        value: phases.reduce((sum, phase) => sum + phase.total, 0),
        weighted: phases.reduce((sum, phase) => sum + phase.weighted, 0),
    };
});

const statuses = [
    { value: 'open', label: 'Open' },
    { value: 'won', label: 'Won' },
    { value: 'lost', label: 'Lost' },
];
</script>

<template>
    <Head title="Deals" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader title="Deals" description="Your sales pipeline.">
            <template #meta>
                <p v-if="board" class="text-sm text-muted-foreground">
                    {{ totals.count }} open deals worth
                    <span class="font-medium text-foreground">{{
                        formatMoney(totals.value, currency)
                    }}</span>
                    · weighted forecast
                    <span class="font-medium text-foreground">{{
                        formatMoney(totals.weighted, currency)
                    }}</span>
                </p>
            </template>
            <template #actions>
                <div class="flex rounded-md border p-0.5">
                    <Button
                        size="sm"
                        :variant="
                            filters.view === 'board' ? 'secondary' : 'ghost'
                        "
                        @click="filters.view = 'board'"
                    >
                        <KanbanSquare /> Board
                    </Button>
                    <Button
                        size="sm"
                        :variant="
                            filters.view === 'list' ? 'secondary' : 'ghost'
                        "
                        @click="filters.view = 'list'"
                    >
                        <List /> List
                    </Button>
                </div>
                <Button v-if="canEdit('deals')" size="sm" as-child>
                    <Link
                        :href="
                            create(slug, {
                                query: {
                                    pipeline: filters.pipeline ?? undefined,
                                },
                            }).url
                        "
                    >
                        <Plus /> New deal
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search deals or customers…"
            />
            <SelectInput
                v-if="pipelines.length > 1"
                v-model="filters.pipeline"
                :options="idOptions(pipelines)"
                class="lg:w-48"
            />
            <SelectInput
                v-model="filters.responsible"
                :options="idOptions(users)"
                placeholder="Everyone"
                class="lg:w-48"
            />
            <SelectInput
                v-if="filters.view === 'list'"
                v-model="filters.status"
                :options="statuses"
                class="lg:w-32"
            />
        </div>

        <DealBoard v-if="board" :phases="board" :can-write="canEdit('deals')" />

        <template v-else-if="deals">
            <EmptyState
                v-if="deals.data.length === 0"
                :icon="Handshake"
                title="No deals found"
                description="Deals you create appear here."
            />
            <div v-else class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Deal</TableHead>
                            <TableHead class="hidden md:table-cell"
                                >Phase</TableHead
                            >
                            <TableHead class="text-right">Value</TableHead>
                            <TableHead class="hidden lg:table-cell"
                                >Expected close</TableHead
                            >
                            <TableHead class="hidden sm:table-cell"
                                >Responsible</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="deal in deals.data" :key="deal.id">
                            <TableCell>
                                <Link
                                    :href="show([slug, deal.id]).url"
                                    class="font-medium hover:underline"
                                >
                                    {{ deal.title }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        deal.company?.name ??
                                        deal.contact?.name ??
                                        ''
                                    }}
                                </p>
                            </TableCell>
                            <TableCell class="hidden text-sm md:table-cell">
                                <Pill
                                    v-if="deal.status !== 'open'"
                                    :color="deal.status_color"
                                >
                                    {{ deal.status_label }}
                                </Pill>
                                <span v-else>{{ deal.phase?.name }}</span>
                            </TableCell>
                            <TableCell
                                class="text-right text-sm font-medium whitespace-nowrap"
                            >
                                {{ formatMoney(deal.value, deal.currency) }}
                            </TableCell>
                            <TableCell class="hidden text-sm lg:table-cell">
                                {{
                                    deal.expected_close_date
                                        ? formatDate(deal.expected_close_date)
                                        : ''
                                }}
                            </TableCell>
                            <TableCell class="hidden text-sm sm:table-cell">
                                {{ deal.responsible_user?.name ?? '' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <Pagination :meta="deals.meta" :links="deals.links" />
        </template>
    </div>
</template>
