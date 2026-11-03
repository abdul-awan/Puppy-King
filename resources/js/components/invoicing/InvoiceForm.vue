<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import DocumentLinesCard from '@/components/documents/DocumentLinesCard.vue';
import DocumentNotesCard from '@/components/documents/DocumentNotesCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import {
    calculateTotals,
    dueDateFor,
    emptyLine,
    linesPayload,
    toEditableLines,
} from '@/lib/documents';
import type { EditableLine } from '@/lib/documents';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/invoices';
import type {
    IdName,
    Invoice,
    LookupResult,
    Option,
    PaymentTermOption,
    TaxRateOption,
} from '@/types';

export type InvoicePrefill = {
    deal: { id: number; title: string } | null;
    company: IdName | null;
    contact: IdName | null;
    apply_further_tax: boolean;
    title: string | null;
    currency: string;
    date: string;
    payment_term_id: number | null;
    due_date: string | null;
    department_id: number | null;
};

const props = defineProps<{
    invoice?: Invoice | null;
    prefill?: InvoicePrefill;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    paymentTerms: PaymentTermOption[];
    furtherTaxRate: string | null;
}>();

const { slug, currency: teamCurrency } = useTeam();
const defaultTaxRateId =
    props.taxRates.find((rate) => rate.is_default)?.id ?? null;

const lines = ref<EditableLine[]>(
    props.invoice?.lines
        ? toEditableLines(props.invoice.lines)
        : [emptyLine('item', defaultTaxRateId)],
);

const form = useForm({
    deal_id: props.invoice?.deal_id ?? props.prefill?.deal?.id ?? null,
    quotation_id: props.invoice?.quotation_id ?? null,
    company_id: props.invoice?.company_id ?? props.prefill?.company?.id ?? null,
    contact_id: props.invoice?.contact_id ?? props.prefill?.contact?.id ?? null,
    department_id:
        props.invoice?.department_id ?? props.prefill?.department_id ?? null,
    payment_term_id:
        props.invoice?.payment_term_id ??
        props.prefill?.payment_term_id ??
        null,
    title: props.invoice?.title ?? props.prefill?.title ?? '',
    date: props.invoice?.date ?? props.prefill?.date ?? '',
    due_date: props.invoice?.due_date ?? props.prefill?.due_date ?? '',
    currency:
        props.invoice?.currency ??
        props.prefill?.currency ??
        teamCurrency.value,
    exchange_rate: props.invoice?.exchange_rate ?? '1',
    discount_percent: props.invoice
        ? Number(props.invoice.discount_percent) || ''
        : '',
    apply_further_tax: props.invoice
        ? props.invoice.further_tax_rate !== null
        : (props.prefill?.apply_further_tax ?? false),
    fbr_invoice_number: props.invoice?.fbr_invoice_number ?? '',
    introduction: props.invoice?.introduction ?? '',
    terms: props.invoice?.terms ?? '',
    notes: props.invoice?.notes ?? '',
    issue_now: false,
});

// Keep the due date in step with the invoice date and payment term.
watch(
    () => [form.date, form.payment_term_id] as const,
    ([date, termId]) => {
        const due = dueDateFor(
            date,
            props.paymentTerms.find((term) => term.id === termId),
        );

        if (due) {
            form.due_date = due;
        }
    },
);

const totals = computed(() =>
    calculateTotals(
        lines.value,
        props.taxRates,
        form.discount_percent,
        form.apply_further_tax ? props.furtherTaxRate : null,
    ),
);

const onCompanySelected = (item: LookupResult | null) => {
    const data = item?.data as
        | {
              sales_tax_registered?: boolean;
              currency?: string | null;
              payment_term_id?: number | null;
          }
        | undefined;

    if (data) {
        form.apply_further_tax =
            props.furtherTaxRate !== null && !data.sales_tax_registered;

        if (data.currency) {
            form.currency = data.currency;
        }

        if (data.payment_term_id) {
            form.payment_term_id = data.payment_term_id;
        }
    }

    form.contact_id = null;
};

const submit = (issue: boolean) => {
    form.issue_now = issue;
    form.transform((data) => ({ ...data, lines: linesPayload(lines.value) }));

    if (props.invoice) {
        form.put(update([slug.value, props.invoice.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.invoice
    ? show([slug.value, props.invoice.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit(false)">
        <Card>
            <CardHeader>
                <CardTitle>Customer & details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field
                    label="Company"
                    :error="form.errors.company_id"
                    class="lg:col-span-2"
                >
                    <EntitySelect
                        v-model="form.company_id"
                        type="companies"
                        :initial-label="
                            invoice?.company?.name ?? prefill?.company?.name
                        "
                        placeholder="Choose a company…"
                        clearable
                        @select="onCompanySelected"
                    />
                </Field>
                <Field
                    label="Contact person"
                    :error="form.errors.contact_id"
                    class="lg:col-span-2"
                >
                    <EntitySelect
                        v-model="form.contact_id"
                        type="contacts"
                        :params="{ company: form.company_id }"
                        :initial-label="
                            invoice?.contact?.name ?? prefill?.contact?.name
                        "
                        placeholder="Choose a contact…"
                        clearable
                    />
                </Field>
                <Field
                    label="Subject"
                    :error="form.errors.title"
                    class="sm:col-span-2"
                >
                    <Input
                        v-model="form.title"
                        placeholder="e.g. Supply of office furniture"
                    />
                </Field>
                <Field label="Invoice date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field
                    label="Payment term"
                    :error="form.errors.payment_term_id"
                >
                    <SelectInput
                        v-model="form.payment_term_id"
                        :options="idOptions(paymentTerms)"
                        placeholder="None"
                    />
                </Field>
                <Field label="Due date" :error="form.errors.due_date">
                    <Input v-model="form.due_date" type="date" />
                </Field>
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
                    v-if="departments.length > 1"
                    label="Company profile"
                    :error="form.errors.department_id"
                >
                    <SelectInput
                        v-model="form.department_id"
                        :options="idOptions(departments)"
                    />
                </Field>
                <Field
                    label="FBR invoice no."
                    hint="If the invoice is reported to FBR"
                    :error="form.errors.fbr_invoice_number"
                >
                    <Input v-model="form.fbr_invoice_number" />
                </Field>
                <Field
                    v-if="invoice?.deal ?? prefill?.deal"
                    label="Deal"
                    class="sm:col-span-2"
                >
                    <p class="flex h-9 items-center text-sm">
                        {{ (invoice?.deal ?? prefill?.deal)?.title }}
                    </p>
                </Field>
                <Field v-if="invoice?.quotation" label="Quotation">
                    <p class="flex h-9 items-center text-sm">
                        {{ invoice.quotation.number }}
                    </p>
                </Field>
            </CardContent>
        </Card>

        <DocumentLinesCard
            v-model:lines="lines"
            v-model:introduction="form.introduction"
            v-model:discount-percent="form.discount_percent"
            v-model:apply-further-tax="form.apply_further_tax"
            :tax-rates="taxRates"
            :currency="form.currency"
            :totals="totals"
            :further-tax-rate="furtherTaxRate"
            :errors="form.errors"
        />

        <DocumentNotesCard
            v-model:terms="form.terms"
            v-model:notes="form.notes"
            :errors="form.errors"
            terms-placeholder="e.g. Payment by bank transfer within 30 days."
        />

        <div
            class="flex flex-col-reverse items-stretch justify-end gap-2 sm:flex-row sm:items-center"
        >
            <p class="text-xs text-muted-foreground sm:mr-auto">
                A draft has no number yet. Issuing gives it the next invoice
                number, after which it can only be corrected with a credit note.
            </p>
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" variant="outline" :disabled="form.processing">
                Save draft
            </Button>
            <Button
                type="button"
                :disabled="form.processing"
                @click="submit(true)"
            >
                Save & issue
            </Button>
        </div>
    </form>
</template>
