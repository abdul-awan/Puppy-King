<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarPlus,
    CheckCircle2,
    ClipboardPen,
    Clock,
    Pencil,
    Plus,
    ReceiptText,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import InputError from '@/components/InputError.vue';
import InvoiceProjectDialog from '@/components/projects/InvoiceProjectDialog.vue';
import MaterialDialog from '@/components/projects/MaterialDialog.vue';
import TaskDialog from '@/components/projects/TaskDialog.vue';
import TaskList from '@/components/projects/TaskList.vue';
import TimeEntryDialog from '@/components/time/TimeEntryDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDuration, formatMoney } from '@/lib/format';
import { index as calendarIndex } from '@/routes/calendar';
import { show as showCompany } from '@/routes/companies';
import { show as showDeal } from '@/routes/deals';
import { destroy as destroyMaterial } from '@/routes/materials';
import { destroy as destroyMilestone } from '@/routes/milestones';
import { destroy, edit, index, show, status } from '@/routes/projects';
import { store as storeMilestone } from '@/routes/projects/milestones';
import { show as showQuotation } from '@/routes/quotations';
import { destroy as destroyTime } from '@/routes/time';
import { create as createWorkOrder } from '@/routes/work-orders';
import type {
    Attachment,
    DocumentSummary,
    IdName,
    Milestone,
    Option,
    Project,
    ProjectMaterial,
    ProjectMetrics,
    Task,
    TaxRateOption,
    Team,
    TimeEntry,
    TimelineItem,
    WorkTypeOption,
} from '@/types';

const props = defineProps<{
    project: Project;
    metrics: ProjectMetrics;
    milestones: Milestone[];
    tasks: Task[];
    timeEntries: TimeEntry[];
    materials: ProjectMaterial[];
    invoices: DocumentSummary[];
    expenses: DocumentSummary[];
    timeline: TimelineItem[];
    attachments: Attachment[];
    users: IdName[];
    workTypes: WorkTypeOption[];
    taxRates: TaxRateOption[];
    statuses: Option[];
    taskStatuses: Option[];
    priorities: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; project: Project }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index(props.currentTeam.slug) },
            {
                title: props.project.title,
                href: show([props.currentTeam.slug, props.project.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const canWrite = computed(() => canEdit('projects'));

// Dialog state.
const taskDialog = ref(false);
const editingTask = ref<Task | null>(null);
const newTaskMilestone = ref<number | null>(null);
const timeDialog = ref(false);
const editingEntry = ref<TimeEntry | null>(null);
const materialDialog = ref(false);
const editingMaterial = ref<ProjectMaterial | null>(null);
const invoicing = ref(false);
const deleting = ref(false);

const openTask = (task: Task | null, milestoneId: number | null = null) => {
    editingTask.value = task;
    newTaskMilestone.value = milestoneId;
    taskDialog.value = true;
};

const openTime = (entry: TimeEntry | null) => {
    editingEntry.value = entry;
    timeDialog.value = true;
};

const openMaterial = (material: ProjectMaterial | null) => {
    editingMaterial.value = material;
    materialDialog.value = true;
};

// Tasks grouped by milestone (tasks without one come first).
const groups = computed(() => [
    {
        milestone: null as Milestone | null,
        tasks: props.tasks.filter((task) => task.milestone_id === null),
    },
    ...props.milestones.map((milestone) => ({
        milestone,
        tasks: props.tasks.filter((task) => task.milestone_id === milestone.id),
    })),
]);

const milestoneForm = useForm({ name: '', due_date: '' });

const addMilestone = () => {
    milestoneForm
        .transform((data) => ({ ...data, due_date: data.due_date || null }))
        .post(storeMilestone([slug.value, props.project.id]).url, {
            preserveScroll: true,
            onSuccess: () => milestoneForm.reset(),
        });
};

const removeMilestone = (milestone: Milestone) => {
    if (
        window.confirm(
            `Delete the milestone "${milestone.name}"? Its tasks stay on the project.`,
        )
    ) {
        router.delete(destroyMilestone([slug.value, milestone.id]).url, {
            preserveScroll: true,
        });
    }
};

const removeEntry = (entry: TimeEntry) => {
    if (window.confirm('Delete this time entry?')) {
        router.delete(destroyTime([slug.value, entry.id]).url, {
            preserveScroll: true,
        });
    }
};

const removeMaterial = (material: ProjectMaterial) => {
    if (window.confirm(`Remove "${material.description}"?`)) {
        router.delete(destroyMaterial([slug.value, material.id]).url, {
            preserveScroll: true,
        });
    }
};

const setStatus = (value: string) => {
    router.patch(
        status([slug.value, props.project.id]).url,
        { status: value },
        { preserveScroll: true },
    );
};

const percent = (value: number | null | undefined) =>
    Math.min(100, Math.max(0, value ?? 0));

const hoursPercent = computed(() =>
    props.metrics.budget_minutes
        ? Math.round(
              (props.metrics.logged_minutes / props.metrics.budget_minutes) *
                  100,
          )
        : null,
);
</script>

<template>
    <Head :title="project.title" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="project.title">
            <template #badges>
                <Pill :color="project.status_color">{{
                    project.status_label
                }}</Pill>
                <Pill v-if="project.is_overdue" color="red">Overdue</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    {{ project.number }} · {{ project.billing_method_label }}
                    <template v-if="project.company">
                        ·
                        <Link
                            :href="showCompany([slug, project.company.id]).url"
                            class="hover:underline"
                            >{{ project.company.name }}</Link
                        >
                    </template>
                    <template v-if="project.deal">
                        · Deal
                        <Link
                            :href="showDeal([slug, project.deal.id]).url"
                            class="hover:underline"
                            >{{ project.deal.title }}</Link
                        >
                    </template>
                    <template v-if="project.quotation">
                        · Quotation
                        <Link
                            :href="
                                showQuotation([slug, project.quotation.id]).url
                            "
                            class="hover:underline"
                            >{{ project.quotation.number }}</Link
                        >
                    </template>
                    <template v-if="project.start_date || project.due_date">
                        · {{ formatDate(project.start_date) }} →
                        {{ formatDate(project.due_date) }}
                    </template>
                </p>
            </template>
            <template #actions>
                <Button
                    v-if="canEdit('calendar')"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="
                            calendarIndex(slug, {
                                query: { plan: 1, project: project.id },
                            }).url
                        "
                        ><CalendarPlus /> Plan</Link
                    >
                </Button>
                <Button
                    v-if="canEdit('time_tracking')"
                    variant="outline"
                    size="sm"
                    @click="openTime(null)"
                >
                    <Clock /> Log time
                </Button>
                <Button
                    v-if="canEdit('work_orders')"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="
                            createWorkOrder(slug, {
                                query: { project: project.id },
                            }).url
                        "
                        ><ClipboardPen /> Work order</Link
                    >
                </Button>
                <Button
                    v-if="
                        canEdit('invoicing') &&
                        project.billing_method !== 'non_billable'
                    "
                    variant="outline"
                    size="sm"
                    @click="invoicing = true"
                >
                    <ReceiptText /> Invoice
                </Button>
                <template v-if="canWrite">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="deleting = true"
                    >
                        <Trash2 />
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="edit([slug, project.id]).url"
                            ><Pencil /> Edit</Link
                        >
                    </Button>
                    <Button
                        v-if="project.is_open"
                        size="sm"
                        @click="setStatus('done')"
                    >
                        <CheckCircle2 /> Mark as done
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="setStatus('active')"
                    >
                        <RotateCcw /> Reopen
                    </Button>
                </template>
            </template>
        </PageHeader>

        <InputError :message="errors.project" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardContent class="space-y-2 pt-6">
                    <p class="text-sm text-muted-foreground">Tasks done</p>
                    <p class="text-2xl font-semibold">
                        {{ metrics.tasks_done }} / {{ metrics.tasks_total }}
                    </p>
                    <div class="h-2 rounded-full bg-muted">
                        <div
                            class="h-2 rounded-full bg-primary"
                            :style="{
                                width: `${percent(metrics.progress_percent)}%`,
                            }"
                        />
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-2 pt-6">
                    <p class="text-sm text-muted-foreground">Time logged</p>
                    <p class="text-2xl font-semibold">
                        {{ formatDuration(metrics.logged_minutes) }}
                        <span
                            v-if="metrics.budget_minutes"
                            class="text-sm font-normal text-muted-foreground"
                        >
                            of {{ formatDuration(metrics.budget_minutes) }}
                        </span>
                    </p>
                    <div
                        v-if="hoursPercent !== null"
                        class="h-2 rounded-full bg-muted"
                    >
                        <div
                            class="h-2 rounded-full"
                            :class="
                                hoursPercent > 100
                                    ? 'bg-destructive'
                                    : 'bg-primary'
                            "
                            :style="{ width: `${percent(hoursPercent)}%` }"
                        />
                    </div>
                    <p v-else class="text-xs text-muted-foreground">
                        {{ formatDuration(metrics.billable_minutes) }} billable
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-2 pt-6">
                    <p class="text-sm text-muted-foreground">
                        Value of the work
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(metrics.work_value, project.currency) }}
                    </p>
                    <template v-if="metrics.budget_used_percent !== null">
                        <div class="h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full"
                                :class="
                                    metrics.budget_used_percent > 100
                                        ? 'bg-destructive'
                                        : 'bg-primary'
                                "
                                :style="{
                                    width: `${percent(metrics.budget_used_percent)}%`,
                                }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ metrics.budget_used_percent }}% of
                            {{
                                formatMoney(
                                    project.budget_amount,
                                    project.currency,
                                )
                            }}
                        </p>
                    </template>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 pt-6 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Not invoiced</span>
                        <span class="font-medium">{{
                            formatMoney(
                                metrics.unbilled_value,
                                project.currency,
                            )
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Invoiced</span>
                        <span>{{
                            formatMoney(metrics.invoiced, project.currency)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Costs</span>
                        <span>{{
                            formatMoney(
                                metrics.expenses + metrics.materials_cost,
                                project.currency,
                            )
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Tabs default-value="tasks">
            <TabsList class="flex-wrap">
                <TabsTrigger value="tasks"
                    >Tasks ({{ tasks.length }})</TabsTrigger
                >
                <TabsTrigger value="time">Time</TabsTrigger>
                <TabsTrigger value="materials"
                    >Materials ({{ materials.length }})</TabsTrigger
                >
                <TabsTrigger value="money">Invoices & costs</TabsTrigger>
                <TabsTrigger value="files"
                    >Files ({{ attachments.length }})</TabsTrigger
                >
                <TabsTrigger value="timeline">History</TabsTrigger>
            </TabsList>

            <TabsContent value="tasks" class="space-y-4">
                <div
                    v-for="group in groups"
                    :key="group.milestone?.id ?? 'none'"
                >
                    <div
                        v-if="group.milestone || milestones.length"
                        class="mb-2 flex items-center justify-between gap-2"
                    >
                        <h3 class="text-sm font-semibold">
                            {{ group.milestone?.name ?? 'Without milestone' }}
                            <span
                                v-if="group.milestone?.due_date"
                                class="font-normal text-muted-foreground"
                            >
                                · {{ formatDate(group.milestone.due_date) }}
                            </span>
                        </h3>
                        <div v-if="canWrite" class="flex gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="
                                    openTask(null, group.milestone?.id ?? null)
                                "
                            >
                                <Plus /> Task
                            </Button>
                            <Button
                                v-if="group.milestone"
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                aria-label="Delete milestone"
                                @click="removeMilestone(group.milestone)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                    <TaskList
                        v-if="
                            group.milestone ||
                            group.tasks.length ||
                            !milestones.length
                        "
                        :tasks="group.tasks"
                        :can-write="canWrite"
                        empty-text="No tasks yet."
                        @edit="openTask"
                    />
                </div>

                <div
                    v-if="canWrite"
                    class="flex flex-wrap items-end gap-2 rounded-md border border-dashed p-3"
                >
                    <Button
                        v-if="!milestones.length"
                        size="sm"
                        @click="openTask(null)"
                    >
                        <Plus /> Add task
                    </Button>
                    <form
                        class="flex flex-wrap items-center gap-2"
                        @submit.prevent="addMilestone"
                    >
                        <Input
                            v-model="milestoneForm.name"
                            placeholder="New milestone (e.g. Design)"
                            class="w-56"
                            aria-label="Milestone name"
                        />
                        <Input
                            v-model="milestoneForm.due_date"
                            type="date"
                            class="w-40"
                            aria-label="Milestone date"
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            :disabled="
                                !milestoneForm.name || milestoneForm.processing
                            "
                        >
                            Add milestone
                        </Button>
                    </form>
                </div>
            </TabsContent>

            <TabsContent value="time">
                <Card>
                    <CardContent class="pt-6">
                        <p
                            v-if="timeEntries.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No time logged yet.
                        </p>
                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Who</TableHead>
                                    <TableHead>Work</TableHead>
                                    <TableHead class="text-right"
                                        >Time</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Value</TableHead
                                    >
                                    <TableHead class="w-20" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="entry in timeEntries"
                                    :key="entry.id"
                                >
                                    <TableCell
                                        class="text-sm whitespace-nowrap"
                                        >{{ formatDate(entry.date) }}</TableCell
                                    >
                                    <TableCell class="text-sm">{{
                                        entry.user?.name ?? '—'
                                    }}</TableCell>
                                    <TableCell class="text-sm">
                                        {{
                                            entry.description ||
                                            entry.task?.title ||
                                            '—'
                                        }}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            <template
                                                v-if="
                                                    entry.task &&
                                                    entry.description
                                                "
                                                >{{
                                                    entry.task.title
                                                }}</template
                                            >
                                            <template v-if="entry.work_type">
                                                · {{ entry.work_type.name }}
                                            </template>
                                            <template v-if="entry.invoice">
                                                · invoiced ({{
                                                    entry.invoice.number ??
                                                    'draft'
                                                }})
                                            </template>
                                            <template v-if="!entry.is_billable">
                                                · not billable
                                            </template>
                                        </p>
                                    </TableCell>
                                    <TableCell
                                        class="text-right text-sm whitespace-nowrap"
                                    >
                                        {{
                                            entry.is_running
                                                ? 'running…'
                                                : formatDuration(
                                                      entry.duration_minutes,
                                                  )
                                        }}
                                    </TableCell>
                                    <TableCell
                                        class="text-right text-sm whitespace-nowrap"
                                    >
                                        {{
                                            entry.is_billable
                                                ? formatMoney(
                                                      entry.value,
                                                      project.currency,
                                                  )
                                                : '—'
                                        }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <template
                                            v-if="
                                                canEdit('time_tracking') &&
                                                !entry.invoice_id &&
                                                !entry.is_running
                                            "
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7"
                                                aria-label="Edit time"
                                                @click="openTime(entry)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7"
                                                aria-label="Delete time"
                                                @click="removeEntry(entry)"
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </template>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="materials">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>Materials</CardTitle>
                        <Button
                            v-if="canWrite"
                            size="sm"
                            variant="outline"
                            @click="openMaterial(null)"
                        >
                            <Plus /> Add material
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="materials.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No materials used yet.
                        </p>
                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Material</TableHead>
                                    <TableHead class="text-right"
                                        >Qty</TableHead
                                    >
                                    <TableHead class="text-right"
                                        >Value</TableHead
                                    >
                                    <TableHead class="w-20" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="material in materials"
                                    :key="material.id"
                                >
                                    <TableCell
                                        class="text-sm whitespace-nowrap"
                                        >{{
                                            formatDate(material.date)
                                        }}</TableCell
                                    >
                                    <TableCell class="text-sm">
                                        {{ material.description }}
                                        <p
                                            v-if="
                                                material.invoice ||
                                                !material.is_billable
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                material.invoice
                                                    ? `invoiced (${material.invoice.number ?? 'draft'})`
                                                    : 'not billable'
                                            }}
                                        </p>
                                    </TableCell>
                                    <TableCell
                                        class="text-right text-sm whitespace-nowrap"
                                    >
                                        {{ Number(material.quantity) }}
                                        {{ material.unit }}
                                    </TableCell>
                                    <TableCell
                                        class="text-right text-sm whitespace-nowrap"
                                    >
                                        {{
                                            formatMoney(
                                                material.value,
                                                project.currency,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <template
                                            v-if="
                                                canWrite && !material.invoice_id
                                            "
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7"
                                                aria-label="Edit material"
                                                @click="openMaterial(material)"
                                            >
                                                <Pencil class="size-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="size-7"
                                                aria-label="Remove material"
                                                @click="
                                                    removeMaterial(material)
                                                "
                                            >
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </template>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="money" class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Invoices</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="invoices.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Not invoiced yet.
                        </p>
                        <ul v-else class="divide-y rounded-md border text-sm">
                            <li
                                v-for="item in invoices"
                                :key="item.id"
                                class="flex items-center justify-between gap-2 px-3 py-2"
                            >
                                <Link
                                    :href="item.url"
                                    class="font-medium hover:underline"
                                >
                                    {{ item.number ?? 'Draft' }}
                                </Link>
                                <span class="text-xs text-muted-foreground">{{
                                    formatDate(item.date)
                                }}</span>
                                <Pill :color="item.status_color">{{
                                    item.status_label
                                }}</Pill>
                                <span class="font-medium">{{
                                    formatMoney(item.total, item.currency)
                                }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Costs (bills)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="expenses.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No bills linked to this project. Choose the project
                            when you enter an expense.
                        </p>
                        <ul v-else class="divide-y rounded-md border text-sm">
                            <li
                                v-for="item in expenses"
                                :key="item.id"
                                class="flex items-center justify-between gap-2 px-3 py-2"
                            >
                                <Link
                                    :href="item.url"
                                    class="font-medium hover:underline"
                                >
                                    {{ item.number }}
                                </Link>
                                <span
                                    class="min-w-0 flex-1 truncate text-muted-foreground"
                                    >{{ item.customer }}</span
                                >
                                <span class="font-medium">{{
                                    formatMoney(item.total, item.currency)
                                }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="files">
                <Card>
                    <CardContent class="pt-6">
                        <AttachmentsPanel
                            :attachments="attachments"
                            attachable-type="project"
                            :attachable-id="project.id"
                            :can-write="canWrite"
                        />
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="timeline">
                <Card>
                    <CardContent class="pt-6">
                        <Timeline
                            :items="timeline"
                            notable-type="project"
                            :notable-id="project.id"
                            :can-write="canWrite"
                        />
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <TaskDialog
            v-model:open="taskDialog"
            :task="editingTask"
            :project-id="project.id"
            :milestones="milestones"
            :default-milestone-id="newTaskMilestone"
            :users="users"
            :work-types="workTypes"
            :priorities="priorities"
            :statuses="taskStatuses"
        />

        <TimeEntryDialog
            v-model:open="timeDialog"
            :entry="editingEntry"
            :project-id="project.id"
            :project-label="project.title"
            :work-types="workTypes"
        />

        <MaterialDialog
            v-model:open="materialDialog"
            :project-id="project.id"
            :material="editingMaterial"
            :tax-rates="taxRates"
        />

        <InvoiceProjectDialog
            v-model:open="invoicing"
            :project="project"
            :metrics="metrics"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete this project?"
            description="The project and its tasks are deleted. Logged time and invoices are kept."
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, project.id]).url)"
        />
    </div>
</template>
