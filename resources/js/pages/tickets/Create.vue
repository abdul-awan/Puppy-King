<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { create, index, store } from '@/routes/tickets';
import type { IdName, Option, Team } from '@/types';

const props = defineProps<{
    prefill: { company: IdName | null; contact: IdName | null };
    users: IdName[];
    statuses: Option[];
    priorities: Option[];
    channels: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Tickets', href: index(props.currentTeam.slug) },
            { title: 'New ticket', href: create(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();

const form = useForm({
    subject: '',
    company_id: props.prefill.company?.id ?? null,
    contact_id: props.prefill.contact?.id ?? null,
    customer_name: '',
    customer_email: '',
    priority: 'normal',
    channel: 'phone',
    assignee_id: null as number | null,
    project_id: null as number | null,
    description: '',
    files: [] as File[],
});

const onFiles = (event: Event) => {
    form.files = Array.from((event.target as HTMLInputElement).files ?? []);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        customer_name: data.customer_name || null,
        customer_email: data.customer_email || null,
    })).post(store(slug.value).url, { forceFormData: form.files.length > 0 });
};
</script>

<template>
    <Head title="New ticket" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="New ticket"
            description="Write down what the customer asked, e.g. after a phone call."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <Card>
                <CardContent class="grid gap-4 pt-6 sm:grid-cols-2">
                    <Field
                        label="Subject"
                        :error="form.errors.subject"
                        class="sm:col-span-2"
                        required
                    >
                        <Input
                            v-model="form.subject"
                            required
                            placeholder="e.g. Printer on the 2nd floor does not work"
                        />
                    </Field>
                    <Field label="Company" :error="form.errors.company_id">
                        <EntitySelect
                            v-model="form.company_id"
                            type="companies"
                            :initial-label="prefill.company?.name"
                            placeholder="Choose a company…"
                            clearable
                        />
                    </Field>
                    <Field
                        label="Contact person"
                        :error="form.errors.contact_id"
                    >
                        <EntitySelect
                            v-model="form.contact_id"
                            type="contacts"
                            :params="{ company: form.company_id }"
                            :initial-label="prefill.contact?.name"
                            placeholder="Choose a contact…"
                            clearable
                        />
                    </Field>
                    <template v-if="!form.company_id && !form.contact_id">
                        <Field
                            label="Customer name"
                            hint="When the customer is not in your contacts"
                            :error="form.errors.customer_name"
                        >
                            <Input v-model="form.customer_name" />
                        </Field>
                        <Field
                            label="Customer e-mail"
                            hint="Replies are sent here"
                            :error="form.errors.customer_email"
                        >
                            <Input v-model="form.customer_email" type="email" />
                        </Field>
                    </template>
                    <Field label="Came in by" :error="form.errors.channel">
                        <SelectInput
                            v-model="form.channel"
                            :options="channels"
                        />
                    </Field>
                    <Field label="Priority" :error="form.errors.priority">
                        <SelectInput
                            v-model="form.priority"
                            :options="priorities"
                        />
                    </Field>
                    <Field label="Handled by" :error="form.errors.assignee_id">
                        <SelectInput
                            v-model="form.assignee_id"
                            :options="idOptions(users)"
                            placeholder="Nobody yet"
                        />
                    </Field>
                    <Field label="Project" :error="form.errors.project_id">
                        <EntitySelect
                            v-model="form.project_id"
                            type="projects"
                            placeholder="None"
                            clearable
                        />
                    </Field>
                    <Field
                        label="What does the customer ask?"
                        :error="form.errors.description"
                        class="sm:col-span-2"
                    >
                        <Textarea v-model="form.description" rows="5" />
                    </Field>
                    <Field
                        label="Files"
                        hint="Screenshots, photos…"
                        :error="form.errors.files"
                        class="sm:col-span-2"
                    >
                        <Input type="file" multiple @change="onFiles" />
                    </Field>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="index(slug).url">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing"
                    >Create ticket</Button
                >
            </div>
        </form>
    </div>
</template>
