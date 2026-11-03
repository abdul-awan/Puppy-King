<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, ListChecks, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import SelectInput from '@/components/app/SelectInput.vue';
import EventDialog from '@/components/calendar/EventDialog.vue';
import type { EventLinks } from '@/components/calendar/EventDialog.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, parseDate, toDateInput, today } from '@/lib/format';
import { index } from '@/routes/calendar';
import type {
    CalendarEventItem,
    CalendarTask,
    IdName,
    Option,
    Team,
} from '@/types';

const props = defineProps<{
    events: CalendarEventItem[];
    tasks: CalendarTask[];
    filters: { view: 'week' | 'month' | 'agenda'; user: string };
    range: {
        start: string;
        end: string;
        anchor: string;
        previous: string;
        next: string;
    };
    users: IdName[];
    types: Option[];
    /** Set when the calendar is opened to plan something for a company, deal or project. */
    planFor: EventLinks | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Calendar', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const HOUR_HEIGHT = 48;

const colors: Record<string, string> = {
    blue: 'bg-blue-100 border-blue-400 text-blue-900 dark:bg-blue-950 dark:text-blue-100',
    green: 'bg-green-100 border-green-500 text-green-900 dark:bg-green-950 dark:text-green-100',
    purple: 'bg-purple-100 border-purple-400 text-purple-900 dark:bg-purple-950 dark:text-purple-100',
    gray: 'bg-muted border-muted-foreground/40 text-foreground',
};

const go = (params: Record<string, string>) => {
    router.get(
        index(slug.value).url,
        {
            view: props.filters.view,
            user: props.filters.user,
            date: props.range.anchor,
            ...params,
        },
        { preserveScroll: true, preserveState: true },
    );
};

const userOptions = computed(() => [
    { value: 'me', label: 'My calendar' },
    { value: 'all', label: 'Everybody' },
    ...props.users.map((user) => ({
        value: String(user.id),
        label: user.name,
    })),
]);

// The days shown (a week, or the weeks of a month).
const days = computed(() => {
    const start = parseDate(props.range.start) ?? new Date();
    const end = parseDate(props.range.end) ?? start;
    const list: string[] = [];

    for (
        const day = new Date(start);
        day <= end;
        day.setDate(day.getDate() + 1)
    ) {
        list.push(toDateInput(day));
    }

    return list;
});

const eventsOn = (date: string) =>
    props.events.filter(
        (event) =>
            event.starts_at.slice(0, 10) <= date &&
            event.ends_at.slice(0, 10) >= date,
    );

const allDayOn = (date: string) =>
    eventsOn(date).filter(
        (event) =>
            event.all_day ||
            event.starts_at.slice(0, 10) !== event.ends_at.slice(0, 10),
    );

const tasksOn = (date: string) =>
    props.tasks.filter((task) => task.due_date === date);

const minutesOf = (value: string) => {
    const [hours, minutes] = value.slice(11, 16).split(':').map(Number);

    return (hours ?? 0) * 60 + (minutes ?? 0);
};

// The hours shown in the week view: 07:00–21:00, widened to fit early or late events.
const hours = computed(() => {
    const timed = props.events.filter((event) => !event.all_day);
    const first = Math.min(
        7,
        ...timed.map((event) => Math.floor(minutesOf(event.starts_at) / 60)),
    );
    const last = Math.max(
        21,
        ...timed.map((event) => Math.ceil(minutesOf(event.ends_at) / 60)),
    );

    return Array.from({ length: last - first }, (_, i) => first + i);
});

// Timed events of a day, with side-by-side lanes for overlaps.
const timedOn = (date: string) => {
    const events = eventsOn(date)
        .filter(
            (event) =>
                !event.all_day &&
                event.starts_at.slice(0, 10) === event.ends_at.slice(0, 10),
        )
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    const laneEnds: number[] = [];
    const placed = events.map((event) => {
        const start = minutesOf(event.starts_at);
        const end = Math.max(start + 30, minutesOf(event.ends_at));
        let lane = laneEnds.findIndex((laneEnd) => laneEnd <= start);

        if (lane === -1) {
            lane = laneEnds.length;
            laneEnds.push(end);
        } else {
            laneEnds[lane] = end;
        }

        return { event, start, end, lane };
    });

    const lanes = Math.max(1, laneEnds.length);
    const firstHour = hours.value[0] ?? 7;

    return placed.map((item) => ({
        ...item,
        style: {
            top: `${((item.start - firstHour * 60) / 60) * HOUR_HEIGHT}px`,
            height: `${Math.max(22, ((item.end - item.start) / 60) * HOUR_HEIGHT - 2)}px`,
            left: `${(item.lane / lanes) * 100}%`,
            width: `${100 / lanes}%`,
        },
    }));
};

const title = computed(() => {
    const anchor = parseDate(props.range.anchor) ?? new Date();

    if (props.filters.view === 'month') {
        return anchor.toLocaleDateString('en-PK', {
            month: 'long',
            year: 'numeric',
        });
    }

    return `${formatDate(props.range.start)} – ${formatDate(props.range.end)}`;
});

const inMonth = (date: string) =>
    date.slice(0, 7) === props.range.anchor.slice(0, 7);

const dayLabel = (date: string) =>
    (parseDate(date) ?? new Date()).toLocaleDateString('en-PK', {
        weekday: 'short',
        day: 'numeric',
    });

const time = (value: string) => value.slice(11, 16);

// Dialog (opened straight away when planning from another page).
const dialog = ref(props.planFor !== null && canEdit('calendar'));
const selected = ref<CalendarEventItem | null>(null);
const newStart = ref<string | null>(null);
const links = ref<EventLinks | undefined>(props.planFor ?? undefined);

const openEvent = (event: CalendarEventItem) => {
    selected.value = event;
    newStart.value = null;
    dialog.value = true;
};

const plan = (date: string, hour = 10) => {
    if (!canEdit('calendar')) {
        return;
    }

    links.value = undefined;
    selected.value = null;
    newStart.value = `${date}T${String(hour).padStart(2, '0')}:00`;
    dialog.value = true;
};

const agendaDays = computed(() =>
    days.value.filter((date) => eventsOn(date).length || tasksOn(date).length),
);

const nowLine = computed(() => {
    const now = new Date();
    const firstHour = hours.value[0] ?? 7;

    return (
        ((now.getHours() * 60 + now.getMinutes() - firstHour * 60) / 60) *
        HOUR_HEIGHT
    );
});
</script>

<template>
    <Head title="Calendar" />

    <div class="space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="mr-2 text-xl font-semibold tracking-tight">Calendar</h1>
            <Button
                variant="outline"
                size="icon"
                aria-label="Previous"
                @click="go({ date: range.previous })"
            >
                <ChevronLeft />
            </Button>
            <Button variant="outline" size="sm" @click="go({ date: today() })">
                Today
            </Button>
            <Button
                variant="outline"
                size="icon"
                aria-label="Next"
                @click="go({ date: range.next })"
            >
                <ChevronRight />
            </Button>
            <span class="text-sm font-medium">{{ title }}</span>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <SelectInput
                    :model-value="filters.user"
                    :options="userOptions"
                    class="w-44"
                    @update:model-value="go({ user: String($event ?? 'me') })"
                />
                <div class="inline-flex rounded-md border p-0.5">
                    <Button
                        v-for="view in ['week', 'month', 'agenda'] as const"
                        :key="view"
                        size="sm"
                        :variant="filters.view === view ? 'secondary' : 'ghost'"
                        class="capitalize"
                        @click="go({ view })"
                    >
                        {{ view }}
                    </Button>
                </div>
                <Button
                    v-if="canEdit('calendar')"
                    size="sm"
                    @click="plan(today())"
                >
                    <Plus /> Plan
                </Button>
            </div>
        </div>

        <!-- Week -->
        <div
            v-if="filters.view === 'week'"
            class="overflow-x-auto rounded-lg border"
        >
            <div class="min-w-[760px]">
                <div class="grid grid-cols-[56px_repeat(7,1fr)] border-b">
                    <div />
                    <div
                        v-for="date in days"
                        :key="date"
                        class="border-l px-2 py-1.5 text-center text-sm"
                        :class="{
                            'bg-primary/5 font-semibold': date === today(),
                        }"
                    >
                        {{ dayLabel(date) }}
                    </div>
                </div>
                <div class="grid grid-cols-[56px_repeat(7,1fr)] border-b">
                    <div
                        class="px-1 py-1 text-right text-[10px] text-muted-foreground"
                    >
                        all day
                    </div>
                    <div
                        v-for="date in days"
                        :key="date"
                        class="min-h-8 space-y-0.5 border-l p-0.5"
                    >
                        <button
                            v-for="event in allDayOn(date)"
                            :key="event.id"
                            type="button"
                            class="block w-full truncate rounded border-l-4 px-1 text-left text-xs"
                            :class="colors[event.color] ?? colors.gray"
                            @click="openEvent(event)"
                        >
                            {{ event.title }}
                        </button>
                        <div
                            v-for="task in tasksOn(date)"
                            :key="`task-${task.id}`"
                            class="flex items-center gap-1 truncate rounded bg-amber-50 px-1 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-100"
                            :title="task.project ?? undefined"
                        >
                            <ListChecks class="size-3 shrink-0" />
                            {{ task.title }}
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-[56px_repeat(7,1fr)]">
                    <div>
                        <div
                            v-for="hour in hours"
                            :key="hour"
                            class="pr-1 text-right text-[10px] text-muted-foreground"
                            :style="{ height: `${HOUR_HEIGHT}px` }"
                        >
                            {{ String(hour).padStart(2, '0') }}:00
                        </div>
                    </div>
                    <div
                        v-for="date in days"
                        :key="date"
                        class="relative border-l"
                    >
                        <button
                            v-for="hour in hours"
                            :key="hour"
                            type="button"
                            class="block w-full border-b border-dashed hover:bg-muted/50"
                            :style="{ height: `${HOUR_HEIGHT}px` }"
                            :aria-label="`Plan at ${hour}:00 on ${formatDate(date)}`"
                            @click="plan(date, hour)"
                        />
                        <div
                            v-if="date === today() && nowLine > 0"
                            class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-500"
                            :style="{ top: `${nowLine}px` }"
                        />
                        <button
                            v-for="item in timedOn(date)"
                            :key="item.event.id"
                            type="button"
                            class="absolute overflow-hidden rounded border-l-4 px-1 py-0.5 text-left text-xs shadow-xs"
                            :class="[
                                colors[item.event.color] ?? colors.gray,
                                {
                                    'line-through opacity-60':
                                        item.event.status === 'cancelled',
                                },
                            ]"
                            :style="item.style"
                            @click="openEvent(item.event)"
                        >
                            <span class="font-medium">{{
                                time(item.event.starts_at)
                            }}</span>
                            {{ item.event.title }}
                            <span
                                v-if="item.event.company"
                                class="block truncate opacity-75"
                                >{{ item.event.company.name }}</span
                            >
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Month -->
        <div
            v-else-if="filters.view === 'month'"
            class="overflow-x-auto rounded-lg border"
        >
            <div class="grid min-w-[700px] grid-cols-7">
                <div
                    v-for="name in [
                        'Mon',
                        'Tue',
                        'Wed',
                        'Thu',
                        'Fri',
                        'Sat',
                        'Sun',
                    ]"
                    :key="name"
                    class="border-b px-2 py-1 text-xs font-medium text-muted-foreground"
                >
                    {{ name }}
                </div>
                <div
                    v-for="date in days"
                    :key="date"
                    class="min-h-28 cursor-pointer border-b border-l p-1 hover:bg-muted/30"
                    :class="{
                        'bg-muted/40 text-muted-foreground': !inMonth(date),
                    }"
                    @click.self="plan(date)"
                >
                    <p
                        class="mb-1 text-xs"
                        :class="{
                            'inline-flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground':
                                date === today(),
                        }"
                        @click="plan(date)"
                    >
                        {{ Number(date.slice(8, 10)) }}
                    </p>
                    <button
                        v-for="event in eventsOn(date).slice(0, 4)"
                        :key="event.id"
                        type="button"
                        class="mb-0.5 block w-full truncate rounded border-l-4 px-1 text-left text-xs"
                        :class="colors[event.color] ?? colors.gray"
                        @click="openEvent(event)"
                    >
                        <template v-if="!event.all_day"
                            >{{ time(event.starts_at) }} </template
                        >{{ event.title }}
                    </button>
                    <p
                        v-if="eventsOn(date).length > 4"
                        class="text-xs text-muted-foreground"
                    >
                        +{{ eventsOn(date).length - 4 }} more
                    </p>
                    <p
                        v-for="task in tasksOn(date).slice(0, 2)"
                        :key="`task-${task.id}`"
                        class="flex items-center gap-1 truncate text-xs text-amber-700 dark:text-amber-300"
                    >
                        <ListChecks class="size-3 shrink-0" /> {{ task.title }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Agenda -->
        <div v-else class="space-y-4">
            <p
                v-if="agendaDays.length === 0"
                class="rounded-lg border p-6 text-center text-sm text-muted-foreground"
            >
                Nothing planned in the next 30 days.
            </p>
            <div
                v-for="date in agendaDays"
                :key="date"
                class="rounded-lg border"
            >
                <p
                    class="border-b bg-muted/40 px-3 py-1.5 text-sm font-medium"
                    :class="{ 'text-primary': date === today() }"
                >
                    {{
                        (parseDate(date) ?? new Date()).toLocaleDateString(
                            'en-PK',
                            { weekday: 'long', day: 'numeric', month: 'long' },
                        )
                    }}
                </p>
                <ul class="divide-y">
                    <li
                        v-for="event in eventsOn(date)"
                        :key="event.id"
                        class="flex cursor-pointer items-start gap-3 px-3 py-2 text-sm hover:bg-muted/30"
                        @click="openEvent(event)"
                    >
                        <span class="w-24 shrink-0 text-muted-foreground">
                            {{
                                event.all_day
                                    ? 'All day'
                                    : `${time(event.starts_at)}–${time(event.ends_at)}`
                            }}
                        </span>
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full border-2"
                            :class="colors[event.color] ?? colors.gray"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="font-medium"
                                :class="{
                                    'line-through':
                                        event.status === 'cancelled',
                                }"
                                >{{ event.title }}</span
                            >
                            <span class="block text-xs text-muted-foreground">
                                {{ event.type_label }}
                                <template v-if="event.company">
                                    · {{ event.company.name }}</template
                                >
                                <template v-if="event.location">
                                    · {{ event.location }}</template
                                >
                                <template v-if="event.status === 'done'">
                                    · done</template
                                >
                            </span>
                        </span>
                    </li>
                    <li
                        v-for="task in tasksOn(date)"
                        :key="`task-${task.id}`"
                        class="flex items-center gap-3 px-3 py-2 text-sm"
                    >
                        <span class="w-24 shrink-0 text-muted-foreground"
                            >Due</span
                        >
                        <ListChecks class="size-4 text-amber-600" />
                        <span>
                            {{ task.title }}
                            <span
                                v-if="task.project"
                                class="text-xs text-muted-foreground"
                                >· {{ task.project }}</span
                            >
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <EventDialog
            v-model:open="dialog"
            :event="selected"
            :start="newStart"
            :links="links"
            :users="users"
            :types="types"
        />
    </div>
</template>
