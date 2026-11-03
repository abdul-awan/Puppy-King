<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    emptyLine,
    linesPayload,
    toEditableLines,
} from '@/lib/documents';
import type { EditableLine } from '@/lib/documents';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/quotations';
import type {
    IdName,
    LookupResult,
    Option,
    Quotation,
    TaxRateOption,
} from '@/types';

const props = defineProps<{
    quotation?: Quotation | null;
    prefill?: {
        deal: { id: number; title: string } | null;
        company: IdName | null;
        contact: IdName | null;
        apply_further_tax: boolean;
        title: string | null;
        currency: string;
        date: string;
        valid_until: string;
        department_id: number | null;
    };
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    furtherTaxRate: string | null;
}>();

const { slug, currency: teamCurrency } = useTeam();
const defaultTaxRateId =
    props.taxRates.find((rate) => rate.is_default)?.id ?? null;

const lines = ref<EditableLine[]>(
    props.quotation?.lines
        ? toEditableLines(props.quotation.lines)
        : [emptyLine('item', defaultTaxRateId)],
);

const form = useForm({
    deal_id: props.quotation?.deal_id ?? props.prefill?.deal?.id ?? null,
    company_id:
        props.quotation?.company_id ?? props.prefill?.company?.id ?? null,
    contact_id:
        props.quotation?.contact_id ?? props.prefill?.contact?.id ?? null,
    department_id:
        props.quotation?.department_id ?? props.prefill?.department_id ?? null,
    title: props.quotation?.title ?? props.prefill?.title ?? '',
    date: props.quotation?.date ?? props.prefill?.date ?? '',
    valid_until:
        props.quotation?.valid_until ?? props.prefill?.valid_until ?? '',
    currency:
        props.quotation?.currency ??
        props.prefill?.currency ??
        teamCurrency.value,
    exchange_rate: props.quotation?.exchange_rate ?? '1',
    discount_percent: props.quotation
        ? Number(props.quotation.discount_percent) || ''
        : '',
    apply_further_tax: props.quotation
        ? props.quotation.further_tax_rate !== null
        : (props.prefill?.apply_further_tax ?? false),
    introduction: props.quotation?.introduction ?? '',
    terms: props.quotation?.terms ?? '',
    notes: props.quotation?.notes ?? '',
});

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
        | { sales_tax_registered?: boolean; currency?: string | null }
        | undefined;

    if (data) {
        form.apply_further_tax =
            props.furtherTaxRate !== null && !data.sales_tax_registered;

        if (data.currency) {
            form.currency = data.currency;
        }
    }

    form.contact_id = null;
};

const submit = () => {
    form.transform((data) => ({ ...data, lines: linesPayload(lines.value) }));

    if (props.quotation) {
        form.put(update([slug.value, props.quotation.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.quotation
    ? show([slug.value, props.quotation.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
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
                            quotation?.company?.name ?? prefill?.company?.name
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
                            quotation?.contact?.name ?? prefill?.contact?.name
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
                        placeholder="e.g. Supply of laptops"
                    />
                </Field>
                <Field label="Date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field label="Valid until" :error="form.errors.valid_until">
                    <Input v-model="form.valid_until" type="date" />
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
                    v-if="quotation?.deal ?? prefill?.deal"
                    label="Deal"
                    class="sm:col-span-2"
                >
                    <p class="flex h-9 items-center text-sm">
                        {{ (quotation?.deal ?? prefill?.deal)?.title }}
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
            terms-placeholder="e.g. Prices include delivery in Lahore. 50% advance."
        />

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ quotation ? 'Save changes' : 'Create quotation' }}
            </Button>
        </div>
    </form>
</template>
