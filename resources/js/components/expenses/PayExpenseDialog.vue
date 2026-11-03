<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { formatMoney, today } from '@/lib/format';
import { store } from '@/routes/expenses/payments';
import type { Expense, Option } from '@/types';

const props = defineProps<{
    expense: Expense;
    methods: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    date: today(),
    amount: '' as string | number,
    method: 'bank_transfer',
    reference: '',
    notes: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        form.date = today();
        form.amount = Number(props.expense.balance_due).toFixed(2);
    }
});

const submit = () => {
    form.post(store([slug.value, props.expense.id]).url, {
        preserveScroll: true,
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
                <DialogTitle>Pay supplier</DialogTitle>
                <DialogDescription>
                    {{ expense.supplier_name }} · still to pay
                    {{ formatMoney(expense.balance_due, expense.currency) }}
                </DialogDescription>
            </DialogHeader>

            <form id="pay-expense" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Date" :error="form.errors.date" required>
                        <Input v-model="form.date" type="date" :max="today()" />
                    </Field>
                    <Field
                        :label="`Amount (${expense.currency})`"
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
                    <Field label="Method" :error="form.errors.method">
                        <SelectInput v-model="form.method" :options="methods" />
                    </Field>
                    <Field label="Reference" :error="form.errors.reference">
                        <Input
                            v-model="form.reference"
                            placeholder="Cheque no.…"
                        />
                    </Field>
                </div>
                <Field label="Notes" :error="form.errors.notes">
                    <Textarea v-model="form.notes" rows="2" />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="pay-expense"
                    :disabled="form.processing"
                >
                    Record payment
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
