<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Field from '@/components/app/Field.vue';
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
import { formatDuration, formatMoney } from '@/lib/format';
import { invoice } from '@/routes/projects';
import type { Project, ProjectMetrics } from '@/types';

const props = defineProps<{
    project: Project;
    metrics: ProjectMetrics;
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    mode: 'time_materials' as 'time_materials' | 'fixed',
    until: '',
    amount: '' as string | number,
    description: '',
});

// A problem with the project itself (e.g. nothing left to invoice) comes back as "project".
const projectError = computed(
    () => (form.errors as Record<string, string | undefined>).project,
);

const leftToInvoice = computed(() =>
    Math.max(
        0,
        Number(props.project.budget_amount ?? 0) - props.metrics.invoiced,
    ),
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.mode =
        props.project.billing_method === 'fixed_price'
            ? 'fixed'
            : 'time_materials';
    form.until = '';
    form.amount = leftToInvoice.value ? leftToInvoice.value.toFixed(2) : '';
    form.description = '';
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        until: data.until || null,
        amount: data.mode === 'fixed' ? data.amount : null,
    }));
    form.post(invoice([slug.value, props.project.id]).url);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Invoice this project</DialogTitle>
                <DialogDescription>
                    A draft invoice is made for
                    {{ project.customer_name ?? 'the customer' }}; you can check
                    and change it before issuing.
                </DialogDescription>
            </DialogHeader>

            <form
                id="invoice-project"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="rounded-md border p-3 text-left text-sm"
                        :class="{
                            'border-primary ring-1 ring-primary':
                                form.mode === 'time_materials',
                        }"
                        @click="form.mode = 'time_materials'"
                    >
                        <p class="font-medium">Time & materials</p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDuration(metrics.unbilled_minutes) }} ·
                            {{
                                formatMoney(
                                    metrics.unbilled_value,
                                    project.currency,
                                )
                            }}
                            not invoiced
                        </p>
                    </button>
                    <button
                        type="button"
                        class="rounded-md border p-3 text-left text-sm"
                        :class="{
                            'border-primary ring-1 ring-primary':
                                form.mode === 'fixed',
                        }"
                        @click="form.mode = 'fixed'"
                    >
                        <p class="font-medium">An amount</p>
                        <p class="text-xs text-muted-foreground">
                            e.g. an advance or a part of the fixed price
                        </p>
                    </button>
                </div>

                <template v-if="form.mode === 'time_materials'">
                    <Field
                        label="Include work up to"
                        hint="Empty: everything not invoiced yet"
                        :error="form.errors.until"
                    >
                        <Input v-model="form.until" type="date" />
                    </Field>
                </template>
                <template v-else>
                    <Field
                        :label="`Amount excl. tax (${project.currency})`"
                        :hint="
                            project.budget_amount
                                ? `Fixed price ${formatMoney(project.budget_amount, project.currency)}, ${formatMoney(metrics.invoiced, project.currency)} invoiced so far`
                                : undefined
                        "
                        :error="form.errors.amount"
                        required
                    >
                        <Input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                    </Field>
                    <Field
                        label="Line description"
                        :error="form.errors.description"
                    >
                        <Input
                            v-model="form.description"
                            placeholder="e.g. 50% advance payment"
                        />
                    </Field>
                </template>

                <p v-if="projectError" class="text-sm text-destructive">
                    {{ projectError }}
                </p>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="invoice-project"
                    :disabled="form.processing"
                >
                    Create draft invoice
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
