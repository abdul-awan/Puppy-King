<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
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
import { today } from '@/lib/format';
import { update } from '@/routes/advances';
import { store } from '@/routes/employees/advances';
import type { EmployeeAdvanceRow } from '@/types';

/**
 * Give a salary advance or loan, recovered in monthly installments through the payroll.
 */
const props = defineProps<{
    employeeId: number;
    advance?: EmployeeAdvanceRow | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug, currency } = useTeam();

const form = useForm({
    date: today(),
    amount: '' as string | number,
    installment: '' as string | number,
    reason: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        date: props.advance?.date ?? today(),
        amount: props.advance ? Number(props.advance.amount) : '',
        installment: props.advance ? Number(props.advance.installment) : '',
        reason: props.advance?.reason ?? '',
    });
    form.reset();
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.advance) {
        form.put(update([slug.value, props.advance.id]).url, options);
    } else {
        form.post(store([slug.value, props.employeeId]).url, options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{
                    advance ? 'Change the advance' : 'Give an advance or loan'
                }}</DialogTitle>
                <DialogDescription>
                    The installment is deducted from the salary every month
                    until the advance is paid back.
                </DialogDescription>
            </DialogHeader>

            <form
                id="advance-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <Field label="Date given" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field
                    :label="`Amount (${currency})`"
                    :error="form.errors.amount"
                    required
                >
                    <Input
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                    />
                </Field>
                <Field
                    label="Installment a month"
                    :error="form.errors.installment"
                    required
                >
                    <Input
                        v-model="form.installment"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                    />
                </Field>
                <Field label="Reason" :error="form.errors.reason">
                    <Input
                        v-model="form.reason"
                        maxlength="255"
                        placeholder="e.g. Motorbike"
                    />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="advance-form"
                    :disabled="form.processing"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
