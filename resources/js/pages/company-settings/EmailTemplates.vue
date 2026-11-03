<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Field from '@/components/app/Field.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { index, update } from '@/routes/settings/email-templates';
import type { Team } from '@/types';

type Template = {
    id: number;
    type: string;
    type_label: string;
    name: string;
    subject: string;
    body: string;
    placeholders: string[];
};

const props = defineProps<{
    templates: Template[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'E-mail templates', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const selectedId = ref(props.templates[0]?.id ?? 0);
const selected = () =>
    props.templates.find((template) => template.id === selectedId.value);

const form = useForm({
    subject: selected()?.subject ?? '',
    body: selected()?.body ?? '',
});

const select = (template: Template) => {
    selectedId.value = template.id;
    form.defaults({ subject: template.subject, body: template.body });
    form.reset();
    form.clearErrors();
};

const insert = (placeholder: string) => {
    form.body = `${form.body}${placeholder}`;
};

const submit = () => {
    form.put(update([slug.value, selectedId.value]).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="E-mail templates" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="E-mail templates"
            description="The text used when you e-mail documents to customers. Placeholders in curly braces are replaced automatically."
        />

        <div class="flex flex-wrap gap-2">
            <Button
                v-for="template in templates"
                :key="template.id"
                size="sm"
                :variant="template.id === selectedId ? 'default' : 'outline'"
                @click="select(template)"
            >
                {{ template.type_label }}
            </Button>
        </div>

        <form v-if="selected()" class="space-y-4" @submit.prevent="submit">
            <Field label="Subject" :error="form.errors.subject">
                <Input
                    v-model="form.subject"
                    :disabled="!canEdit('settings')"
                />
            </Field>
            <Field label="Message" :error="form.errors.body">
                <Textarea
                    v-model="form.body"
                    rows="12"
                    :disabled="!canEdit('settings')"
                />
            </Field>
            <div class="space-y-2">
                <p class="text-sm text-muted-foreground">
                    Placeholders (click to insert):
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="placeholder in selected()?.placeholders ?? []"
                        :key="placeholder"
                        type="button"
                        class="rounded-md border bg-muted px-2 py-0.5 font-mono text-xs hover:bg-accent"
                        :disabled="!canEdit('settings')"
                        @click="insert(placeholder)"
                    >
                        {{ placeholder }}
                    </button>
                </div>
            </div>
            <Button
                v-if="canEdit('settings')"
                type="submit"
                :disabled="form.processing"
            >
                Save template
            </Button>
        </form>
    </div>
</template>
