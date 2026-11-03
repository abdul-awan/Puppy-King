<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import Field from '@/components/app/Field.vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Heading from '@/components/Heading.vue';
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
import { formatMoney } from '@/lib/format';
import { edit as settingsHome } from '@/routes/settings/general';
import { index, update } from '@/routes/settings/members';
import type { Option, Team } from '@/types';

type Member = {
    user_id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    full_access: boolean;
    access: Record<string, string>;
    hourly_rate: string | null;
    daily_capacity_minutes: number;
};

const props = defineProps<{
    members: Member[];
    modules: Option[];
    levels: Option[];
    canManage: boolean;
    teamsUrl: string;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Members & access', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();

const editing = ref<Member | null>(null);
const open = ref(false);

const form = useForm({
    access: {} as Record<string, string>,
    hourly_rate: null as string | null,
    daily_capacity_hours: 8,
});

const levelLabel = (value: string) =>
    props.levels.find((level) => level.value === value)?.label ?? value;

const startEdit = (member: Member) => {
    editing.value = member;
    form.defaults({
        access: { ...member.access },
        hourly_rate: member.hourly_rate,
        daily_capacity_hours: member.daily_capacity_minutes / 60,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
};

const submit = () => {
    if (!editing.value) {
        return;
    }

    form.transform(({ daily_capacity_hours, ...data }) => ({
        ...data,
        daily_capacity_minutes: Math.round(Number(daily_capacity_hours) * 60),
    })).put(update([slug.value, editing.value.user_id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Head title="Members & access" />

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Members & access"
                description="Choose what each member can see and change. Owners and admins always have full access."
            />
            <Button variant="outline" size="sm" as-child>
                <Link :href="teamsUrl"><ExternalLink /> Invite members</Link>
            </Button>
        </div>

        <div class="space-y-3">
            <div
                v-for="member in members"
                :key="member.user_id"
                class="space-y-3 rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ member.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ member.email }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Pill :color="member.full_access ? 'indigo' : 'gray'">{{
                            member.role_label
                        }}</Pill>
                        <Button
                            v-if="canManage"
                            size="sm"
                            variant="outline"
                            @click="startEdit(member)"
                        >
                            Edit access
                        </Button>
                    </div>
                </div>
                <p
                    v-if="member.full_access"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <ShieldCheck class="size-4" /> Full access to every module.
                </p>
                <div v-else class="flex flex-wrap gap-1.5">
                    <Pill
                        v-for="module in modules"
                        :key="module.value"
                        :color="
                            member.access[module.value] === 'write'
                                ? 'green'
                                : member.access[module.value] === 'read'
                                  ? 'blue'
                                  : 'gray'
                        "
                    >
                        {{ module.label }}:
                        {{ levelLabel(member.access[module.value]) }}
                    </Pill>
                </div>
                <p class="text-xs text-muted-foreground">
                    Hourly rate:
                    {{
                        member.hourly_rate
                            ? formatMoney(member.hourly_rate, currency)
                            : 'not set'
                    }}
                    · Works {{ member.daily_capacity_minutes / 60 }} h per day
                </p>
            </div>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Access for {{ editing?.name }}</DialogTitle>
                    <DialogDescription v-if="editing?.full_access">
                        {{ editing.role_label }}s always have full access; you
                        can still set the hourly rate and working hours.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submit">
                    <div
                        v-if="!editing?.full_access"
                        class="grid gap-3 sm:grid-cols-2"
                    >
                        <Field
                            v-for="module in modules"
                            :key="module.value"
                            :label="module.label"
                        >
                            <SelectInput
                                v-model="form.access[module.value]"
                                :options="levels"
                            />
                        </Field>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Field
                            label="Hourly rate"
                            :error="form.errors.hourly_rate"
                            hint="Used to value tracked time"
                        >
                            <Input
                                v-model="form.hourly_rate"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </Field>
                        <Field
                            label="Working hours per day"
                            :error="
                                (form.errors as Record<string, string>)
                                    .daily_capacity_minutes
                            "
                        >
                            <Input
                                v-model="form.daily_capacity_hours"
                                type="number"
                                step="0.5"
                                min="0"
                                max="24"
                            />
                        </Field>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="form.processing"
                            >Save</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
