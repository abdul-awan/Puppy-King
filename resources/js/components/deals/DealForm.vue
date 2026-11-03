<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import CustomFieldInputs from '@/components/app/CustomFieldInputs.vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import TagPicker from '@/components/app/TagPicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/deals';
import type {
    CustomFieldDefinition,
    Deal,
    IdName,
    Option,
    PipelineOption,
    Tag,
} from '@/types';

const props = defineProps<{
    deal?: Deal | null;
    prefill?: {
        company: IdName | null;
        contact: IdName | null;
        pipeline_id: number | null;
    };
    pipelines: PipelineOption[];
    sources: IdName[];
    users: IdName[];
    departments: IdName[];
    currencies: Option[];
    defaultCurrency: string;
    tags: Tag[];
    customFields: CustomFieldDefinition[];
}>();

const { slug } = useTeam();

const initialPipeline =
    props.deal?.pipeline_id ??
    props.prefill?.pipeline_id ??
    props.pipelines[0]?.id ??
    null;

const form = useForm({
    title: props.deal?.title ?? '',
    company_id: props.deal?.company_id ?? props.prefill?.company?.id ?? null,
    contact_id: props.deal?.contact_id ?? props.prefill?.contact?.id ?? null,
    pipeline_id: initialPipeline,
    phase_id:
        props.deal?.phase_id ??
        props.pipelines.find((pipeline) => pipeline.id === initialPipeline)
            ?.phases[0]?.id ??
        null,
    value: props.deal?.value ?? '',
    currency: props.deal?.currency ?? props.defaultCurrency,
    probability: props.deal?.probability ?? null,
    expected_close_date: props.deal?.expected_close_date ?? '',
    responsible_user_id: props.deal?.responsible_user_id ?? null,
    source_id: props.deal?.source_id ?? null,
    department_id: props.deal?.department_id ?? null,
    description: props.deal?.description ?? '',
    tag_ids: props.deal?.tags?.map((tag) => tag.id) ?? [],
    custom_fields: { ...props.deal?.custom_fields },
});

const phases = computed(
    () =>
        props.pipelines.find((pipeline) => pipeline.id === form.pipeline_id)
            ?.phases ?? [],
);

const phaseProbability = computed(
    () =>
        phases.value.find((phase) => phase.id === form.phase_id)?.probability ??
        0,
);

watch(
    () => form.pipeline_id,
    () => {
        if (!phases.value.some((phase) => phase.id === form.phase_id)) {
            form.phase_id = phases.value[0]?.id ?? null;
        }
    },
);

// A different company usually means a different contact person.
watch(
    () => form.company_id,
    (value, previous) => {
        if (previous !== undefined && value !== previous) {
            form.contact_id = null;
        }
    },
);

const submit = () => {
    if (props.deal) {
        form.put(update([slug.value, props.deal.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.deal
    ? show([slug.value, props.deal.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Deal</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Title"
                    required
                    :error="form.errors.title"
                    class="sm:col-span-2"
                >
                    <Input
                        v-model="form.title"
                        v-focus
                        required
                        placeholder="e.g. 50 laptops for head office"
                    />
                </Field>
                <Field label="Company" :error="form.errors.company_id">
                    <EntitySelect
                        v-model="form.company_id"
                        type="companies"
                        :initial-label="
                            deal?.company?.name ?? prefill?.company?.name
                        "
                        placeholder="Choose a company…"
                        clearable
                    />
                </Field>
                <Field label="Contact person" :error="form.errors.contact_id">
                    <EntitySelect
                        v-model="form.contact_id"
                        type="contacts"
                        :params="{ company: form.company_id }"
                        :initial-label="
                            deal?.contact?.name ?? prefill?.contact?.name
                        "
                        placeholder="Choose a contact…"
                        clearable
                    />
                </Field>
                <Field label="Value" required :error="form.errors.value">
                    <div class="flex gap-2">
                        <Input
                            v-model="form.value"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                        <SelectInput
                            v-model="form.currency"
                            :options="
                                currencies.map((c) => ({
                                    value: c.value,
                                    label: c.value,
                                }))
                            "
                            class="w-24"
                        />
                    </div>
                </Field>
                <Field
                    label="Expected close date"
                    :error="form.errors.expected_close_date"
                >
                    <Input v-model="form.expected_close_date" type="date" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Pipeline</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <Field label="Pipeline" :error="form.errors.pipeline_id">
                    <SelectInput
                        v-model="form.pipeline_id"
                        :options="idOptions(pipelines)"
                    />
                </Field>
                <Field label="Phase" :error="form.errors.phase_id">
                    <SelectInput
                        v-model="form.phase_id"
                        :options="idOptions(phases)"
                    />
                </Field>
                <Field
                    label="Probability (%)"
                    :error="form.errors.probability"
                    :hint="`Empty = phase default (${phaseProbability}%)`"
                >
                    <Input
                        v-model="form.probability"
                        type="number"
                        min="0"
                        max="100"
                    />
                </Field>
                <Field
                    label="Responsible"
                    :error="form.errors.responsible_user_id"
                >
                    <SelectInput
                        v-model="form.responsible_user_id"
                        :options="idOptions(users)"
                        placeholder="—"
                    />
                </Field>
                <Field label="Source" :error="form.errors.source_id">
                    <SelectInput
                        v-model="form.source_id"
                        :options="idOptions(sources)"
                        placeholder="—"
                    />
                </Field>
                <Field
                    v-if="departments.length > 1"
                    label="Company profile"
                    :error="form.errors.department_id"
                >
                    <SelectInput
                        v-model="form.department_id"
                        :options="idOptions(departments)"
                        placeholder="Default"
                    />
                </Field>
                <Field label="Tags" class="sm:col-span-3">
                    <TagPicker v-model="form.tag_ids" :options="tags" />
                </Field>
                <Field
                    label="Description"
                    :error="form.errors.description"
                    class="sm:col-span-3"
                >
                    <Textarea v-model="form.description" rows="4" />
                </Field>
            </CardContent>
        </Card>

        <Card v-if="customFields.length > 0">
            <CardHeader>
                <CardTitle>Additional information</CardTitle>
            </CardHeader>
            <CardContent>
                <CustomFieldInputs
                    v-model="form.custom_fields"
                    :fields="customFields"
                    :errors="form.errors"
                />
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ deal ? 'Save changes' : 'Create deal' }}
            </Button>
        </div>
    </form>
</template>
