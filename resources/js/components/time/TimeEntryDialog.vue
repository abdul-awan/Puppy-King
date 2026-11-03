<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
import { formatDuration, today } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { durationInput, parseDuration } from '@/lib/time';
import { store, update } from '@/routes/time';
import type { IdName, LookupResult, TimeEntry, WorkTypeOption } from '@/types';

const props = defineProps<{
    entry?: TimeEntry | null;
    /** Pre-selected project (and task) when logging from a project page. */
    projectId?: number | null;
    projectLabel?: string | null;
    taskId?: number | null;
    taskLabel?: string | null;
    /** Support time logged from a ticket. */
    ticketId?: number | null;
    /** Hours of a work order (its project and customer are used). */
    workOrderId?: number | null;
    defaultDate?: string | null;
    workTypes: WorkTypeOption[];
    /** Owners and admins may log time for someone else. */
    users?: IdName[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    user_id: null as number | null,
    date: today(),
    duration: '',
    project_id: null as number | null,
    task_id: null as number | null,
    work_type_id: null as number | null,
    description: '',
    is_billable: true,
    hourly_rate: '',
});

const minutes = computed(() => parseDuration(form.duration));

const workOrder = computed(
    () => props.entry?.work_order_id ?? props.workOrderId ?? null,
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.user_id = props.entry?.user_id ?? null;
    form.date = props.entry?.date ?? props.defaultDate ?? today();
    form.duration = durationInput(props.entry?.duration_minutes);
    form.project_id = props.entry?.project_id ?? props.projectId ?? null;
    form.task_id = props.entry?.task_id ?? props.taskId ?? null;
    form.work_type_id = props.entry?.work_type_id ?? null;
    form.description = props.entry?.description ?? '';
    form.is_billable = props.entry?.is_billable ?? true;
    form.hourly_rate = props.entry
        ? String(Number(props.entry.hourly_rate))
        : '';
});

const onProjectSelected = (item: LookupResult | null) => {
    form.task_id = null;

    if (item?.data && item.data.billable === false) {
        form.is_billable = false;
    }
};

const onTaskSelected = (item: LookupResult | null) => {
    const data = item?.data as
        | { work_type_id?: number | null; billable?: boolean }
        | undefined;

    if (data?.work_type_id) {
        form.work_type_id = data.work_type_id;
    }

    if (data && data.billable === false) {
        form.is_billable = false;
    }
};

const onWorkTypeChanged = (id: number | null | undefined) => {
    const type = props.workTypes.find((workType) => workType.id === id);

    if (type && !type.is_billable) {
        form.is_billable = false;
    }
};

const submit = () => {
    form.transform((data) => ({
        user_id: data.user_id,
        date: data.date,
        duration_minutes: minutes.value ?? 0,
        project_id: data.project_id,
        task_id: data.task_id,
        ticket_id: props.entry?.ticket_id ?? props.ticketId ?? null,
        work_order_id: workOrder.value,
        work_type_id: data.work_type_id,
        description: data.description || null,
        is_billable: data.is_billable,
        hourly_rate: data.hourly_rate === '' ? null : data.hourly_rate,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.entry) {
        form.put(update([slug.value, props.entry.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    entry ? 'Edit time' : 'Log time'
                }}</DialogTitle>
            </DialogHeader>

            <form id="time-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Date" :error="form.errors.date" required>
                        <Input v-model="form.date" type="date" :max="today()" />
                    </Field>
                    <Field
                        label="Time worked"
                        :hint="
                            minutes
                                ? `= ${formatDuration(minutes)}`
                                : 'e.g. 1:30, 1.5 or 45m'
                        "
                        :error="
                            (form.errors as Record<string, string | undefined>)
                                .duration_minutes
                        "
                        required
                    >
                        <Input v-model="form.duration" required v-focus />
                    </Field>
                    <Field
                        v-if="users?.length && !entry"
                        label="For"
                        :error="form.errors.user_id"
                        class="sm:col-span-2"
                    >
                        <SelectInput
                            v-model="form.user_id"
                            :options="idOptions(users)"
                            placeholder="Myself"
                        />
                    </Field>
                    <Field
                        v-if="workOrder === null"
                        label="Project"
                        :error="form.errors.project_id"
                        class="sm:col-span-2"
                    >
                        <EntitySelect
                            v-model="form.project_id"
                            type="projects"
                            :initial-label="
                                entry?.project?.title ?? projectLabel
                            "
                            placeholder="No project"
                            clearable
                            @select="onProjectSelected"
                        />
                    </Field>
                    <Field
                        v-if="form.project_id && workOrder === null"
                        label="Task"
                        :error="form.errors.task_id"
                        class="sm:col-span-2"
                    >
                        <EntitySelect
                            v-model="form.task_id"
                            type="tasks"
                            :params="{ project: form.project_id }"
                            :initial-label="entry?.task?.title ?? taskLabel"
                            placeholder="No task"
                            clearable
                            @select="onTaskSelected"
                        />
                    </Field>
                    <Field label="Work type" :error="form.errors.work_type_id">
                        <SelectInput
                            v-model="form.work_type_id"
                            :options="idOptions(workTypes)"
                            placeholder="None"
                            @update:model-value="onWorkTypeChanged"
                        />
                    </Field>
                    <Field
                        label="Hourly rate"
                        hint="Empty: from the work type or project"
                        :error="form.errors.hourly_rate"
                    >
                        <Input
                            v-model="form.hourly_rate"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                    </Field>
                </div>
                <Field
                    label="What did you do?"
                    :error="form.errors.description"
                >
                    <Textarea v-model="form.description" rows="2" />
                </Field>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.is_billable"
                        @update:model-value="form.is_billable = Boolean($event)"
                    />
                    Billable to the customer
                </label>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="time-form"
                    :disabled="form.processing"
                >
                    {{ entry ? 'Save' : 'Log time' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
