<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { today } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/work-orders';
import type { IdName, WorkOrder } from '@/types';

export type WorkOrderPrefill = {
    title: string;
    company: IdName | null;
    contact: IdName | null;
    project: IdName | null;
    ticket: IdName | null;
    location: string | null;
};

const props = defineProps<{
    workOrder?: WorkOrder | null;
    prefill?: WorkOrderPrefill;
    users: IdName[];
    departments: IdName[];
}>();

const { slug } = useTeam();
const page = usePage();

const initialLocation =
    props.workOrder?.location ?? props.prefill?.location ?? '';

const form = useForm({
    title: props.workOrder?.title ?? props.prefill?.title ?? '',
    date: props.workOrder?.date ?? today(),
    company_id:
        props.workOrder?.company_id ?? props.prefill?.company?.id ?? null,
    contact_id:
        props.workOrder?.contact_id ?? props.prefill?.contact?.id ?? null,
    project_id:
        props.workOrder?.project_id ?? props.prefill?.project?.id ?? null,
    ticket_id: props.workOrder?.ticket_id ?? props.prefill?.ticket?.id ?? null,
    assignee_id: props.workOrder
        ? props.workOrder.assignee_id
        : (page.props.auth.user?.id ?? null),
    department_id: props.workOrder?.department_id ?? null,
    location: initialLocation,
    description: props.workOrder?.description ?? '',
    internal_notes: props.workOrder?.internal_notes ?? '',
    show_prices: props.workOrder?.show_prices ?? false,
});

const ticketLabel = props.workOrder?.ticket
    ? `${props.workOrder.ticket.number ?? ''} ${props.workOrder.ticket.subject}`.trim()
    : (props.prefill?.ticket?.name ?? null);

// The suggested address belongs to the first customer; another customer gets its own.
const onCustomerChanged = () => {
    if (!props.workOrder && form.location === initialLocation) {
        form.location = '';
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        location: data.location || null,
        description: data.description || null,
        internal_notes: data.internal_notes || null,
    }));

    if (props.workOrder) {
        form.put(update([slug.value, props.workOrder.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.workOrder
    ? show([slug.value, props.workOrder.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Job</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="What is the job?"
                    :error="form.errors.title"
                    class="sm:col-span-2"
                    required
                >
                    <Input
                        v-model="form.title"
                        required
                        placeholder="e.g. Service of the air conditioners"
                    />
                </Field>
                <Field label="Date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field label="Done by" :error="form.errors.assignee_id">
                    <SelectInput
                        v-model="form.assignee_id"
                        :options="idOptions(users)"
                        placeholder="Nobody yet"
                    />
                </Field>
                <Field label="Company" :error="form.errors.company_id">
                    <EntitySelect
                        v-model="form.company_id"
                        type="companies"
                        :initial-label="
                            workOrder?.company?.name ?? prefill?.company?.name
                        "
                        placeholder="Choose a company…"
                        clearable
                        @select="onCustomerChanged"
                    />
                </Field>
                <Field label="Contact person" :error="form.errors.contact_id">
                    <EntitySelect
                        v-model="form.contact_id"
                        type="contacts"
                        :params="{ company: form.company_id }"
                        :initial-label="
                            workOrder?.contact?.name ?? prefill?.contact?.name
                        "
                        placeholder="Choose a contact…"
                        clearable
                        @select="onCustomerChanged"
                    />
                </Field>
                <Field label="Project" :error="form.errors.project_id">
                    <EntitySelect
                        v-model="form.project_id"
                        type="projects"
                        :initial-label="
                            workOrder?.project
                                ? `${workOrder.project.number ?? ''} ${workOrder.project.title}`.trim()
                                : prefill?.project?.name
                        "
                        placeholder="None"
                        clearable
                    />
                </Field>
                <Field
                    v-if="departments.length > 1"
                    label="Letterhead"
                    hint="Which of your departments does the work"
                    :error="form.errors.department_id"
                >
                    <SelectInput
                        v-model="form.department_id"
                        :options="idOptions(departments)"
                        placeholder="Default"
                    />
                </Field>
                <Field
                    v-if="form.ticket_id && ticketLabel"
                    label="For ticket"
                    :error="form.errors.ticket_id"
                >
                    <div
                        class="flex h-9 items-center justify-between gap-2 rounded-md border px-3 text-sm"
                    >
                        <span class="truncate">{{ ticketLabel }}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            aria-label="Remove the link to the ticket"
                            @click="form.ticket_id = null"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                </Field>
                <Field
                    label="Location of the work"
                    hint="Empty: the visiting address of the customer"
                    :error="form.errors.location"
                    class="sm:col-span-2"
                >
                    <Textarea v-model="form.location" rows="2" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Report</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <Field
                    label="Work carried out"
                    hint="Printed on the work order"
                    :error="form.errors.description"
                >
                    <Textarea
                        v-model="form.description"
                        rows="6"
                        placeholder="What was done, what was found, advice for the customer…"
                    />
                </Field>
                <Field
                    label="Internal notes"
                    hint="Only your team sees these"
                    :error="form.errors.internal_notes"
                >
                    <Textarea v-model="form.internal_notes" rows="3" />
                </Field>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.show_prices"
                        @update:model-value="form.show_prices = Boolean($event)"
                    />
                    Show rates and prices on the work order
                </label>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ workOrder ? 'Save' : 'Create work order' }}
            </Button>
        </div>
    </form>
</template>
