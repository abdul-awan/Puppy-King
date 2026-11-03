<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import { round2 } from '@/lib/documents';
import { formatMoney, today } from '@/lib/format';
import { store } from '@/routes/invoices/payments';
import type { Invoice, Option } from '@/types';

const props = defineProps<{
    invoice: Invoice;
    methods: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const balance = computed(() => Number(props.invoice.balance_due));
const netValue = computed(
    () =>
        Number(props.invoice.subtotal) - Number(props.invoice.discount_amount),
);
const salesTax = computed(
    () =>
        Number(props.invoice.tax_total) +
        Number(props.invoice.further_tax_amount),
);

const form = useForm({
    date: today(),
    amount: '' as string | number,
    income_tax_withheld: '' as string | number,
    sales_tax_withheld: '' as string | number,
    method: 'bank_transfer',
    reference: '',
    notes: '',
});

const showWithholding = ref(false);
const incomeTaxRate = ref<string | number>('');
const salesTaxShare = ref<string | number>('');
const amountTouched = ref(false);

const withheld = computed(
    () =>
        Number(form.income_tax_withheld || 0) +
        Number(form.sales_tax_withheld || 0),
);
const settled = computed(() => Number(form.amount || 0) + withheld.value);
const remaining = computed(() => round2(balance.value - settled.value));

const reset = () => {
    form.reset();
    form.clearErrors();
    form.date = today();
    form.amount = balance.value.toFixed(2);
    incomeTaxRate.value = '';
    salesTaxShare.value = '';
    amountTouched.value = false;
    showWithholding.value = false;
};

watch(open, (isOpen) => {
    if (isOpen) {
        reset();
    }
});

// The money received is what is left after the customer's withholdings.
watch(withheld, (value) => {
    if (!amountTouched.value) {
        form.amount = Math.max(0, round2(balance.value - value)).toFixed(2);
    }
});

watch(incomeTaxRate, (rate) => {
    if (rate !== '') {
        form.income_tax_withheld = round2(
            (netValue.value * Number(rate)) / 100,
        ).toFixed(2);
    }
});

watch(salesTaxShare, (share) => {
    if (share !== '') {
        form.sales_tax_withheld = round2(
            (salesTax.value * Number(share)) / 100,
        ).toFixed(2);
    }
});

const submit = () => {
    form.post(store([slug.value, props.invoice.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Record payment</DialogTitle>
                <DialogDescription>
                    Invoice {{ invoice.number }} · balance due
                    {{ formatMoney(invoice.balance_due, invoice.currency) }}
                </DialogDescription>
            </DialogHeader>

            <form
                id="record-payment"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Date" :error="form.errors.date" required>
                        <Input v-model="form.date" type="date" :max="today()" />
                    </Field>
                    <Field label="Method" :error="form.errors.method">
                        <SelectInput v-model="form.method" :options="methods" />
                    </Field>
                    <Field
                        :label="`Amount received (${invoice.currency})`"
                        :error="form.errors.amount"
                        required
                    >
                        <Input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            @input="amountTouched = true"
                        />
                    </Field>
                    <Field
                        label="Reference"
                        hint="Cheque no., transaction ID…"
                        :error="form.errors.reference"
                    >
                        <Input v-model="form.reference" />
                    </Field>
                </div>

                <div class="rounded-md border p-3">
                    <button
                        type="button"
                        class="text-sm font-medium hover:underline"
                        @click="showWithholding = !showWithholding"
                    >
                        {{ showWithholding ? '−' : '+' }} Tax withheld by the
                        customer
                    </button>
                    <div v-if="showWithholding" class="mt-3 space-y-3">
                        <p class="text-xs text-muted-foreground">
                            Value excl. tax
                            {{ formatMoney(netValue, invoice.currency) }} ·
                            sales tax
                            {{ formatMoney(salesTax, invoice.currency) }}. Keep
                            the withholding certificate (CPR) for your tax
                            return.
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <Field
                                label="Income tax withheld"
                                :error="form.errors.income_tax_withheld"
                            >
                                <div class="flex gap-2">
                                    <Input
                                        v-model="form.income_tax_withheld"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <Input
                                        v-model="incomeTaxRate"
                                        type="number"
                                        step="any"
                                        min="0"
                                        placeholder="%"
                                        class="w-20"
                                        aria-label="Income tax rate on the value excl. tax"
                                    />
                                </div>
                            </Field>
                            <Field
                                label="Sales tax withheld"
                                :error="form.errors.sales_tax_withheld"
                            >
                                <div class="flex gap-2">
                                    <Input
                                        v-model="form.sales_tax_withheld"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <Input
                                        v-model="salesTaxShare"
                                        type="number"
                                        step="any"
                                        min="0"
                                        placeholder="%"
                                        class="w-20"
                                        aria-label="Share of the sales tax withheld"
                                    />
                                </div>
                            </Field>
                        </div>
                    </div>
                </div>

                <Field label="Notes" :error="form.errors.notes">
                    <Textarea v-model="form.notes" rows="2" />
                </Field>

                <div
                    class="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2 text-sm"
                >
                    <span>
                        Settles
                        <strong>{{
                            formatMoney(settled, invoice.currency)
                        }}</strong>
                    </span>
                    <span
                        :class="{
                            'text-destructive': remaining < 0,
                            'text-green-700 dark:text-green-400':
                                remaining === 0,
                        }"
                    >
                        {{
                            remaining === 0
                                ? 'Paid in full'
                                : remaining < 0
                                  ? `${formatMoney(-remaining, invoice.currency)} too much`
                                  : `${formatMoney(remaining, invoice.currency)} still open`
                        }}
                    </span>
                </div>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="record-payment"
                    :disabled="form.processing"
                >
                    Record payment
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
