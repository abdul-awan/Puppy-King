<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import TaskDialog from '@/components/projects/TaskDialog.vue';
import TaskList from '@/components/projects/TaskList.vue';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { index } from '@/routes/tasks';
import type {
    IdName,
    Option,
    Paginated,
    Task,
    Team,
    WorkTypeOption,
} from '@/types';

const props = defineProps<{
    tasks: Paginated<Task>;
    filters: {
        search: string;
        status: string;
        assignee: string;
        project: number | null;
        due: string | null;
    };
    users: IdName[];
    projects: IdName[];
    statuses: Option[];
    priorities: Option[];
    workTypes: WorkTypeOption[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [{ title: 'Tasks', href: index(props.currentTeam.slug) }],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const dialog = ref(false);
const editing = ref<Task | null>(null);

const open = (task: Task | null) => {
    editing.value = task;
    dialog.value = true;
};

const statusOptions = [
    { value: 'open', label: 'Open' },
    { value: 'all', label: 'All' },
    ...props.statuses,
];

const assigneeOptions = [
    { value: 'me', label: 'My tasks' },
    { value: 'all', label: 'Everybody' },
    ...props.users.map((user) => ({
        value: String(user.id),
        label: user.name,
    })),
];

const dueOptions = [
    { value: 'overdue', label: 'Overdue' },
    { value: 'today', label: 'Due today' },
    { value: 'week', label: 'Due within a week' },
];
</script>

<template>
    <Head title="Tasks" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Tasks"
            description="What needs to be done, and by whom."
        >
            <template #actions>
                <Button
                    v-if="canEdit('projects')"
                    size="sm"
                    @click="open(null)"
                >
                    <Plus /> New task
                </Button>
            </template>
        </PageHeader>

        <div
            class="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center"
        >
            <SearchInput v-model="filters.search" placeholder="Search tasks…" />
            <SelectInput
                v-model="filters.assignee"
                :options="assigneeOptions"
                class="lg:w-44"
            />
            <SelectInput
                v-model="filters.status"
                :options="statusOptions"
                class="lg:w-36"
            />
            <SelectInput
                v-model="filters.due"
                :options="dueOptions"
                placeholder="Any due date"
                class="lg:w-48"
            />
            <SelectInput
                v-model="filters.project"
                :options="idOptions(projects)"
                placeholder="All projects"
                class="lg:w-56"
            />
        </div>

        <TaskList
            :tasks="tasks.data"
            :can-write="canEdit('projects')"
            show-project
            empty-text="Nothing to do here."
            @edit="open"
        />

        <Pagination :meta="tasks.meta" :links="tasks.links" />

        <TaskDialog
            v-model:open="dialog"
            :task="editing"
            :users="users"
            :work-types="workTypes"
            :priorities="priorities"
            :statuses="statuses"
        />
    </div>
</template>
