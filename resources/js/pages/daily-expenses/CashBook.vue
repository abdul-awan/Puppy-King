<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Pencil, Plus, Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import CashEntryDialog from '@/components/daily-expenses/CashEntryDialog.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
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
import { exportMethod, index } from '@/routes/cash-book';
import {
    index as dailyExpenses,
    pdf as printExpenses,
    show as showExpense,
} from '@/routes/daily-expenses';
import type {
    CashMovement,
    CashSummary,
    Option,
    PettyCashEntry,
    Team,
} from '@/types';

const props = defineProps<{
    filters: { period: string | null; from: string | null; to: string | null };
    periods: Option[];
    range: { from: string; to: string };
    summary: CashSummary;
    movements: CashMovement[];
    cashInHand: string;
    entries: Record<string, PettyCashEntry>;
    directions: Option[];
    people: string[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Daily expenses',
                href: dailyExpenses(props.currentTeam.slug),
            },
            { title: 'Cash book', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const adding = ref(false);
const editing = ref<PettyCashEntry | null>(null);
const editOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editing.value = null;
        }
    },
});

const money = (value: string | number) => formatMoney(value, currency.value);

const periodQuery = computed(() => ({
    period: props.filters.period ?? undefined,
    from: props.filters.from ?? undefined,
    to: props.filters.to ?? undefined,
}));

const exportUrl = computed(
    () => exportMethod(slug.value, { query: periodQuery.value }).url,
);
const printUrl = computed(
    () => printExpenses(slug.value, { query: periodQuery.value }).url,
);

const edit = (movement: CashMovement) => {
    editing.value = props.entries[movement.id] ?? null;
};
</script>

<template>
    <Head title="Cash book" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Cash book"
            :description="`The cash box from ${formatDate(range.from)} to ${formatDate(range.to)}: cash added, cash taken out and the daily expenses paid in cash.`"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="printUrl" target="_blank" rel="noopener"
                        ><Printer /> Print</a
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
                <Button
                    v-if="canEdit('daily_expenses')"
                    size="sm"
                    @click="adding = true"
                >
                    <Plus /> Add or take out cash
                </Button>
            </template>
        </PageHeader>

        <PeriodFilter
            v-model:period="filters.period"
            v-model:from="filters.from"
            v-model:to="filters.to"
            :periods="periods"
            placeholder="This month"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure
                label="Cash at the start"
                :value="money(summary.opening)"
            />
            <ReportFigure
                label="Cash added"
                :value="money(summary.added)"
                :hint="
                    Number(summary.taken_out)
                        ? `${money(summary.taken_out)} taken out`
                        : null
                "
            />
            <ReportFigure
                label="Spent in cash"
                :value="money(summary.expenses)"
            />
            <ReportFigure
                label="Cash at the end"
                :value="money(summary.closing)"
                :tone="Number(summary.closing) < 0 ? 'bad' : 'default'"
                :hint="`In hand now: ${money(cashInHand)}`"
            />
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-28">Date</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Person</TableHead
                        >
                        <TableHead class="text-right">Cash in</TableHead>
                        <TableHead class="text-right">Cash out</TableHead>
                        <TableHead class="text-right">In hand</TableHead>
                        <TableHead
                            v-if="canEdit('daily_expenses')"
                            class="w-12"
                        />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow class="bg-muted/40">
                        <TableCell>{{ formatDate(range.from) }}</TableCell>
                        <TableCell class="font-medium" colspan="4"
                            >Cash at the start</TableCell
                        >
                        <TableCell
                            class="text-right font-medium tabular-nums"
                            >{{ money(summary.opening) }}</TableCell
                        >
                        <TableCell v-if="canEdit('daily_expenses')" />
                    </TableRow>
                    <TableRow v-if="movements.length === 0">
                        <TableCell
                            colspan="7"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            Nothing went in or out of the cash box in this
                            period.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="movement in movements" :key="movement.key">
                        <TableCell class="text-sm whitespace-nowrap">{{
                            formatDate(movement.date)
                        }}</TableCell>
                        <TableCell class="text-sm">
                            <Link
                                v-if="movement.type === 'expense'"
                                :href="showExpense([slug, movement.id]).url"
                                class="hover:underline"
                            >
                                {{ movement.description }}
                            </Link>
                            <span v-else class="font-medium">{{
                                movement.description
                            }}</span>
                            <span
                                v-if="movement.number"
                                class="ml-1 text-xs text-muted-foreground"
                                >{{ movement.number }}</span
                            >
                        </TableCell>
                        <TableCell
                            class="hidden text-sm text-muted-foreground md:table-cell"
                            >{{ movement.person ?? '' }}</TableCell
                        >
                        <TableCell
                            class="text-right text-sm text-green-700 tabular-nums dark:text-green-400"
                            >{{
                                movement.in ? money(movement.in) : ''
                            }}</TableCell
                        >
                        <TableCell class="text-right text-sm tabular-nums">{{
                            movement.out ? money(movement.out) : ''
                        }}</TableCell>
                        <TableCell
                            class="text-right text-sm font-medium tabular-nums"
                            :class="{
                                'text-destructive':
                                    Number(movement.balance) < 0,
                            }"
                            >{{ money(movement.balance) }}</TableCell
                        >
                        <TableCell v-if="canEdit('daily_expenses')">
                            <Button
                                v-if="movement.type === 'entry'"
                                variant="ghost"
                                size="icon-sm"
                                title="Edit"
                                @click="edit(movement)"
                            >
                                <Pencil />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <CashEntryDialog
            v-model:open="adding"
            :directions="directions"
            :people="people"
        />
        <CashEntryDialog
            v-model:open="editOpen"
            :entry="editing"
            :directions="directions"
            :people="people"
        />
    </div>
</template>
