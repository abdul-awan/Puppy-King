<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { round2 } from '@/lib/documents';
import { formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/expenses';
import type {
    Expense,
    IdName,
    LookupResult,
    Option,
    TaxRateOption,
} from '@/types';

export type ExpensePrefill = {
    company: IdName | null;
    supplier_name: string | null;
    supplier_ntn: string | null;
    supplier_strn: string | null;
    currency: string;
    date: string;
};

const props = defineProps<{
    expense?: Expense | null;
    prefill?: ExpensePrefill;
    categories: IdName[];
    taxRates: TaxRateOption[];
    currencies: Option[];
}>();

const { slug, currency: teamCurrency } = useTeam();

const form = useForm({
    title: props.expense?.title ?? '',
    company_id: props.expense?.company_id ?? props.prefill?.company?.id ?? null,
    supplier_name:
        props.expense?.supplier_name ?? props.prefill?.supplier_name ?? '',
    supplier_ntn:
        props.expense?.supplier_ntn ?? props.prefill?.supplier_ntn ?? '',
    supplier_strn:
        props.expense?.supplier_strn ?? props.prefill?.supplier_strn ?? '',
    bill_number: props.expense?.bill_number ?? '',
    category_id: props.expense?.category_id ?? null,
    date: props.expense?.date ?? props.prefill?.date ?? '',
    due_date: props.expense?.due_date ?? '',
    currency:
        props.expense?.currency ??
        props.prefill?.currency ??
        teamCurrency.value,
    exchange_rate: props.expense?.exchange_rate ?? '1',
    net_amount: props.expense
        ? Number(props.expense.net_amount)
        : ('' as string | number),
    tax_rate_id: props.expense?.tax_rate_id ?? null,
    tax_amount: props.expense
        ? Number(props.expense.tax_amount)
        : ('' as string | number),
    tax_claimable: props.expense?.tax_claimable ?? true,
    income_tax_withheld: props.expense
        ? Number(props.expense.income_tax_withheld) || ''
        : ('' as string | number),
    sales_tax_withheld: props.expense
        ? Number(props.expense.sales_tax_withheld) || ''
        : ('' as string | number),
    notes: props.expense?.notes ?? '',
    approve_now: true,
    files: [] as File[],
});

// The tax follows the rate until it is typed by hand (to match the bill exactly).
const taxTouched = ref(props.expense !== null && props.expense !== undefined);
const showWithholding = ref(
    Number(props.expense?.income_tax_withheld ?? 0) > 0 ||
        Number(props.expense?.sales_tax_withheld ?? 0) > 0,
);
const incomeTaxRate = ref<string | number>('');
const salesTaxShare = ref<string | number>('');

const suggestedTax = computed(() => {
    const rate = props.taxRates.find((tax) => tax.id === form.tax_rate_id);

    return round2(
        (Number(form.net_amount || 0) * Number(rate?.rate ?? 0)) / 100,
    );
});

watch(suggestedTax, (tax) => {
    if (!taxTouched.value) {
        form.tax_amount = tax ? tax.toFixed(2) : '';
    }
});

watch(incomeTaxRate, (rate) => {
    if (rate !== '') {
        form.income_tax_withheld = round2(
            (Number(form.net_amount || 0) * Number(rate)) / 100,
        ).toFixed(2);
    }
});

watch(salesTaxShare, (share) => {
    if (share !== '') {
        form.sales_tax_withheld = round2(
            (Number(form.tax_amount || 0) * Number(share)) / 100,
        ).toFixed(2);
    }
});

const total = computed(() =>
    round2(Number(form.net_amount || 0) + Number(form.tax_amount || 0)),
);
const payable = computed(() =>
    round2(
        total.value -
            Number(form.income_tax_withheld || 0) -
            Number(form.sales_tax_withheld || 0),
    ),
);

const onSupplierSelected = (item: LookupResult | null) => {
    const data = item?.data as
        | {
              name?: string;
              ntn?: string | null;
              strn?: string | null;
              currency?: string | null;
              sales_tax_registered?: boolean;
          }
        | undefined;

    if (!data) {
        return;
    }

    form.supplier_name = data.name ?? form.supplier_name;
    form.supplier_ntn = data.ntn ?? '';
    form.supplier_strn = data.strn ?? '';
    form.tax_claimable = Boolean(data.sales_tax_registered);

    if (data.currency) {
        form.currency = data.currency;
    }
};

const onFiles = (event: Event) => {
    form.files = Array.from((event.target as HTMLInputElement).files ?? []);
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        due_date: data.due_date || null,
        tax_amount: data.tax_amount === '' ? null : data.tax_amount,
        ...(props.expense ? { _method: 'put' } : {}),
    }));

    // Files are sent as multipart form data, which only works with POST (the update spoofs PUT).
    form.post(
        props.expense
            ? update([slug.value, props.expense.id]).url
            : store(slug.value).url,
        { preserveScroll: true, forceFormData: form.files.length > 0 },
    );
};

const cancelUrl = props.expense
    ? show([slug.value, props.expense.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Supplier & bill</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field
                    label="Supplier (from your contacts)"
                    :error="form.errors.company_id"
                    class="lg:col-span-2"
                >
                    <EntitySelect
                        v-model="form.company_id"
                        type="companies"
                        :initial-label="
                            expense?.supplier?.name ?? prefill?.company?.name
                        "
                        placeholder="Choose a company…"
                        clearable
                        @select="onSupplierSelected"
                    />
                </Field>
                <Field
                    label="Supplier name"
                    hint="As printed on the bill"
                    :error="form.errors.supplier_name"
                    class="lg:col-span-2"
                >
                    <Input
                        v-model="form.supplier_name"
                        :disabled="form.company_id !== null"
                        placeholder="e.g. K-Electric"
                    />
                </Field>
                <Field label="Supplier NTN" :error="form.errors.supplier_ntn">
                    <Input
                        v-model="form.supplier_ntn"
                        placeholder="1234567-8"
                    />
                </Field>
                <Field label="Supplier STRN" :error="form.errors.supplier_strn">
                    <Input v-model="form.supplier_strn" />
                </Field>
                <Field
                    label="Bill / invoice no."
                    :error="form.errors.bill_number"
                >
                    <Input v-model="form.bill_number" />
                </Field>
                <Field label="Category" :error="form.errors.category_id">
                    <SelectInput
                        v-model="form.category_id"
                        :options="idOptions(categories)"
                        placeholder="Choose…"
                    />
                </Field>
                <Field
                    label="Description"
                    :error="form.errors.title"
                    class="sm:col-span-2"
                    required
                >
                    <Input
                        v-model="form.title"
                        required
                        placeholder="e.g. Office rent October"
                    />
                </Field>
                <Field label="Bill date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field label="Pay by" :error="form.errors.due_date">
                    <Input v-model="form.due_date" type="date" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Amounts</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Currency" :error="form.errors.currency">
                        <SelectInput
                            v-model="form.currency"
                            :options="
                                currencies.map((c) => ({
                                    value: c.value,
                                    label: c.value,
                                }))
                            "
                        />
                    </Field>
                    <Field
                        v-if="form.currency !== teamCurrency"
                        :label="`Exchange rate (1 ${form.currency} = ? ${teamCurrency})`"
                        :error="form.errors.exchange_rate"
                    >
                        <Input
                            v-model="form.exchange_rate"
                            type="number"
                            step="any"
                            min="0"
                        />
                    </Field>
                    <Field
                        label="Amount excl. tax"
                        :error="form.errors.net_amount"
                        required
                    >
                        <Input
                            v-model="form.net_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                    </Field>
                    <Field
                        label="Sales tax rate"
                        :error="form.errors.tax_rate_id"
                    >
                        <SelectInput
                            v-model="form.tax_rate_id"
                            :options="idOptions(taxRates)"
                            placeholder="No tax"
                        />
                    </Field>
                    <Field
                        label="Sales tax on the bill"
                        :error="form.errors.tax_amount"
                        :hint="
                            taxTouched &&
                            Number(form.tax_amount) !== suggestedTax
                                ? `Calculated: ${formatMoney(suggestedTax, form.currency)}`
                                : undefined
                        "
                    >
                        <Input
                            v-model="form.tax_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            @input="taxTouched = true"
                        />
                    </Field>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        :model-value="form.tax_claimable"
                        class="mt-0.5"
                        @update:model-value="
                            form.tax_claimable = Boolean($event)
                        "
                    />
                    <span>
                        The sales tax can be claimed as input tax (the supplier
                        is registered and gave a proper sales tax invoice)
                    </span>
                </label>

                <div class="rounded-md border p-3">
                    <button
                        type="button"
                        class="text-sm font-medium hover:underline"
                        @click="showWithholding = !showWithholding"
                    >
                        {{ showWithholding ? '−' : '+' }} Tax you withhold when
                        paying the supplier
                    </button>
                    <div
                        v-if="showWithholding"
                        class="mt-3 grid gap-3 sm:grid-cols-2"
                    >
                        <Field
                            label="Income tax withheld"
                            hint="Right field: % of the amount excl. tax"
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
                                    aria-label="Income tax rate"
                                />
                            </div>
                        </Field>
                        <Field
                            label="Sales tax withheld"
                            hint="Right field: % of the sales tax"
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
                                    aria-label="Share of the sales tax"
                                />
                            </div>
                        </Field>
                    </div>
                </div>

                <dl
                    class="ml-auto w-full max-w-sm space-y-1 rounded-md bg-muted/50 p-3 text-sm"
                >
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Total of the bill</dt>
                        <dd class="font-semibold">
                            {{ formatMoney(total, form.currency) }}
                        </dd>
                    </div>
                    <div v-if="payable !== total" class="flex justify-between">
                        <dt class="text-muted-foreground">
                            To pay the supplier
                        </dt>
                        <dd>{{ formatMoney(payable, form.currency) }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Bill scan & notes</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Attach the bill"
                    hint="PDF or photo, up to 20 MB each"
                    :error="form.errors.files ?? form.errors['files.0']"
                >
                    <Input
                        type="file"
                        multiple
                        accept=".pdf,image/*"
                        @change="onFiles"
                    />
                </Field>
                <Field label="Notes" :error="form.errors.notes">
                    <Textarea v-model="form.notes" rows="3" />
                </Field>
                <label
                    v-if="!expense"
                    class="flex items-start gap-2 text-sm sm:col-span-2"
                >
                    <Checkbox
                        :model-value="form.approve_now"
                        class="mt-0.5"
                        @update:model-value="form.approve_now = Boolean($event)"
                    />
                    <span>
                        Approve for payment now (untick to let someone else
                        approve it)
                    </span>
                </label>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ expense ? 'Save changes' : 'Save expense' }}
            </Button>
        </div>
    </form>
</template>
