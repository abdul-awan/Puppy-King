<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Play, Square } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { start as startTimer, stop as stopTimer } from '@/routes/timer';

/**
 * The timer in the header: shows the running timer (with a stop button) or starts one.
 */
const page = usePage();
const { slug } = useTeam();
const { canEdit } = usePermissions();

const timer = computed(() => page.props.timer);
const now = ref(Date.now());
let interval: number | undefined;

onMounted(() => {
    interval = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => window.clearInterval(interval));

const elapsed = computed(() => {
    if (!timer.value?.started_at) {
        return '';
    }

    const seconds =
        Math.max(
            0,
            Math.floor((now.value - Date.parse(timer.value.started_at)) / 1000),
        ) +
        timer.value.minutes_before * 60;
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
});

const starting = ref(false);
const form = useForm({
    project_id: null as number | null,
    task_id: null as number | null,
    description: '',
});

watch(
    () => form.project_id,
    () => {
        form.task_id = null;
    },
);

const start = () => {
    form.post(startTimer(slug.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            starting.value = false;
            form.reset();
        },
    });
};

const stop = () => {
    router.post(stopTimer(slug.value).url, {}, { preserveScroll: true });
};
</script>

<template>
    <div
        v-if="timer"
        class="flex items-center gap-2 rounded-md border px-2 py-1"
    >
        <span class="relative flex size-2">
            <span
                class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75"
            />
            <span class="relative inline-flex size-2 rounded-full bg-red-500" />
        </span>
        <span class="font-mono text-sm tabular-nums">{{ elapsed }}</span>
        <span
            class="hidden max-w-40 truncate text-xs text-muted-foreground lg:inline"
        >
            {{ timer.description || timer.project || 'Timer' }}
        </span>
        <Button
            variant="ghost"
            size="icon"
            class="size-7"
            aria-label="Stop the timer"
            @click="stop"
        >
            <Square class="size-3.5 fill-current" />
        </Button>
    </div>
    <Button
        v-else-if="canEdit('time_tracking')"
        variant="outline"
        size="sm"
        @click="starting = true"
    >
        <Play /> <span class="hidden sm:inline">Start timer</span>
    </Button>

    <Dialog v-model:open="starting">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Start the timer</DialogTitle>
            </DialogHeader>
            <form id="start-timer" class="space-y-4" @submit.prevent="start">
                <Field label="Project" :error="form.errors.project_id">
                    <EntitySelect
                        v-model="form.project_id"
                        type="projects"
                        placeholder="Choose a project…"
                        clearable
                    />
                </Field>
                <Field
                    v-if="form.project_id"
                    label="Task"
                    :error="form.errors.task_id"
                >
                    <EntitySelect
                        v-model="form.task_id"
                        type="tasks"
                        :params="{ project: form.project_id }"
                        placeholder="Choose a task…"
                        clearable
                    />
                </Field>
                <Field
                    label="What are you working on?"
                    :error="form.errors.description"
                >
                    <Input v-model="form.description" v-focus />
                </Field>
            </form>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="starting = false"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    form="start-timer"
                    :disabled="form.processing"
                >
                    <Play /> Start
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
