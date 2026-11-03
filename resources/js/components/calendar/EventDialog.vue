<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, Check, Trash2 } from '@lucide/vue';
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
import { toDateInput, today } from '@/lib/format';
import { complete, destroy, ics, store, update } from '@/routes/calendar';
import type { CalendarEventItem, IdName, Option } from '@/types';

export type EventLinks = {
    company?: IdName | null;
    contact?: IdName | null;
    deal?: { id: number; title: string } | null;
    project?: { id: number; title: string } | null;
};

const props = defineProps<{
    event?: CalendarEventItem | null;
    /** Start of a new event, e.g. "2026-10-02T10:00" (a clicked slot). */
    start?: string | null;
    /** Record(s) a new event is about, e.g. when planned from a company page. */
    links?: EventLinks;
    users: IdName[];
    types: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const reminderOptions = [
    { value: 0, label: 'At the start' },
    { value: 10, label: '10 minutes before' },
    { value: 30, label: '30 minutes before' },
    { value: 60, label: '1 hour before' },
    { value: 1440, label: '1 day before' },
];

const form = useForm({
    type: 'meeting',
    title: '',
    description: '',
    location: '',
    all_day: false,
    date: today(),
    start_time: '10:00',
    end_date: today(),
    end_time: '11:00',
    company_id: null as number | null,
    contact_id: null as number | null,
    deal_id: null as number | null,
    project_id: null as number | null,
    reminder_minutes: null as number | null,
    attendee_ids: [] as number[],
    status: 'planned',
    outcome: '',
});

const addHour = (time: string) => {
    const [hours, minutes] = time.split(':').map(Number);

    return `${String(Math.min(23, (hours ?? 0) + 1)).padStart(2, '0')}:${String(minutes ?? 0).padStart(2, '0')}`;
};

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const event = props.event;
    const start = event?.starts_at ?? props.start ?? `${today()}T10:00`;
    const [date, time] = start.split('T');
    const [endDate, endTime] = (
        event?.ends_at ?? `${date}T${addHour(time ?? '10:00')}`
    ).split('T');

    form.clearErrors();
    form.type = event?.type ?? 'meeting';
    form.title = event?.title ?? '';
    form.description = event?.description ?? '';
    form.location = event?.location ?? '';
    form.all_day = event?.all_day ?? false;
    form.date = date ?? today();
    form.start_time = time ?? '10:00';
    form.end_date = endDate ?? form.date;
    form.end_time = endTime ?? '11:00';
    form.company_id = event?.company_id ?? props.links?.company?.id ?? null;
    form.contact_id = event?.contact_id ?? props.links?.contact?.id ?? null;
    form.deal_id = event?.deal_id ?? props.links?.deal?.id ?? null;
    form.project_id = event?.project_id ?? props.links?.project?.id ?? null;
    form.reminder_minutes = event?.reminder_minutes ?? null;
    form.attendee_ids = (event?.attendees ?? []).map((attendee) => attendee.id);
    form.status = event?.status ?? 'planned';
    form.outcome = event?.outcome ?? '';
});

// Keep the end after the start when the start moves.
watch(
    () => form.date,
    (date, previous) => {
        if (form.end_date === previous || form.end_date < date) {
            form.end_date = date;
        }
    },
);

const toggleAttendee = (id: number, on: boolean) => {
    form.attendee_ids = on
        ? [...form.attendee_ids, id]
        : form.attendee_ids.filter((attendee) => attendee !== id);
};

// Event times are local ("2026-10-02T10:00"), so compare with the local time.
const nowLocal = () => {
    const now = new Date();

    return `${toDateInput(now)}T${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
};

const past = computed(
    () =>
        props.event !== null &&
        props.event !== undefined &&
        props.event.starts_at <= nowLocal(),
);

const submit = () => {
    form.transform((data) => ({
        type: data.type,
        title: data.title,
        description: data.description || null,
        location: data.location || null,
        all_day: data.all_day,
        starts_at: data.all_day ? data.date : `${data.date} ${data.start_time}`,
        ends_at: data.all_day
            ? data.end_date
            : `${data.end_date} ${data.end_time}`,
        company_id: data.company_id,
        contact_id: data.contact_id,
        deal_id: data.deal_id,
        project_id: data.project_id,
        reminder_minutes: data.reminder_minutes,
        attendee_ids: data.attendee_ids,
        status: data.status,
        outcome: data.outcome || null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.event) {
        form.put(update([slug.value, props.event.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};

const markDone = () => {
    if (!props.event) {
        return;
    }

    router.post(
        complete([slug.value, props.event.id]).url,
        { outcome: form.outcome || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
};

const remove = () => {
    if (props.event && window.confirm('Delete this event?')) {
        router.delete(destroy([slug.value, props.event.id]).url, {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    }
};

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{
                    event ? 'Event' : 'Plan an event'
                }}</DialogTitle>
            </DialogHeader>

            <form id="event-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field label="Type" :error="errors.type">
                        <SelectInput v-model="form.type" :options="types" />
                    </Field>
                    <Field
                        label="Title"
                        :error="errors.title"
                        class="sm:col-span-2"
                        required
                    >
                        <Input
                            v-model="form.title"
                            required
                            placeholder="e.g. Demo at Indus Traders"
                            v-focus
                        />
                    </Field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="From" :error="errors.starts_at">
                        <div class="flex gap-2">
                            <Input v-model="form.date" type="date" />
                            <Input
                                v-if="!form.all_day"
                                v-model="form.start_time"
                                type="time"
                                class="w-28"
                                aria-label="Start time"
                            />
                        </div>
                    </Field>
                    <Field label="To" :error="errors.ends_at">
                        <div class="flex gap-2">
                            <Input v-model="form.end_date" type="date" />
                            <Input
                                v-if="!form.all_day"
                                v-model="form.end_time"
                                type="time"
                                class="w-28"
                                aria-label="End time"
                            />
                        </div>
                    </Field>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.all_day"
                        @update:model-value="form.all_day = Boolean($event)"
                    />
                    All day
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Location" :error="errors.location">
                        <Input
                            v-model="form.location"
                            placeholder="Office, address or phone number"
                        />
                    </Field>
                    <Field label="Reminder" :error="errors.reminder_minutes">
                        <SelectInput
                            v-model="form.reminder_minutes"
                            :options="reminderOptions"
                            placeholder="No reminder"
                        />
                    </Field>
                    <Field label="Company" :error="errors.company_id">
                        <EntitySelect
                            v-model="form.company_id"
                            type="companies"
                            :initial-label="
                                event?.company?.name ?? links?.company?.name
                            "
                            placeholder="None"
                            clearable
                        />
                    </Field>
                    <Field label="Contact" :error="errors.contact_id">
                        <EntitySelect
                            v-model="form.contact_id"
                            type="contacts"
                            :params="{ company: form.company_id }"
                            :initial-label="
                                event?.contact?.name ?? links?.contact?.name
                            "
                            placeholder="None"
                            clearable
                        />
                    </Field>
                    <Field
                        v-if="form.project_id || !form.deal_id"
                        label="Project"
                        :error="errors.project_id"
                    >
                        <EntitySelect
                            v-model="form.project_id"
                            type="projects"
                            :initial-label="
                                event?.project?.title ?? links?.project?.title
                            "
                            placeholder="None"
                            clearable
                        />
                    </Field>
                    <Field v-if="event?.deal ?? links?.deal" label="Deal">
                        <p class="flex h-9 items-center text-sm">
                            {{ (event?.deal ?? links?.deal)?.title }}
                        </p>
                    </Field>
                </div>

                <Field label="Who takes part">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="user in users"
                            :key="user.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="
                                    form.attendee_ids.includes(user.id)
                                "
                                @update:model-value="
                                    toggleAttendee(user.id, Boolean($event))
                                "
                            />
                            {{ user.name }}
                        </label>
                    </div>
                </Field>

                <Field label="Notes" :error="errors.description">
                    <Textarea v-model="form.description" rows="2" />
                </Field>

                <Field
                    v-if="event"
                    label="Outcome"
                    hint="What was agreed or said"
                    :error="errors.outcome"
                >
                    <Textarea v-model="form.outcome" rows="2" />
                </Field>
            </form>

            <DialogFooter class="flex-wrap gap-2">
                <template v-if="event">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="mr-auto"
                        aria-label="Delete event"
                        @click="remove"
                    >
                        <Trash2 />
                    </Button>
                    <Button variant="outline" as-child>
                        <a :href="ics([slug, event.id]).url"
                            ><CalendarPlus /> .ics</a
                        >
                    </Button>
                    <Button
                        v-if="event.status === 'planned' && past"
                        variant="outline"
                        @click="markDone"
                    >
                        <Check /> Done
                    </Button>
                </template>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="event-form"
                    :disabled="form.processing"
                >
                    {{ event ? 'Save' : 'Plan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
