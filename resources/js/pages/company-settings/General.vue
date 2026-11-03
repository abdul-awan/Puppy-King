<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import { edit, update } from '@/routes/settings/general';
import type { Option, Team } from '@/types';

const props = defineProps<{
    settings: {
        currency: string;
        fiscal_year_start_month: number;
        further_tax_enabled: boolean;
        further_tax_rate: string;
        quotation_validity_days: number;
        reminders: { enabled: boolean; days_after_due: number[] };
        daily_capacity_minutes: number;
        working_days: number[];
        support_enabled: boolean;
    };
    supportUrl: string | null;
    currencies: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Company settings', href: edit(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();

const months: Option<number>[] = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
].map((label, index) => ({ value: index + 1, label }));

const form = useForm({
    currency: props.settings.currency,
    fiscal_year_start_month: props.settings.fiscal_year_start_month,
    further_tax_enabled: props.settings.further_tax_enabled,
    further_tax_rate: props.settings.further_tax_rate,
    quotation_validity_days: props.settings.quotation_validity_days,
    reminders: {
        enabled: props.settings.reminders.enabled,
        days_after_due: [...props.settings.reminders.days_after_due],
    },
    daily_capacity_hours: props.settings.daily_capacity_minutes / 60,
    working_days: [...props.settings.working_days],
    support_enabled: props.settings.support_enabled,
});

const copySupportUrl = async () => {
    if (props.supportUrl) {
        await navigator.clipboard.writeText(props.supportUrl);
        toast.success(
            'Link copied. Put it on your website or in your e-mail signature.',
        );
    }
};

const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(
    (label, index) => ({ value: index + 1, label }),
);

const toggleWorkingDay = (day: number, on: boolean) => {
    form.working_days = on
        ? [...form.working_days, day].sort((a, b) => a - b)
        : form.working_days.filter((value) => value !== day);
};

const newReminderDay = ref<number | null>(null);

const addReminderDay = () => {
    const day = Number(newReminderDay.value);

    if (
        day > 0 &&
        !form.reminders.days_after_due.includes(day) &&
        form.reminders.days_after_due.length < 5
    ) {
        form.reminders.days_after_due = [
            ...form.reminders.days_after_due,
            day,
        ].sort((a, b) => a - b);
    }

    newReminderDay.value = null;
};

const submit = () => {
    form.transform(({ daily_capacity_hours, ...data }) => ({
        ...data,
        daily_capacity_minutes: Math.round(Number(daily_capacity_hours) * 60),
    })).put(update(slug.value).url, { preserveScroll: true });
};
</script>

<template>
    <Head title="Company settings" />

    <form class="space-y-10" @submit.prevent="submit">
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Money & fiscal year"
                description="Pakistan's fiscal year runs from July to June. Document numbers restart every fiscal year."
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Default currency" :error="form.errors.currency">
                    <SelectInput
                        v-model="form.currency"
                        :options="currencies"
                    />
                </Field>
                <Field
                    label="Fiscal year starts in"
                    :error="form.errors.fiscal_year_start_month"
                >
                    <SelectInput
                        v-model="form.fiscal_year_start_month"
                        :options="months"
                    />
                </Field>
            </div>
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                title="Further tax"
                description="Extra sales tax charged when you sell to buyers who are not registered for sales tax. Check the current rate with your tax adviser — it changes with the budget."
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <Checkbox
                        :model-value="form.further_tax_enabled"
                        @update:model-value="
                            form.further_tax_enabled = Boolean($event)
                        "
                    />
                    Charge further tax to unregistered buyers
                </label>
                <Field
                    v-if="form.further_tax_enabled"
                    label="Further tax rate (%)"
                    :error="form.errors.further_tax_rate"
                >
                    <Input
                        v-model="form.further_tax_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                    />
                </Field>
            </div>
        </div>

        <div class="space-y-6">
            <Heading variant="small" title="Quotations & reminders" />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Quotations are valid for (days)"
                    :error="form.errors.quotation_validity_days"
                >
                    <Input
                        v-model="form.quotation_validity_days"
                        type="number"
                        min="1"
                        max="365"
                    />
                </Field>
                <div />
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <Checkbox
                        :model-value="form.reminders.enabled"
                        @update:model-value="
                            form.reminders.enabled = Boolean($event)
                        "
                    />
                    Automatically e-mail payment reminders for overdue invoices
                </label>
                <Field
                    v-if="form.reminders.enabled"
                    label="Send a reminder this many days after the due date"
                    :error="form.errors['reminders.days_after_due']"
                    class="sm:col-span-2"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-for="day in form.reminders.days_after_due"
                            :key="day"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-sm"
                        >
                            {{ day }} days
                            <button
                                type="button"
                                class="opacity-60 hover:opacity-100"
                                @click="
                                    form.reminders.days_after_due =
                                        form.reminders.days_after_due.filter(
                                            (d) => d !== day,
                                        )
                                "
                            >
                                <X class="size-3.5" />
                            </button>
                        </span>
                        <Input
                            v-model="newReminderDay"
                            type="number"
                            min="1"
                            max="365"
                            placeholder="Days"
                            class="w-24"
                            @keydown.enter.prevent="addReminderDay"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addReminderDay"
                        >
                            <Plus /> Add
                        </Button>
                    </div>
                </Field>
            </div>
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                title="Planning"
                description="The working days and hours per day, used to show on the planning board how busy everyone is."
            />
            <Field
                label="Working hours per day"
                :error="
                    (form.errors as Record<string, string>)
                        .daily_capacity_minutes
                "
                class="sm:max-w-xs"
            >
                <Input
                    v-model="form.daily_capacity_hours"
                    type="number"
                    step="0.5"
                    min="1"
                    max="24"
                />
            </Field>
            <Field label="Working days" :error="form.errors.working_days">
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="day in weekDays"
                        :key="day.value"
                        class="flex items-center gap-1.5 text-sm"
                    >
                        <Checkbox
                            :model-value="form.working_days.includes(day.value)"
                            @update:model-value="
                                toggleWorkingDay(day.value, Boolean($event))
                            "
                        />
                        {{ day.label }}
                    </label>
                </div>
            </Field>
        </div>

        <div class="space-y-4">
            <Heading
                variant="small"
                title="Support form"
                description="A public page where customers can ask a question; every question becomes a ticket."
            />
            <label class="flex items-start gap-2 text-sm">
                <Checkbox
                    :model-value="form.support_enabled"
                    class="mt-0.5"
                    @update:model-value="form.support_enabled = Boolean($event)"
                />
                <span>Switch on the support form</span>
            </label>
            <div
                v-if="supportUrl && settings.support_enabled"
                class="flex flex-wrap items-center gap-2 rounded-md border bg-muted/40 p-3 text-sm"
            >
                <span class="break-all">{{ supportUrl }}</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="copySupportUrl"
                >
                    Copy link
                </Button>
            </div>
            <p
                v-else-if="form.support_enabled"
                class="text-xs text-muted-foreground"
            >
                Save the settings to get the link of your support form.
            </p>
        </div>

        <Button type="submit" :disabled="form.processing">Save settings</Button>
    </form>
</template>
