<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/projects';
import type { IdName, Option, Project } from '@/types';

export type ProjectPrefill = {
    title: string | null;
    company: IdName | null;
    contact: IdName | null;
    deal: { id: number; title: string } | null;
    quotation: { id: number; number: string | null } | null;
    billing_method: string;
    budget_amount: string | null;
    currency: string;
    start_date: string;
    responsible_user_id: number | null;
};

const props = defineProps<{
    project?: Project | null;
    prefill?: ProjectPrefill;
    users: IdName[];
    statuses: Option[];
    billingMethods: Option[];
    currencies: Option[];
}>();

const { slug, currency: teamCurrency } = useTeam();

const form = useForm({
    title: props.project?.title ?? props.prefill?.title ?? '',
    description: props.project?.description ?? '',
    company_id: props.project?.company_id ?? props.prefill?.company?.id ?? null,
    contact_id: props.project?.contact_id ?? props.prefill?.contact?.id ?? null,
    deal_id: props.project?.deal_id ?? props.prefill?.deal?.id ?? null,
    quotation_id:
        props.project?.quotation_id ?? props.prefill?.quotation?.id ?? null,
    responsible_user_id:
        props.project?.responsible_user_id ??
        props.prefill?.responsible_user_id ??
        null,
    member_ids: (props.project?.members ?? []).map((member) => member.id),
    status: props.project?.status ?? 'active',
    billing_method:
        props.project?.billing_method ??
        props.prefill?.billing_method ??
        'time_and_materials',
    start_date: props.project?.start_date ?? props.prefill?.start_date ?? '',
    due_date: props.project?.due_date ?? '',
    budget_amount:
        props.project?.budget_amount ?? props.prefill?.budget_amount ?? '',
    budget_hours: props.project?.budget_hours ?? '',
    hourly_rate: props.project?.hourly_rate ?? '',
    currency:
        props.project?.currency ??
        props.prefill?.currency ??
        teamCurrency.value,
});

const toggleMember = (id: number, on: boolean) => {
    form.member_ids = on
        ? [...form.member_ids, id]
        : form.member_ids.filter((member) => member !== id);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        start_date: data.start_date || null,
        due_date: data.due_date || null,
        budget_amount: data.budget_amount === '' ? null : data.budget_amount,
        budget_hours: data.budget_hours === '' ? null : data.budget_hours,
        hourly_rate: data.hourly_rate === '' ? null : data.hourly_rate,
    }));

    if (props.project) {
        form.put(update([slug.value, props.project.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.project
    ? show([slug.value, props.project.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Project</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field
                    label="Name"
                    :error="form.errors.title"
                    class="sm:col-span-2"
                    required
                >
                    <Input
                        v-model="form.title"
                        required
                        placeholder="e.g. New website for Indus Traders"
                    />
                </Field>
                <Field label="Status" :error="form.errors.status">
                    <SelectInput v-model="form.status" :options="statuses" />
                </Field>
                <Field
                    label="Responsible"
                    :error="form.errors.responsible_user_id"
                >
                    <SelectInput
                        v-model="form.responsible_user_id"
                        :options="idOptions(users)"
                        placeholder="Nobody"
                    />
                </Field>
                <Field
                    label="Customer"
                    :error="form.errors.company_id"
                    class="lg:col-span-2"
                >
                    <EntitySelect
                        v-model="form.company_id"
                        type="companies"
                        :initial-label="
                            project?.company?.name ?? prefill?.company?.name
                        "
                        placeholder="Choose a company…"
                        clearable
                        @select="form.contact_id = null"
                    />
                </Field>
                <Field
                    label="Contact person"
                    :error="form.errors.contact_id"
                    class="lg:col-span-2"
                >
                    <EntitySelect
                        v-model="form.contact_id"
                        type="contacts"
                        :params="{ company: form.company_id }"
                        :initial-label="
                            project?.contact?.name ?? prefill?.contact?.name
                        "
                        placeholder="Choose a contact…"
                        clearable
                    />
                </Field>
                <Field label="Start" :error="form.errors.start_date">
                    <Input v-model="form.start_date" type="date" />
                </Field>
                <Field label="Deadline" :error="form.errors.due_date">
                    <Input v-model="form.due_date" type="date" />
                </Field>
                <div
                    v-if="
                        (project?.deal ?? prefill?.deal) ||
                        (project?.quotation ?? prefill?.quotation)
                    "
                    class="flex flex-col justify-center text-sm sm:col-span-2"
                >
                    <p v-if="project?.deal ?? prefill?.deal">
                        <span class="text-muted-foreground">Deal:</span>
                        {{ (project?.deal ?? prefill?.deal)?.title }}
                    </p>
                    <p v-if="project?.quotation ?? prefill?.quotation">
                        <span class="text-muted-foreground">Quotation:</span>
                        {{ (project?.quotation ?? prefill?.quotation)?.number }}
                    </p>
                </div>
                <Field
                    label="Description"
                    :error="form.errors.description"
                    class="sm:col-span-2 lg:col-span-4"
                >
                    <Textarea v-model="form.description" rows="3" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Budget & billing</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field label="Billing" :error="form.errors.billing_method">
                    <SelectInput
                        v-model="form.billing_method"
                        :options="billingMethods"
                    />
                </Field>
                <Field
                    :label="
                        form.billing_method === 'fixed_price'
                            ? 'Fixed price (excl. tax)'
                            : 'Budget (excl. tax)'
                    "
                    :error="form.errors.budget_amount"
                >
                    <Input
                        v-model="form.budget_amount"
                        type="number"
                        step="0.01"
                        min="0"
                    />
                </Field>
                <Field
                    label="Budget in hours"
                    :error="form.errors.budget_hours"
                >
                    <Input
                        v-model="form.budget_hours"
                        type="number"
                        step="0.25"
                        min="0"
                    />
                </Field>
                <Field
                    label="Hourly rate"
                    hint="Used when the work type has no rate"
                    :error="form.errors.hourly_rate"
                >
                    <Input
                        v-model="form.hourly_rate"
                        type="number"
                        step="0.01"
                        min="0"
                    />
                </Field>
                <Field label="Currency" :error="form.errors.currency">
                    <SelectInput
                        v-model="form.currency"
                        :options="
                            currencies.map((c) => ({
                                value: c.value,
                                label: c.value,
                            }))
                        "
                    />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Team</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <label
                        v-for="user in users"
                        :key="user.id"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :model-value="
                                form.member_ids.includes(user.id) ||
                                form.responsible_user_id === user.id
                            "
                            :disabled="form.responsible_user_id === user.id"
                            @update:model-value="
                                toggleMember(user.id, Boolean($event))
                            "
                        />
                        {{ user.name }}
                    </label>
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ project ? 'Save changes' : 'Create project' }}
            </Button>
        </div>
    </form>
</template>
