<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ListChecks,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import PlanTaskDialog from '@/components/planning/PlanTaskDialog.vue';
import type { PlannableTask } from '@/components/planning/PlanTaskDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDuration, parseDate, today } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index } from '@/routes/planning';
import type { IdName, Team } from '@/types';

type BoardItem = {
    kind: 'event' | 'task';
    id: number;
    title: string;
    minutes: number;
    time?: string | null;
    project?: string | null;
    done?: boolean;
    planned_minutes?: number | null;
    due_date?: string | null;
};

type BoardRow = {
    user: IdName;
    days: {
        date: string;
        capacity: number;
        minutes: number;
        items: BoardItem[];
    }[];
    minutes: number;
    capacity: number;
};

type UnplannedTask = {
    id: number;
    title: string;
    project: string | null;
    assignee_id: number | null;
    assignee: string | null;
    due_date: string | null;
    estimated_minutes: number | null;
    priority: string;
    priority_label: string;
    priority_color: string;
};

const props = defineProps<{
    board: BoardRow[];
    days: { date: string; working: boolean }[];
    unplanned: UnplannedTask[];
    filters: { project: number | null; weeks: number };
    week: { start: string; previous: string; next: string };
    capacity: number;
    users: IdName[];
    projects: IdName[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Planning', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const go = (params: Record<string, string | number | null>) => {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries({
        week: props.week.start,
        project: props.filters.project,
        weeks: props.filters.weeks === 2 ? 2 : null,
        ...params,
    })) {
        if (value !== null && value !== '') {
            query[key] = value;
        }
    }

    router.get(index(slug.value).url, query, {
        preserveScroll: true,
        preserveState: true,
    });
};

const load = (minutes: number, capacity: number) => {
    if (capacity === 0) {
        return minutes > 0 ? 'bg-red-500' : 'bg-transparent';
    }

    const share = minutes / capacity;

    return share > 1
        ? 'bg-red-500'
        : share > 0.85
          ? 'bg-amber-500'
          : 'bg-green-500';
};

const width = (minutes: number, capacity: number) =>
    capacity === 0
        ? minutes > 0
            ? 100
            : 0
        : Math.min(100, Math.round((minutes / capacity) * 100));

const dayName = (date: string) =>
    (parseDate(date) ?? new Date()).toLocaleDateString('en-PK', {
        weekday: 'short',
        day: 'numeric',
    });

// Dialog.
const dialog = ref(false);
const selected = ref<PlannableTask | null>(null);
const dialogUser = ref<number | null>(null);
const dialogDate = ref(today());

const firstWorkingDay = computed(
    () =>
        props.days.find((day) => day.working && day.date >= today())?.date ??
        props.days[0]?.date ??
        today(),
);

const planUnplanned = (task: UnplannedTask) => {
    if (!canEdit('planning')) {
        return;
    }

    selected.value = task;
    dialogUser.value = null;
    dialogDate.value = firstWorkingDay.value;
    dialog.value = true;
};

const editPlanned = (item: BoardItem, userId: number, date: string) => {
    if (item.kind !== 'task' || !canEdit('planning')) {
        return;
    }

    selected.value = {
        id: item.id,
        title: item.title,
        project: item.project,
        assignee_id: userId,
        planned_date: date,
        planned_minutes: item.planned_minutes,
        estimated_minutes: item.minutes,
    };
    dialogUser.value = userId;
    dialogDate.value = date;
    dialog.value = true;
};
</script>

<template>
    <Head title="Planning" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="mr-2 text-xl font-semibold tracking-tight">Planning</h1>
            <Button
                variant="outline"
                size="icon"
                aria-label="Previous week"
                @click="go({ week: week.previous })"
            >
                <ChevronLeft />
            </Button>
            <Button variant="outline" size="sm" @click="go({ week: null })">
                This week
            </Button>
            <Button
                variant="outline"
                size="icon"
                aria-label="Next week"
                @click="go({ week: week.next })"
            >
                <ChevronRight />
            </Button>
            <span class="text-sm font-medium">
                {{ formatDate(days[0]?.date) }} –
                {{ formatDate(days[days.length - 1]?.date) }}
            </span>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <SelectInput
                    :model-value="filters.project"
                    :options="idOptions(projects)"
                    placeholder="All projects"
                    class="w-56"
                    @update:model-value="go({ project: $event ?? null })"
                />
                <div class="inline-flex rounded-md border p-0.5">
                    <Button
                        size="sm"
                        :variant="filters.weeks === 1 ? 'secondary' : 'ghost'"
                        @click="go({ weeks: null })"
                    >
                        1 week
                    </Button>
                    <Button
                        size="sm"
                        :variant="filters.weeks === 2 ? 'secondary' : 'ghost'"
                        @click="go({ weeks: 2 })"
                    >
                        2 weeks
                    </Button>
                </div>
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            A working day has {{ formatDuration(capacity) }}. Meetings from the
            calendar and planned tasks fill it up:
            <span class="text-green-700 dark:text-green-400">free</span>,
            <span class="text-amber-700 dark:text-amber-400">almost full</span>,
            <span class="text-red-700 dark:text-red-400">too much</span>.
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full min-w-[900px] table-fixed text-sm">
                <thead>
                    <tr class="border-b bg-muted/40">
                        <th class="w-44 px-3 py-2 text-left font-medium">
                            Person
                        </th>
                        <th
                            v-for="day in days"
                            :key="day.date"
                            class="px-2 py-2 text-left font-medium"
                            :class="{
                                'text-muted-foreground': !day.working,
                                'text-primary': day.date === today(),
                            }"
                        >
                            {{ dayName(day.date) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in board"
                        :key="row.user.id"
                        class="border-b align-top last:border-b-0"
                    >
                        <td class="px-3 py-2">
                            <p class="font-medium">{{ row.user.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDuration(row.minutes) }} of
                                {{ formatDuration(row.capacity) }}
                            </p>
                        </td>
                        <td
                            v-for="day in row.days"
                            :key="day.date"
                            class="space-y-1 border-l px-1.5 py-2"
                            :class="{ 'bg-muted/30': day.capacity === 0 }"
                        >
                            <div class="h-1.5 rounded-full bg-muted">
                                <div
                                    class="h-1.5 rounded-full"
                                    :class="load(day.minutes, day.capacity)"
                                    :style="{
                                        width: `${width(day.minutes, day.capacity)}%`,
                                    }"
                                />
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                {{
                                    day.minutes
                                        ? `${formatDuration(day.minutes)}${day.capacity ? ` / ${formatDuration(day.capacity)}` : ''}`
                                        : day.capacity
                                          ? 'free'
                                          : ''
                                }}
                            </p>
                            <button
                                v-for="item in day.items"
                                :key="`${item.kind}-${item.id}`"
                                type="button"
                                class="flex w-full items-start gap-1 rounded px-1 py-0.5 text-left text-xs"
                                :class="
                                    item.kind === 'event'
                                        ? 'cursor-default bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-100'
                                        : 'bg-amber-50 text-amber-900 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-100'
                                "
                                @click="
                                    editPlanned(item, row.user.id, day.date)
                                "
                            >
                                <CalendarDays
                                    v-if="item.kind === 'event'"
                                    class="mt-0.5 size-3 shrink-0"
                                />
                                <ListChecks
                                    v-else
                                    class="mt-0.5 size-3 shrink-0"
                                />
                                <span
                                    class="min-w-0 flex-1"
                                    :class="{
                                        'line-through opacity-60': item.done,
                                    }"
                                >
                                    <template v-if="item.time"
                                        >{{ item.time }} </template
                                    >{{ item.title }}
                                    <span
                                        v-if="item.minutes"
                                        class="opacity-70"
                                    >
                                        ·
                                        {{ formatDuration(item.minutes) }}</span
                                    >
                                </span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Not planned yet ({{ unplanned.length }})</CardTitle>
            </CardHeader>
            <CardContent>
                <p
                    v-if="unplanned.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Every open task has a day.
                </p>
                <ul v-else class="divide-y rounded-md border">
                    <li
                        v-for="task in unplanned"
                        :key="task.id"
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm"
                    >
                        <span class="min-w-0 flex-1">
                            {{ task.title }}
                            <span class="block text-xs text-muted-foreground">
                                <template v-if="task.project"
                                    >{{ task.project }} ·
                                </template>
                                <template v-if="task.assignee"
                                    >{{ task.assignee }} ·
                                </template>
                                <template v-if="task.estimated_minutes"
                                    >{{
                                        formatDuration(task.estimated_minutes)
                                    }}
                                    ·
                                </template>
                                <template v-if="task.due_date"
                                    >due
                                    {{ formatDate(task.due_date) }}</template
                                >
                            </span>
                        </span>
                        <Pill
                            v-if="task.priority !== 'normal'"
                            :color="task.priority_color"
                            >{{ task.priority_label }}</Pill
                        >
                        <Button
                            v-if="canEdit('planning')"
                            size="sm"
                            variant="outline"
                            @click="planUnplanned(task)"
                        >
                            Plan
                        </Button>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <PlanTaskDialog
            v-model:open="dialog"
            :task="selected"
            :default-date="dialogDate"
            :default-user-id="dialogUser"
            :users="users"
        />
    </div>
</template>
