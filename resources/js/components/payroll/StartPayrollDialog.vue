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
import { formatMonth } from '@/lib/format';
import { store } from '@/routes/payroll';

/**
 * Start the payroll of a month: a draft payslip for every employee.
 */
const props = defineProps<{
    nextMonth: string;
    existingMonths: string[];
    payDay: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({ month: props.nextMonth, pay_date: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.defaults({ month: props.nextMonth, pay_date: '' });
        form.reset();
    }
});

const exists = computed(() => props.existingMonths.includes(form.month));

const payDayHint = computed(() =>
    props.payDay === 0
        ? 'Empty: the last day of the month'
        : `Empty: day ${props.payDay} of the next month`,
);

const submit = () => {
    form.post(store(slug.value).url, {
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Run payroll</DialogTitle>
                <DialogDescription>
                    Makes a draft payslip for everyone who works for the company
                    in the month, with their salary, advances, EOBI and income
                    tax. You can check and change them before approving.
                </DialogDescription>
            </DialogHeader>

            <form
                id="start-payroll"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <Field
                    label="Month"
                    :error="
                        form.errors.month ??
                        (exists
                            ? `There is already a payroll for ${formatMonth(form.month)}.`
                            : undefined)
                    "
                    required
                >
                    <Input v-model="form.month" type="month" required />
                </Field>
                <Field
                    label="Pay date"
                    :hint="payDayHint"
                    :error="form.errors.pay_date"
                >
                    <Input v-model="form.pay_date" type="date" />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="start-payroll"
                    :disabled="form.processing || exists || !form.month"
                >
                    Make the payslips
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
