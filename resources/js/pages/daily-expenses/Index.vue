<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Coins,
    Download,
    Paperclip,
    Pencil,
    Plus,
    Printer,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import DailyExpenseDialog from '@/components/daily-expenses/DailyExpenseDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatMoney, parseDate } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index as cashBook } from '@/routes/cash-book';
import { exportMethod, index, pdf, show } from '@/routes/daily-expenses';
import type { DailyExpense, IdName, Option, Paginated, Team } from '@/types';

const props = defineProps<{
    expenses: Paginated<DailyExpense>;
    dayTotals: Record<string, { total: string; count: number }>;
    filters: {
        search: string;
        category: number | null;
        method: string | null;
        period: string | null;
        from: string | null;
        to: string | null;
    };
    periods: Option[];
    summary: {
        today: string;
        today_count: number;
        month: string;
        month_count: number;
        month_cash: string;
        cash_in_hand: string | null;
    };
    filtered: { total: string; count: number } | null;
    categories: IdName[];
    paymentMethods: Option[];
    people: string[];
    payees: string[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Daily expenses', href: index(props.currentTeam.slug) },
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
const editing = ref<DailyExpense | null>(null);
const editOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editing.value = null;
        }
    },
});

const money = (value: string | number) => formatMoney(value, currency.value);

const dayFormatter = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const dayLabel = (date: string) => {
    const parsed = parseDate(date);

    return parsed ? dayFormatter.format(parsed) : date;
};

// The expenses of the page per day (the list is sorted newest first).
const days = computed(() => {
    const groups: { date: string; items: DailyExpense[] }[] = [];

    for (const expense of props.expenses.data) {
        const last = groups.at(-1);

        if (last?.date === expense.date) {
            last.items.push(expense);
        } else {
            groups.push({ date: expense.date, items: [expense] });
        }
    }

    return groups;
});

const query = computed(() => {
    const result: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== null && value !== undefined && value !== '') {
            result[key] = value;
        }
    }

    return result;
});

const exportUrl = computed(
    () => exportMethod(slug.value, { query: query.value }).url,
);
const printUrl = computed(() => pdf(slug.value, { query: query.value }).url);
</script>

<template>
    <Head title="Daily expenses" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Daily expenses"
            description="Day-to-day spending such as tea, fuel, stationery and couriers, mostly paid from the cash box."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="cashBook(slug).url"
                        ><BookOpen /> Cash book</Link
                    >
                </Button>
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
                    <Plus /> Add expense
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Spent today</p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ money(summary.today) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.today_count }}
                        {{ summary.today_count === 1 ? 'expense' : 'expenses' }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">This month</p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ money(summary.month) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ summary.month_count }} expenses ·
                        {{ money(summary.month_cash) }} in cash
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Cash in hand</p>
                    <template v-if="summary.cash_in_hand !== null">
                        <p
                            class="text-2xl font-semibold tabular-nums"
                            :class="{
                                'text-destructive':
                                    Number(summary.cash_in_hand) < 0,
                            }"
                        >
                            {{ money(summary.cash_in_hand) }}
                        </p>
                        <Link
                            :href="cashBook(slug).url"
                            class="text-xs text-muted-foreground hover:underline"
                        >
                            {{
                                Number(summary.cash_in_hand) < 0
                                    ? 'More was spent than added: add the cash you put in'
                                    : 'Open the cash book'
                            }}
                        </Link>
                    </template>
                    <template v-else>
                        <p class="text-2xl font-semibold text-muted-foreground">
                            —
                        </p>
                        <Link
                            :href="cashBook(slug).url"
                            class="text-xs text-muted-foreground hover:underline"
                        >
                            Add the cash in your cash box to keep track of it
                        </Link>
                    </template>
                </CardContent>
            </Card>
        </div>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput
                v-model="filters.search"
                placeholder="Search description, shop, person, no.…"
            />
            <SelectInput
                v-model="filters.category"
                :options="idOptions(categories)"
                placeholder="All categories"
                class="lg:w-52"
            />
            <SelectInput
                v-model="filters.method"
                :options="paymentMethods"
                placeholder="Any way of paying"
                class="lg:w-52"
            />
            <PeriodFilter
                v-model:period="filters.period"
                v-model:from="filters.from"
                v-model:to="filters.to"
                :periods="periods"
            />
        </div>

        <p v-if="filtered" class="text-sm text-muted-foreground">
            {{ filtered.count }} expenses found, together
            <span class="font-medium text-foreground">{{
                money(filtered.total)
            }}</span>
        </p>

        <EmptyState
            v-if="expenses.data.length === 0"
            :icon="Coins"
            title="No daily expenses found"
            description="Write down what is spent every day (tea, fuel, stationery…) to know where the cash goes."
        >
            <Button
                v-if="canEdit('daily_expenses') && !filtered"
                size="sm"
                @click="adding = true"
            >
                <Plus /> Add expense
            </Button>
        </EmptyState>

        <div v-else class="space-y-4">
            <section
                v-for="day in days"
                :key="day.date"
                class="overflow-hidden rounded-lg border"
            >
                <header
                    class="flex items-center justify-between gap-2 bg-muted/50 px-4 py-2 text-sm"
                >
                    <span class="font-medium">{{ dayLabel(day.date) }}</span>
                    <span class="text-muted-foreground">
                        {{ dayTotals[day.date]?.count ?? day.items.length }}
                        ·
                        <span
                            class="font-semibold text-foreground tabular-nums"
                        >
                            {{ money(dayTotals[day.date]?.total ?? 0) }}
                        </span>
                    </span>
                </header>
                <ul class="divide-y">
                    <li
                        v-for="expense in day.items"
                        :key="expense.id"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm"
                    >
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="show([slug, expense.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ expense.description }}
                            </Link>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ expense.number }}
                                <template v-if="expense.category">
                                    · {{ expense.category.name }}
                                </template>
                                <template v-if="expense.paid_to">
                                    · to {{ expense.paid_to }}
                                </template>
                                <template v-if="expense.paid_by">
                                    · by {{ expense.paid_by }}
                                </template>
                            </p>
                        </div>
                        <span
                            class="hidden text-xs text-muted-foreground sm:inline"
                        >
                            {{
                                expense.payment_method === 'cash'
                                    ? 'Cash'
                                    : expense.payment_method_label
                            }}
                        </span>
                        <Paperclip
                            v-if="expense.attachments_count"
                            class="size-3.5 text-muted-foreground"
                            :aria-label="`${expense.attachments_count} receipts`"
                        />
                        <span
                            class="w-28 text-right font-medium whitespace-nowrap tabular-nums"
                        >
                            {{ money(expense.amount) }}
                        </span>
                        <Button
                            v-if="canEdit('daily_expenses')"
                            variant="ghost"
                            size="icon-sm"
                            title="Edit"
                            @click="editing = expense"
                        >
                            <Pencil />
                        </Button>
                    </li>
                </ul>
            </section>
        </div>

        <Pagination :meta="expenses.meta" :links="expenses.links" />

        <DailyExpenseDialog
            v-model:open="adding"
            :categories="categories"
            :payment-methods="paymentMethods"
            :people="people"
            :payees="payees"
        />
        <DailyExpenseDialog
            v-model:open="editOpen"
            :expense="editing"
            :categories="categories"
            :payment-methods="paymentMethods"
            :people="people"
            :payees="payees"
        />
    </div>
</template>
