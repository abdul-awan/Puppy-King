<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import { formatDuration } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { durationInput, parseDuration } from '@/lib/time';
import { plan } from '@/routes/planning';
import type { IdName } from '@/types';

export type PlannableTask = {
    id: number;
    title: string;
    project?: string | null;
    assignee_id?: number | null;
    planned_date?: string | null;
    planned_minutes?: number | null;
    estimated_minutes?: number | null;
};

const props = defineProps<{
    task: PlannableTask | null;
    defaultDate: string;
    defaultUserId?: number | null;
    users: IdName[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    assignee_id: null as number | null,
    planned_date: '',
    duration: '',
});

watch(open, (isOpen) => {
    if (!isOpen || !props.task) {
        return;
    }

    form.clearErrors();
    form.assignee_id =
        props.defaultUserId ??
        props.task.assignee_id ??
        props.users[0]?.id ??
        null;
    form.planned_date = props.task.planned_date ?? props.defaultDate;
    form.duration = durationInput(
        props.task.planned_minutes ?? props.task.estimated_minutes ?? 60,
    );
});

const minutes = computed(() => parseDuration(form.duration));

const save = (remove = false) => {
    if (!props.task) {
        return;
    }

    form.transform((data) => ({
        assignee_id: data.assignee_id,
        planned_date: remove ? null : data.planned_date,
        planned_minutes: remove ? null : minutes.value,
    })).patch(plan([slug.value, props.task.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Plan task</DialogTitle>
                <DialogDescription v-if="task">
                    {{ task.title }}
                    <template v-if="task.project">
                        · {{ task.project }}</template
                    >
                </DialogDescription>
            </DialogHeader>

            <form id="plan-task" class="space-y-4" @submit.prevent="save()">
                <Field label="Who" :error="errors.assignee_id" required>
                    <SelectInput
                        v-model="form.assignee_id"
                        :options="idOptions(users)"
                    />
                </Field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Day" :error="errors.planned_date" required>
                        <Input
                            v-model="form.planned_date"
                            type="date"
                            required
                        />
                    </Field>
                    <Field
                        label="Time needed"
                        :hint="
                            minutes
                                ? `= ${formatDuration(minutes)}`
                                : 'e.g. 2h or 1:30'
                        "
                        :error="errors.planned_minutes"
                    >
                        <Input v-model="form.duration" />
                    </Field>
                </div>
            </form>

            <DialogFooter class="gap-2">
                <Button
                    v-if="task?.planned_date"
                    variant="ghost"
                    class="mr-auto"
                    :disabled="form.processing"
                    @click="save(true)"
                >
                    Take out of planning
                </Button>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="plan-task"
                    :disabled="form.processing"
                >
                    Plan
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
