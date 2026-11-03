<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Field from '@/components/app/Field.vue';
import InputError from '@/components/InputError.vue';
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
import { formatMoney } from '@/lib/format';
import { store, update } from '@/routes/settings/salary-tax-tables';

export type TaxTable = {
    id: number;
    tax_year: number;
    label: string;
    name: string;
    slabs: { from: string; fixed: string; rate: string }[];
    surcharge_threshold: string | null;
    surcharge_percent: string;
    notes: string | null;
};

/**
 * Add or change the income tax slabs of salaried individuals for a tax year.
 */
const props = defineProps<{
    table?: TaxTable | null;
    copyFrom?: TaxTable | null;
    year: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug, currency } = useTeam();

type Slab = {
    from: string | number;
    fixed: string | number;
    rate: string | number;
};

const form = useForm({
    tax_year: props.year as string | number,
    name: '',
    slabs: [] as Slab[],
    surcharge_threshold: '' as string | number,
    surcharge_percent: '' as string | number,
    notes: '',
});

const example = ref<string | number>(2400000);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const source = props.table ?? props.copyFrom ?? null;

    form.clearErrors();
    form.defaults({
        tax_year: props.table?.tax_year ?? props.year,
        name: props.table?.name ?? '',
        slabs: source
            ? source.slabs.map((slab) => ({
                  from: Number(slab.from),
                  fixed: Number(slab.fixed),
                  rate: Number(slab.rate),
              }))
            : [{ from: 0, fixed: 0, rate: 0 }],
        surcharge_threshold: source?.surcharge_threshold
            ? Number(source.surcharge_threshold)
            : '',
        surcharge_percent: source ? Number(source.surcharge_percent) : '',
        notes: props.table?.notes ?? '',
    });
    form.reset();
});

// The same sum as the payroll: the last slab the income exceeds, plus the surcharge.
const exampleTax = computed(() => {
    const income = Number(example.value || 0);
    let tax = 0;

    for (const slab of [...form.slabs].sort(
        (a, b) => Number(a.from) - Number(b.from),
    )) {
        if (income > Number(slab.from)) {
            tax =
                Number(slab.fixed) +
                ((income - Number(slab.from)) * Number(slab.rate)) / 100;
        }
    }

    if (
        form.surcharge_threshold !== '' &&
        income > Number(form.surcharge_threshold)
    ) {
        tax += (tax * Number(form.surcharge_percent || 0)) / 100;
    }

    return Math.round(tax * 100) / 100;
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        surcharge_threshold:
            data.surcharge_threshold === '' ? null : data.surcharge_threshold,
        surcharge_percent: data.surcharge_percent || 0,
    }));

    if (props.table) {
        form.put(update([slug.value, props.table.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        table
                            ? `Tax rates of tax year ${table.tax_year}`
                            : 'Add the tax rates of a tax year'
                    }}
                </DialogTitle>
                <DialogDescription>
                    The income tax slabs for salaried individuals from the
                    Finance Act of the year. Tax year {{ form.tax_year }} runs
                    from July {{ Number(form.tax_year) - 1 }} to June
                    {{ form.tax_year }}.
                </DialogDescription>
            </DialogHeader>

            <form
                id="tax-table-form"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Tax year"
                        :error="form.errors.tax_year"
                        required
                    >
                        <Input
                            v-model="form.tax_year"
                            type="number"
                            min="2000"
                            max="2100"
                            required
                        />
                    </Field>
                    <Field
                        label="Source"
                        hint="E.g. Finance Act 2026"
                        :error="form.errors.name"
                        required
                    >
                        <Input v-model="form.name" maxlength="100" required />
                    </Field>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">Slabs</p>
                    <p class="text-xs text-muted-foreground">
                        Where the taxable salary of the year exceeds the amount:
                        the fixed tax plus the rate on the part above it.
                    </p>
                    <div
                        class="hidden gap-2 text-xs text-muted-foreground sm:grid sm:grid-cols-[1fr_1fr_7rem_auto]"
                    >
                        <span>Income over ({{ currency }})</span>
                        <span>Fixed tax</span>
                        <span>Rate %</span>
                        <span class="w-9" />
                    </div>
                    <div
                        v-for="(slab, position) in form.slabs"
                        :key="position"
                        class="grid gap-2 sm:grid-cols-[1fr_1fr_7rem_auto]"
                    >
                        <Input
                            v-model="slab.from"
                            type="number"
                            step="0.01"
                            min="0"
                            aria-label="Income over"
                        />
                        <Input
                            v-model="slab.fixed"
                            type="number"
                            step="0.01"
                            min="0"
                            aria-label="Fixed tax"
                        />
                        <Input
                            v-model="slab.rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            aria-label="Rate"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove slab"
                            :disabled="form.slabs.length === 1"
                            @click="form.slabs.splice(position, 1)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    <InputError
                        :message="
                            form.errors.slabs ??
                            Object.entries(form.errors).find(([key]) =>
                                key.startsWith('slabs.'),
                            )?.[1]
                        "
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="
                            form.slabs.push({ from: '', fixed: '', rate: '' })
                        "
                    >
                        <Plus /> Add slab
                    </Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Surcharge above (yearly income)"
                        hint="Leave empty when there is no surcharge"
                        :error="form.errors.surcharge_threshold"
                    >
                        <Input
                            v-model="form.surcharge_threshold"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                    </Field>
                    <Field
                        label="Surcharge (% of the tax)"
                        :error="form.errors.surcharge_percent"
                    >
                        <Input
                            v-model="form.surcharge_percent"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                        />
                    </Field>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 rounded-md bg-muted/50 p-3 text-sm"
                >
                    <span>Check: a yearly taxable salary of</span>
                    <Input
                        v-model="example"
                        type="number"
                        min="0"
                        class="h-8 w-36"
                        aria-label="Yearly salary to check"
                    />
                    <span
                        >gives
                        <strong>{{ formatMoney(exampleTax, currency) }}</strong>
                        tax a year ({{
                            formatMoney(exampleTax / 12, currency)
                        }}
                        a month).</span
                    >
                </div>

                <Field label="Notes" :error="form.errors.notes">
                    <Textarea v-model="form.notes" rows="2" />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="tax-table-form"
                    :disabled="form.processing"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
