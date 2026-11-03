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
import { Checkbox } from '@/components/ui/checkbox';
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
import { index, show, store, update } from '@/routes/recurring-invoices';
import type {
    IdName,
    LookupResult,
    Option,
    PaymentTermOption,
    RecurringInvoice,
    TaxRateOption,
} from '@/types';

export type RecurringPrefill = {
    company: IdName | null;
    apply_further_tax: boolean;
    currency: string;
    start_date: string;
    payment_term_id: number | null;
    department_id: number | null;
};

const props = defineProps<{
    profile?: RecurringInvoice | null;
    prefill?: RecurringPrefill;
    taxRates: TaxRateOption[];
    departments: IdName[];
    currencies: Option[];
    paymentTerms: PaymentTermOption[];
    frequencies: Option[];
    furtherTaxRate: string | null;
}>();

const { slug, currency: teamCurrency } = useTeam();
const defaultTaxRateId =
    props.taxRates.find((rate) => rate.is_default)?.id ?? null;

const lines = ref<EditableLine[]>(
    props.profile?.lines
        ? toEditableLines(props.profile.lines)
        : [emptyLine('item', defaultTaxRateId)],
);

const form = useForm({
    title: props.profile?.title ?? '',
    company_id: props.profile?.company_id ?? props.prefill?.company?.id ?? null,
    contact_id: props.profile?.contact_id ?? null,
    department_id:
        props.profile?.department_id ?? props.prefill?.department_id ?? null,
    payment_term_id:
        props.profile?.payment_term_id ??
        props.prefill?.payment_term_id ??
        null,
    frequency: props.profile?.frequency ?? 'monthly',
    start_date: props.profile?.start_date ?? props.prefill?.start_date ?? '',
    end_date: props.profile?.end_date ?? '',
    auto_issue: props.profile?.auto_issue ?? false,
    auto_send: props.profile?.auto_send ?? false,
    is_active: props.profile?.is_active ?? true,
    currency:
        props.profile?.currency ??
        props.prefill?.currency ??
        teamCurrency.value,
    exchange_rate: props.profile?.exchange_rate ?? '1',
    discount_percent: props.profile
        ? Number(props.profile.discount_percent) || ''
        : '',
    apply_further_tax: props.profile
        ? props.profile.further_tax_rate !== null
        : (props.prefill?.apply_further_tax ?? false),
    introduction: props.profile?.introduction ?? '',
    terms: props.profile?.terms ?? '',
    notes: props.profile?.notes ?? '',
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

const submit = () => {
    form.transform((data) => ({
        ...data,
        end_date: data.end_date || null,
        lines: linesPayload(lines.value),
    }));

    if (props.profile) {
        form.put(update([slug.value, props.profile.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.profile
    ? show([slug.value, props.profile.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Customer & schedule</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field
                    label="Name"
                    :error="form.errors.title"
                    class="sm:col-span-2"
                    hint="Also the subject of each invoice, followed by the period"
                    required
                >
                    <Input
                        v-model="form.title"
                        required
                        placeholder="e.g. Website maintenance"
                    />
                </Field>
                <Field
                    label="Company"
                    :error="form.errors.company_id"
                    class="lg:col-span-2"
                >
                    <EntitySelect
                        v-model="form.company_id"
                        type="companies"
                        :initial-label="
                            profile?.company?.name ?? prefill?.company?.name
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
                        :initial-label="profile?.contact?.name"
                        placeholder="Choose a contact…"
                        clearable
                    />
                </Field>
                <Field label="Repeat" :error="form.errors.frequency" required>
                    <SelectInput
                        v-model="form.frequency"
                        :options="frequencies"
                    />
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
                <Field
                    label="First invoice on"
                    :error="form.errors.start_date"
                    required
                >
                    <Input v-model="form.start_date" type="date" required />
                </Field>
                <Field
                    label="Last invoice on or before"
                    hint="Leave empty to keep going"
                    :error="form.errors.end_date"
                >
                    <Input v-model="form.end_date" type="date" />
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

                <div class="space-y-2 text-sm sm:col-span-2 lg:col-span-4">
                    <label class="flex items-start gap-2">
                        <Checkbox
                            :model-value="form.auto_issue"
                            class="mt-0.5"
                            @update:model-value="
                                form.auto_issue = Boolean($event)
                            "
                        />
                        <span>
                            Issue each invoice automatically (otherwise it is
                            created as a draft for you to check)
                        </span>
                    </label>
                    <label
                        class="flex items-start gap-2"
                        :class="{ 'opacity-50': !form.auto_issue }"
                    >
                        <Checkbox
                            :model-value="form.auto_send"
                            :disabled="!form.auto_issue"
                            class="mt-0.5"
                            @update:model-value="
                                form.auto_send = Boolean($event)
                            "
                        />
                        <span>
                            and e-mail it to the customer with the invoice
                            e-mail template
                        </span>
                    </label>
                    <label class="flex items-start gap-2">
                        <Checkbox
                            :model-value="form.is_active"
                            class="mt-0.5"
                            @update:model-value="
                                form.is_active = Boolean($event)
                            "
                        />
                        <span>Active</span>
                    </label>
                </div>
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
        />

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ profile ? 'Save changes' : 'Create recurring invoice' }}
            </Button>
        </div>
    </form>
</template>
