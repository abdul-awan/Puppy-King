<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import Pill from '@/components/app/Pill.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDuration } from '@/lib/format';
import { show as projectRoute } from '@/routes/projects';
import { complete, destroy } from '@/routes/tasks';
import type { Task } from '@/types';

withDefaults(
    defineProps<{
        tasks: Task[];
        canWrite: boolean;
        showProject?: boolean;
        emptyText?: string;
    }>(),
    {
        showProject: false,
        emptyText: 'No tasks.',
    },
);

const emit = defineEmits<{
    edit: [task: Task];
}>();

const { slug } = useTeam();

const toggle = (task: Task) => {
    router.patch(
        complete([slug.value, task.id]).url,
        {},
        { preserveScroll: true },
    );
};

const remove = (task: Task) => {
    if (!window.confirm(`Delete the task "${task.title}"?`)) {
        return;
    }

    router.delete(destroy([slug.value, task.id]).url, { preserveScroll: true });
};
</script>

<template>
    <p v-if="tasks.length === 0" class="py-2 text-sm text-muted-foreground">
        {{ emptyText }}
    </p>
    <ul v-else class="divide-y rounded-md border">
        <li
            v-for="task in tasks"
            :key="task.id"
            class="group flex items-start gap-3 px-3 py-2"
        >
            <Checkbox
                :model-value="task.status === 'done'"
                :disabled="!canWrite"
                class="mt-0.5"
                :aria-label="`Mark “${task.title}” as done`"
                @update:model-value="toggle(task)"
            />
            <div class="min-w-0 flex-1">
                <p
                    class="text-sm"
                    :class="{
                        'text-muted-foreground line-through':
                            task.status === 'done',
                    }"
                >
                    {{ task.title }}
                    <Pill
                        v-if="task.status === 'in_progress'"
                        color="blue"
                        class="ml-1"
                        >In progress</Pill
                    >
                    <Pill
                        v-if="
                            task.priority === 'high' ||
                            task.priority === 'urgent'
                        "
                        :color="task.priority_color"
                        class="ml-1"
                        >{{ task.priority_label }}</Pill
                    >
                </p>
                <p class="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                    <Link
                        v-if="showProject && task.project"
                        :href="projectRoute([slug, task.project.id]).url"
                        class="hover:underline"
                    >
                        {{ task.project.title }}
                    </Link>
                    <span v-else-if="showProject && task.company">{{
                        task.company.name
                    }}</span>
                    <span v-if="task.assignee">{{ task.assignee.name }}</span>
                    <span
                        v-if="task.due_date"
                        :class="{ 'text-destructive': task.is_overdue }"
                    >
                        due {{ formatDate(task.due_date) }}
                    </span>
                    <span v-if="task.logged_minutes || task.estimated_minutes">
                        {{ formatDuration(task.logged_minutes ?? 0) }}
                        <template v-if="task.estimated_minutes">
                            / {{ formatDuration(task.estimated_minutes) }}
                        </template>
                    </span>
                </p>
            </div>
            <div
                v-if="canWrite"
                class="flex opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
            >
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    aria-label="Edit task"
                    @click="emit('edit', task)"
                >
                    <Pencil class="size-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-7"
                    aria-label="Delete task"
                    @click="remove(task)"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>
        </li>
    </ul>
</template>
