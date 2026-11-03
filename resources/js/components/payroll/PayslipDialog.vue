<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatMoney } from '@/lib/format';
import { update } from '@/routes/payslips';
import type { OpenAdvance, Payslip } from '@/types';

/**
 * Change a draft payslip: unpaid days, one-off earnings and deductions, advance recoveries and,
 * if needed, the income tax by hand. Saving works the payslip out again.
 */
const props = defineProps<{
    payslip: Payslip | null;
    advances: OpenAdvance[];
    componentNames: { earning: string[]; deduction: string[] };
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug, currency } = useTeam();

type Adjustment = {
    kind: 'earning' | 'deduction';
    name: string;
    amount: string | number;
    taxable: boolean;
};

const form = useForm({
    unpaid_days: 0 as string | number,
    manual_tax: false,
    tax_override: '' as string | number,
    notes: '',
    adjustments: [] as Adjustment[],
    advances: [] as { id: number; amount: string | number }[],
});

watch(open, (isOpen) => {
    const payslip = props.payslip;

    if (!isOpen || !payslip) {
        return;
    }

    const lines = payslip.lines ?? [];

    form.clearErrors();
    form.defaults({
        unpaid_days: Number(payslip.unpaid_days),
        manual_tax: payslip.tax_override !== null,
        tax_override:
            payslip.tax_override !== null ? Number(payslip.tax_override) : '',
        notes: payslip.notes ?? '',
        adjustments: lines
            .filter((line) => line.source === 'adjustment')
            .map((line) => ({
                kind: line.kind === 'earning' ? 'earning' : 'deduction',
                name: line.name,
                amount: Number(line.amount),
                taxable: Number(line.taxable_amount) > 0,
            })),
        advances: props.advances.map((advance) => ({
            id: advance.id,
            amount: Number(
                lines.find((line) => line.employee_advance_id === advance.id)
                    ?.amount ?? 0,
            ),
        })),
    });
    form.reset();
});

const money = (value: string | number) => formatMoney(value, currency.value);

const kinds = [
    { value: 'earning', label: 'Add to pay' },
    { value: 'deduction', label: 'Deduct' },
];

const addAdjustment = (kind: 'earning' | 'deduction') => {
    form.adjustments.push({ kind, name: '', amount: '', taxable: true });
};

const advanceLabel = (id: number) =>
    props.advances.find((advance) => advance.id === id);

const tax = computed(() => props.payslip?.tax_details ?? null);

const submit = () => {
    if (!props.payslip) {
        return;
    }

    form.transform((data) => ({
        unpaid_days: data.unpaid_days || 0,
        tax_override: data.manual_tax ? data.tax_override || 0 : null,
        notes: data.notes,
        adjustments: data.adjustments,
        advances: data.advances.map((advance) => ({
            id: advance.id,
            amount: advance.amount || 0,
        })),
    }));

    form.put(update([slug.value, props.payslip.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ payslip?.employee_name }}</DialogTitle>
                <DialogDescription>
                    {{ payslip?.period_label }} · now
                    {{ money(payslip?.net ?? 0) }} net. Saving works the payslip
                    out again.
                </DialogDescription>
            </DialogHeader>

            <form
                v-if="payslip"
                id="payslip-form"
                class="space-y-5"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Unpaid days"
                        :hint="`Absent or unpaid leave (worked ${payslip.employed_days} of ${payslip.days_in_period} days)`"
                        :error="form.errors.unpaid_days"
                    >
                        <Input
                            v-model="form.unpaid_days"
                            type="number"
                            step="0.5"
                            min="0"
                            max="31"
                        />
                    </Field>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">
                        One-off amounts this month
                    </p>
                    <p
                        v-if="form.adjustments.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        E.g. a bonus, overtime or arrears, or a fine.
                    </p>
                    <div
                        v-for="(adjustment, position) in form.adjustments"
                        :key="position"
                        class="grid items-start gap-2 sm:grid-cols-[8rem_1fr_9rem_auto]"
                    >
                        <SelectInput
                            v-model="adjustment.kind"
                            :options="kinds"
                            aria-label="Add or deduct"
                        />
                        <div>
                            <Input
                                v-model="adjustment.name"
                                maxlength="100"
                                :list="`payslip-names-${adjustment.kind}`"
                                placeholder="Description"
                                aria-label="Description"
                            />
                            <p
                                v-if="
                                    form.errors[`adjustments.${position}.name`]
                                "
                                class="mt-1 text-sm text-destructive"
                            >
                                {{
                                    form.errors[`adjustments.${position}.name`]
                                }}
                            </p>
                            <label
                                v-if="adjustment.kind === 'earning'"
                                class="mt-1 flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Checkbox
                                    :model-value="adjustment.taxable"
                                    @update:model-value="
                                        adjustment.taxable = Boolean($event)
                                    "
                                />
                                Taxable
                            </label>
                        </div>
                        <div>
                            <Input
                                v-model="adjustment.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                aria-label="Amount"
                            />
                            <p
                                v-if="
                                    form.errors[
                                        `adjustments.${position}.amount`
                                    ]
                                "
                                class="mt-1 text-sm text-destructive"
                            >
                                {{
                                    form.errors[
                                        `adjustments.${position}.amount`
                                    ]
                                }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove"
                            @click="form.adjustments.splice(position, 1)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    <datalist id="payslip-names-earning">
                        <option
                            v-for="name in componentNames.earning"
                            :key="name"
                            :value="name"
                        />
                    </datalist>
                    <datalist id="payslip-names-deduction">
                        <option
                            v-for="name in componentNames.deduction"
                            :key="name"
                            :value="name"
                        />
                    </datalist>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addAdjustment('earning')"
                        >
                            <Plus /> Bonus, overtime…
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addAdjustment('deduction')"
                        >
                            <Plus /> Deduction
                        </Button>
                    </div>
                </div>

                <div v-if="form.advances.length" class="space-y-2">
                    <p class="text-sm font-medium">Advance recoveries</p>
                    <div
                        v-for="(recovery, position) in form.advances"
                        :key="recovery.id"
                        class="grid items-start gap-2 sm:grid-cols-[1fr_9rem]"
                    >
                        <p class="pt-2 text-sm">
                            {{ advanceLabel(recovery.id)?.label }}
                            <span class="block text-xs text-muted-foreground">
                                {{
                                    money(advanceLabel(recovery.id)?.left ?? 0)
                                }}
                                left · installment
                                {{
                                    money(
                                        advanceLabel(recovery.id)
                                            ?.installment ?? 0,
                                    )
                                }}
                            </span>
                        </p>
                        <div>
                            <Input
                                v-model="recovery.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                aria-label="Recover this month"
                            />
                            <p
                                v-if="
                                    form.errors[`advances.${position}.amount`]
                                "
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ form.errors[`advances.${position}.amount`] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 rounded-md border p-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <Checkbox
                            :model-value="form.manual_tax"
                            @update:model-value="
                                form.manual_tax = Boolean($event)
                            "
                        />
                        Set the income tax of this month by hand
                    </label>
                    <Field
                        v-if="form.manual_tax"
                        :label="`Income tax (${currency})`"
                        :error="form.errors.tax_override"
                    >
                        <Input
                            v-model="form.tax_override"
                            type="number"
                            step="0.01"
                            min="0"
                            class="max-w-48"
                        />
                    </Field>
                    <div v-else-if="tax" class="text-xs text-muted-foreground">
                        <template
                            v-if="tax.method === 'auto' && tax.table_year"
                        >
                            Expected taxable salary in tax year
                            {{ tax.tax_year }}:
                            {{ money(tax.expected_income ?? 0) }} (earlier
                            months {{ money(tax.earlier_taxable ?? 0) }}, this
                            month {{ money(tax.month_taxable ?? 0) }}, then
                            {{ money(tax.monthly_taxable ?? 0) }} a month). Tax
                            for the year {{ money(tax.year_tax ?? 0) }}, already
                            deducted {{ money(tax.earlier_tax ?? 0) }}, spread
                            over {{ tax.months_left }}
                            {{ tax.months_left === 1 ? 'month' : 'months' }}
                            <template v-if="Number(tax.one_off_taxable)">
                                (one-off earnings are taxed in full this
                                month)</template
                            >.
                            <template v-if="Number(tax.opening_taxable)">
                                The earlier months include
                                {{ money(tax.opening_taxable ?? 0) }} paid
                                before this payroll was used.
                            </template>
                            <template v-if="tax.table_year !== tax.tax_year">
                                Uses the rates of tax year
                                {{ tax.table_year }}.
                            </template>
                        </template>
                        <template v-else-if="tax.method === 'auto'">
                            No income tax rates are set up yet.
                        </template>
                        <template v-else-if="tax.method === 'fixed'">
                            The fixed monthly tax of the employee.
                        </template>
                        <template v-else-if="tax.method === 'none'">
                            No income tax is deducted for this employee.
                        </template>
                    </div>
                </div>

                <Field
                    label="Remarks on the payslip"
                    :error="form.errors.notes"
                >
                    <Textarea v-model="form.notes" rows="2" />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="payslip-form"
                    :disabled="form.processing"
                >
                    Save and work out again
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
