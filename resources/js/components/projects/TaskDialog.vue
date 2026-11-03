<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { durationInput, parseDuration } from '@/lib/time';
import { store, update } from '@/routes/tasks';
import type { IdName, Milestone, Option, Task, WorkTypeOption } from '@/types';

const props = defineProps<{
    task?: Task | null;
    /** Fixes the project (tasks created on a project page). */
    projectId?: number | null;
    milestones?: Milestone[];
    defaultMilestoneId?: number | null;
    users: IdName[];
    workTypes: WorkTypeOption[];
    priorities: Option[];
    statuses: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    title: '',
    description: '',
    project_id: null as number | null,
    milestone_id: null as number | null,
    assignee_id: null as number | null,
    status: 'todo',
    priority: 'normal',
    due_date: '',
    estimate: '',
    work_type_id: null as number | null,
    is_billable: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.title = props.task?.title ?? '';
    form.description = props.task?.description ?? '';
    form.project_id = props.task?.project_id ?? props.projectId ?? null;
    form.milestone_id =
        props.task?.milestone_id ?? props.defaultMilestoneId ?? null;
    form.assignee_id = props.task?.assignee_id ?? null;
    form.status = props.task?.status ?? 'todo';
    form.priority = props.task?.priority ?? 'normal';
    form.due_date = props.task?.due_date ?? '';
    form.estimate = durationInput(props.task?.estimated_minutes);
    form.work_type_id = props.task?.work_type_id ?? null;
    form.is_billable = props.task?.is_billable ?? true;
});

const submit = () => {
    form.transform((data) => ({
        title: data.title,
        description: data.description || null,
        project_id: data.project_id,
        milestone_id: data.project_id ? data.milestone_id : null,
        assignee_id: data.assignee_id,
        status: data.status,
        priority: data.priority,
        due_date: data.due_date || null,
        estimated_minutes: parseDuration(data.estimate),
        work_type_id: data.work_type_id,
        is_billable: data.is_billable,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.task) {
        form.put(update([slug.value, props.task.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ task ? 'Edit task' : 'New task' }}</DialogTitle>
            </DialogHeader>

            <form id="task-form" class="space-y-4" @submit.prevent="submit">
                <Field label="Task" :error="form.errors.title" required>
                    <Input v-model="form.title" required v-focus />
                </Field>
                <Field
                    v-if="!projectId"
                    label="Project"
                    :error="form.errors.project_id"
                >
                    <EntitySelect
                        v-model="form.project_id"
                        type="projects"
                        :initial-label="task?.project?.title"
                        placeholder="No project"
                        clearable
                    />
                </Field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Assigned to" :error="form.errors.assignee_id">
                        <SelectInput
                            v-model="form.assignee_id"
                            :options="idOptions(users)"
                            placeholder="Nobody"
                        />
                    </Field>
                    <Field label="Due" :error="form.errors.due_date">
                        <Input v-model="form.due_date" type="date" />
                    </Field>
                    <Field label="Priority" :error="form.errors.priority">
                        <SelectInput
                            v-model="form.priority"
                            :options="priorities"
                        />
                    </Field>
                    <Field
                        v-if="task"
                        label="Status"
                        :error="form.errors.status"
                    >
                        <SelectInput
                            v-model="form.status"
                            :options="statuses"
                        />
                    </Field>
                    <Field
                        v-if="
                            milestones?.length && (projectId || form.project_id)
                        "
                        label="Milestone"
                        :error="form.errors.milestone_id"
                    >
                        <SelectInput
                            v-model="form.milestone_id"
                            :options="idOptions(milestones)"
                            placeholder="None"
                        />
                    </Field>
                    <Field
                        label="Estimate"
                        hint="e.g. 2h, 1:30 or 45m"
                        :error="
                            (form.errors as Record<string, string | undefined>)
                                .estimated_minutes
                        "
                    >
                        <Input v-model="form.estimate" />
                    </Field>
                    <Field label="Work type" :error="form.errors.work_type_id">
                        <SelectInput
                            v-model="form.work_type_id"
                            :options="idOptions(workTypes)"
                            placeholder="None"
                        />
                    </Field>
                </div>
                <Field label="Details" :error="form.errors.description">
                    <Textarea v-model="form.description" rows="3" />
                </Field>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.is_billable"
                        @update:model-value="form.is_billable = Boolean($event)"
                    />
                    Time on this task is billable
                </label>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="task-form"
                    :disabled="form.processing"
                >
                    {{ task ? 'Save' : 'Add task' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
