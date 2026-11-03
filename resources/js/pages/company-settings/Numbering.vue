<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Field from '@/components/app/Field.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { edit, update } from '@/routes/settings/numbering';
import type { Team } from '@/types';

type NumberingType = {
    type: string;
    label: string;
    pattern: string;
    padding: number;
    next: string;
    per_department: boolean;
};

const props = defineProps<{
    types: NumberingType[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Numbering', href: edit(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const form = useForm({
    numbering: Object.fromEntries(
        props.types.map((type) => [
            type.type,
            {
                pattern: type.pattern,
                padding: type.padding,
                next_number: null as number | null,
            },
        ]),
    ),
});

const submit = () => {
    form.put(update(slug.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            for (const type of props.types) {
                form.numbering[type.type].next_number = null;
            }
        },
    });
};
</script>

<template>
    <Head title="Numbering" />

    <form class="space-y-6" @submit.prevent="submit">
        <Heading
            variant="small"
            title="Document numbering"
            description="Numbers are handed out in order without gaps. Use {FY} for the fiscal year (2026-27), {FYS} for its short form (2627), {YYYY}, {YY} or {MM} for the date, and {SEQ} for the running number. The counter restarts every fiscal year when the pattern contains {FY} or {FYS}."
        />

        <div class="divide-y rounded-lg border">
            <div
                v-for="type in types"
                :key="type.type"
                class="grid gap-3 p-4 sm:grid-cols-[10rem_1fr_6rem_9rem] sm:items-start"
            >
                <div class="pt-2">
                    <p class="font-medium">{{ type.label }}</p>
                    <p class="text-xs text-muted-foreground">
                        Next: <span class="font-mono">{{ type.next }}</span>
                    </p>
                    <p
                        v-if="type.per_department"
                        class="text-xs text-muted-foreground"
                    >
                        Counted per company profile
                    </p>
                </div>
                <Field
                    label="Pattern"
                    :error="form.errors[`numbering.${type.type}.pattern`]"
                >
                    <Input
                        v-model="form.numbering[type.type].pattern"
                        class="font-mono"
                        :disabled="!canEdit('settings')"
                    />
                </Field>
                <Field
                    label="Digits"
                    :error="form.errors[`numbering.${type.type}.padding`]"
                >
                    <Input
                        v-model="form.numbering[type.type].padding"
                        type="number"
                        min="1"
                        max="10"
                        :disabled="!canEdit('settings')"
                    />
                </Field>
                <Field
                    label="Start from"
                    hint="Leave empty to continue"
                    :error="form.errors[`numbering.${type.type}.next_number`]"
                >
                    <Input
                        v-model="form.numbering[type.type].next_number"
                        type="number"
                        min="1"
                        :disabled="!canEdit('settings')"
                    />
                </Field>
            </div>
        </div>

        <Button
            v-if="canEdit('settings')"
            type="submit"
            :disabled="form.processing"
        >
            Save numbering
        </Button>
    </form>
</template>
