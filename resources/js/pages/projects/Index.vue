<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FolderKanban, Plus } from '@lucide/vue';
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
import { formatDate, formatDuration, formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { create, index, show } from '@/routes/projects';
import type { IdName, Option, Paginated, Project, Team } from '@/types';

const props = defineProps<{
    projects: Paginated<Project>;
    filters: {
        search: string;
        status: string;
        company: number | null;
        responsible: number | null;
    };
    statuses: Option[];
    users: IdName[];
    summary: {
        open_count: number;
        overdue_count: number;
        unbilled_value: string;
    };
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();
const { filters } = useFilters(
    { ...props.filters },
    () => index(slug.value).url,
);

const statusOptions = [
    { value: 'open', label: 'Open projects' },
    { value: 'all', label: 'All projects' },
    ...props.statuses,
];

const progress = (project: Project) =>
    project.tasks_count
        ? Math.round(
              ((project.done_tasks_count ?? 0) / project.tasks_count) * 100,
          )
        : null;
</script>

<template>
    <Head title="Projects" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Projects"
            description="Work for your customers, from start to invoice."
        >
            <template #actions>
                <Button v-if="canEdit('projects')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New project</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">Open projects</p>
                    <p class="text-2xl font-semibold">
                        {{ summary.open_count }}
                    </p>
                    <p
                        class="text-xs"
                        :class="
                            summary.overdue_count
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ summary.overdue_count }} past the deadline
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        Not invoiced yet
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(summary.unbilled_value, currency) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Billable time and materials
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search number, name, customer…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statusOptions"
                class="lg:w-44"
            />
            <SelectInput
                v-model="filters.responsible"
                :options="idOptions(users)"
                placeholder="Anyone responsible"
                class="lg:w-52"
            />
        </div>

        <EmptyState
            v-if="projects.data.length === 0"
            :icon="FolderKanban"
            title="No projects found"
            description="Create a project to plan tasks, log time and invoice the work."
        >
            <Button
                v-if="canEdit('projects') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"><Plus /> New project</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Project</TableHead>
                        <TableHead class="hidden md:table-cell">
                            Responsible
                        </TableHead>
                        <TableHead class="hidden lg:table-cell">
                            Deadline
                        </TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="hidden w-40 sm:table-cell">
                            Tasks done
                        </TableHead>
                        <TableHead class="text-right">Time</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="project in projects.data"
                        :key="project.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, project.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ project.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ project.number }}
                                <template v-if="project.customer_name">
                                    · {{ project.customer_name }}
                                </template>
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            project.responsible_user?.name ?? '—'
                        }}</TableCell>
                        <TableCell
                            class="hidden text-sm lg:table-cell"
                            :class="{ 'text-destructive': project.is_overdue }"
                        >
                            {{ formatDate(project.due_date) }}
                        </TableCell>
                        <TableCell>
                            <Pill :color="project.status_color">{{
                                project.status_label
                            }}</Pill>
                        </TableCell>
                        <TableCell class="hidden sm:table-cell">
                            <div
                                v-if="progress(project) !== null"
                                class="flex items-center gap-2"
                            >
                                <div class="h-2 flex-1 rounded-full bg-muted">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :style="{
                                            width: `${progress(project)}%`,
                                        }"
                                    />
                                </div>
                                <span class="w-10 text-right text-xs">
                                    {{ progress(project) }}%
                                </span>
                            </div>
                            <span v-else class="text-xs text-muted-foreground">
                                No tasks
                            </span>
                        </TableCell>
                        <TableCell class="text-right text-sm whitespace-nowrap">
                            {{ formatDuration(project.logged_minutes ?? 0) }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="projects.meta" :links="projects.links" />
    </div>
</template>
