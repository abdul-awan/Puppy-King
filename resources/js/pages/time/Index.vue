<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import PeriodFilter from '@/components/app/PeriodFilter.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import TimeEntryDialog from '@/components/time/TimeEntryDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import {
    formatDate,
    formatDuration,
    formatMoney,
    parseDate,
    toDateInput,
} from '@/lib/format';
import { idOptions } from '@/lib/options';
import { show as showProject } from '@/routes/projects';
import { destroy, exportMethod, index } from '@/routes/time';
import type {
    IdName,
    Option,
    Paginated,
    Team,
    TimeEntry,
    WorkTypeOption,
} from '@/types';

type ProjectOption = IdName & { company_id: number | null; billable: boolean };

const props = defineProps<{
    entries: Paginated<TimeEntry> | TimeEntry[];
    filters: {
        view: 'week' | 'list';
        user: string;
        project: number | null;
        billable: string | null;
        invoiced: string | null;
        period: string | null;
        from: string | null;
        to: string | null;
    };
    week: { start: string; previous: string; next: string };
    totals: { minutes: number; billable_minutes: number; value: string };
    isManager: boolean;
    periods: Option[];
    users: IdName[];
    projects: ProjectOption[];
    workTypes: WorkTypeOption[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [{ title: 'Time', href: index(props.currentTeam.slug) }],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const { filters } = useFilters(
    {
        view: props.filters.view,
        user: props.filters.user,
        project: props.filters.project,
        billable: props.filters.billable,
        invoiced: props.filters.invoiced,
        period: props.filters.period,
        from: props.filters.from,
        to: props.filters.to,
        week: props.week.start as string | null,
    },
    () => index(slug.value).url,
);

const list = computed(() =>
    Array.isArray(props.entries) ? props.entries : props.entries.data,
);

// The seven days of the week shown in the timesheet.
const days = computed(() => {
    const start = parseDate(props.week.start) ?? new Date();

    return Array.from({ length: 7 }, (_, offset) => {
        const day = new Date(start);
        day.setDate(start.getDate() + offset);

        return {
            date: toDateInput(day),
            label: day.toLocaleDateString('en-PK', { weekday: 'short' }),
            number: day.getDate(),
        };
    });
});

// One row per user, project and task, with the minutes of each day.
const rows = computed(() => {
    const map = new Map<
        string,
        {
            label: string;
            sub: string;
            minutes: Record<string, number>;
            total: number;
        }
    >();

    for (const entry of list.value) {
        const key = `${entry.user_id}|${entry.project_id ?? 0}|${entry.task_id ?? 0}`;
        const row = map.get(key) ?? {
            label: entry.project?.title ?? entry.company?.name ?? 'No project',
            sub: [
                entry.task?.title,
                filters.user !== 'me' ? entry.user?.name : null,
            ]
                .filter(Boolean)
                .join(' · '),
            minutes: {},
            total: 0,
        };

        row.minutes[entry.date] =
            (row.minutes[entry.date] ?? 0) + entry.duration_minutes;
        row.total += entry.duration_minutes;
        map.set(key, row);
    }

    return [...map.values()];
});

const dayTotal = (date: string) =>
    list.value
        .filter((entry) => entry.date === date)
        .reduce((sum, entry) => sum + entry.duration_minutes, 0);

const weekLabel = computed(() => {
    const first = days.value[0];
    const last = days.value[6];

    return `${formatDate(first.date)} – ${formatDate(last.date)}`;
});

const goToWeek = (date: string | null) => {
    filters.week = date;
};

const dialog = ref(false);
const editing = ref<TimeEntry | null>(null);
const dialogDate = ref<string | null>(null);

const openDialog = (entry: TimeEntry | null, date: string | null = null) => {
    editing.value = entry;
    dialogDate.value = date;
    dialog.value = true;
};

const remove = (entry: TimeEntry) => {
    if (window.confirm('Delete this time entry?')) {
        router.delete(destroy([slug.value, entry.id]).url, {
            preserveScroll: true,
        });
    }
};

const userOptions = computed(() => [
    { value: 'me', label: 'My time' },
    { value: 'all', label: 'Everybody' },
    ...props.users.map((user) => ({
        value: String(user.id),
        label: user.name,
    })),
]);

const exportUrl = computed(() => {
    const query: Record<string, string | number> = { view: 'list' };

    for (const [key, value] of Object.entries(filters)) {
        if (
            value !== null &&
            value !== undefined &&
            value !== '' &&
            !['view', 'week'].includes(key)
        ) {
            query[key] = value;
        }
    }

    return exportMethod(slug.value, { query }).url;
});

const canChange = (entry: TimeEntry) =>
    canEdit('time_tracking') && !entry.invoice_id && !entry.is_running;
</script>

<template>
    <Head title="Time" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Time"
            description="Hours worked on projects and for customers."
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> Export</a>
                </Button>
                <Button
                    v-if="canEdit('time_tracking')"
                    size="sm"
                    @click="openDialog(null)"
                >
                    <Plus /> Log time
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-md border p-0.5">
                <Button
                    size="sm"
                    :variant="filters.view === 'week' ? 'secondary' : 'ghost'"
                    @click="filters.view = 'week'"
                >
                    Week
                </Button>
                <Button
                    size="sm"
                    :variant="filters.view === 'list' ? 'secondary' : 'ghost'"
                    @click="filters.view = 'list'"
                >
                    List
                </Button>
            </div>
            <SelectInput
                v-if="isManager"
                v-model="filters.user"
                :options="userOptions"
                class="w-44"
            />
            <SelectInput
                v-model="filters.project"
                :options="idOptions(projects)"
                placeholder="All projects"
                class="w-56"
            />
            <template v-if="filters.view === 'list'">
                <SelectInput
                    v-model="filters.billable"
                    :options="[
                        { value: 'yes', label: 'Billable' },
                        { value: 'no', label: 'Not billable' },
                    ]"
                    placeholder="Billable or not"
                    class="w-40"
                />
                <SelectInput
                    v-model="filters.invoiced"
                    :options="[
                        { value: 'no', label: 'Not invoiced' },
                        { value: 'yes', label: 'Invoiced' },
                    ]"
                    placeholder="Invoiced or not"
                    class="w-40"
                />
                <PeriodFilter
                    v-model:period="filters.period"
                    v-model:from="filters.from"
                    v-model:to="filters.to"
                    :periods="periods"
                />
            </template>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Total</p>
                    <p class="text-2xl font-semibold">
                        {{ formatDuration(totals.minutes) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Billable</p>
                    <p class="text-2xl font-semibold">
                        {{ formatDuration(totals.billable_minutes) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            totals.minutes
                                ? Math.round(
                                      (totals.billable_minutes /
                                          totals.minutes) *
                                          100,
                                  )
                                : 0
                        }}% of the time
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Billable value</p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(totals.value, currency) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <template v-if="filters.view === 'week'">
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Previous week"
                    @click="goToWeek(week.previous)"
                >
                    <ChevronLeft />
                </Button>
                <Button variant="outline" size="sm" @click="goToWeek(null)">
                    This week
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Next week"
                    @click="goToWeek(week.next)"
                >
                    <ChevronRight />
                </Button>
                <span class="text-sm font-medium">{{ weekLabel }}</span>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="min-w-48"
                                >Project / task</TableHead
                            >
                            <TableHead
                                v-for="day in days"
                                :key="day.date"
                                class="w-20 text-center"
                            >
                                {{ day.label }} {{ day.number }}
                            </TableHead>
                            <TableHead class="w-20 text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="rows.length === 0">
                            <TableCell
                                :colspan="9"
                                class="text-center text-sm text-muted-foreground"
                            >
                                No time logged this week. Click a day below to
                                add some.
                            </TableCell>
                        </TableRow>
                        <TableRow
                            v-for="(row, position) in rows"
                            :key="position"
                        >
                            <TableCell class="text-sm">
                                {{ row.label }}
                                <p
                                    v-if="row.sub"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ row.sub }}
                                </p>
                            </TableCell>
                            <TableCell
                                v-for="day in days"
                                :key="day.date"
                                class="text-center text-sm"
                            >
                                {{
                                    row.minutes[day.date]
                                        ? formatDuration(row.minutes[day.date])
                                        : ''
                                }}
                            </TableCell>
                            <TableCell class="text-right text-sm font-medium">
                                {{ formatDuration(row.total) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell class="text-sm font-medium"
                                >Total</TableCell
                            >
                            <TableCell
                                v-for="day in days"
                                :key="day.date"
                                class="text-center"
                            >
                                <button
                                    v-if="canEdit('time_tracking')"
                                    type="button"
                                    class="w-full rounded px-1 text-sm font-medium hover:bg-muted"
                                    :title="`Log time on ${formatDate(day.date)}`"
                                    @click="openDialog(null, day.date)"
                                >
                                    {{
                                        dayTotal(day.date)
                                            ? formatDuration(dayTotal(day.date))
                                            : '+'
                                    }}
                                </button>
                                <span v-else class="text-sm">{{
                                    formatDuration(dayTotal(day.date))
                                }}</span>
                            </TableCell>
                            <TableCell class="text-right text-sm font-semibold">
                                {{ formatDuration(totals.minutes) }}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </div>
        </template>

        <div v-if="list.length" class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead v-if="filters.user !== 'me'">Who</TableHead>
                        <TableHead>Work</TableHead>
                        <TableHead class="text-right">Time</TableHead>
                        <TableHead class="hidden text-right md:table-cell">
                            Value
                        </TableHead>
                        <TableHead class="w-20" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="entry in list" :key="entry.id">
                        <TableCell class="text-sm whitespace-nowrap">{{
                            formatDate(entry.date)
                        }}</TableCell>
                        <TableCell
                            v-if="filters.user !== 'me'"
                            class="text-sm"
                            >{{ entry.user?.name }}</TableCell
                        >
                        <TableCell class="text-sm">
                            {{ entry.description || entry.task?.title || '—' }}
                            <p class="text-xs text-muted-foreground">
                                <Link
                                    v-if="entry.project"
                                    :href="
                                        showProject([slug, entry.project.id])
                                            .url
                                    "
                                    class="hover:underline"
                                    >{{ entry.project.title }}</Link
                                >
                                <template v-else-if="entry.company">{{
                                    entry.company.name
                                }}</template>
                                <template v-if="entry.work_type">
                                    · {{ entry.work_type.name }}
                                </template>
                                <template v-if="entry.invoice">
                                    · invoiced
                                </template>
                                <template v-if="!entry.is_billable">
                                    · not billable
                                </template>
                            </p>
                        </TableCell>
                        <TableCell class="text-right text-sm whitespace-nowrap">
                            {{
                                entry.is_running
                                    ? 'running…'
                                    : formatDuration(entry.duration_minutes)
                            }}
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm whitespace-nowrap md:table-cell"
                        >
                            {{
                                entry.is_billable
                                    ? formatMoney(entry.value, currency)
                                    : '—'
                            }}
                        </TableCell>
                        <TableCell class="text-right">
                            <template v-if="canChange(entry)">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    aria-label="Edit time"
                                    @click="openDialog(entry)"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    aria-label="Delete time"
                                    @click="remove(entry)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </template>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination
            v-if="!Array.isArray(entries)"
            :meta="entries.meta"
            :links="entries.links"
        />

        <TimeEntryDialog
            v-model:open="dialog"
            :entry="editing"
            :default-date="dialogDate"
            :work-types="workTypes"
            :users="isManager ? users : []"
        />
    </div>
</template>
