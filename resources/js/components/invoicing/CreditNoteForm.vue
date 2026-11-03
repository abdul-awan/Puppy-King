<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Field from '@/components/app/Field.vue';
import DocumentLinesCard from '@/components/documents/DocumentLinesCard.vue';
import DocumentNotesCard from '@/components/documents/DocumentNotesCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import {
    calculateTotals,
    linesPayload,
    toEditableLines,
} from '@/lib/documents';
import type { EditableLine } from '@/lib/documents';
import { formatDate, formatMoney } from '@/lib/format';
import { show, update } from '@/routes/credit-notes';
import type { CreditNote, TaxRateOption } from '@/types';

const props = defineProps<{
    creditNote: CreditNote;
    taxRates: TaxRateOption[];
}>();

const { slug } = useTeam();

const lines = ref<EditableLine[]>(
    toEditableLines(props.creditNote.lines ?? []),
);

// Discount and further tax follow the invoice being credited.
const discountPercent = ref<string | number>(
    Number(props.creditNote.discount_percent) || '',
);
const applyFurtherTax = ref(props.creditNote.further_tax_rate !== null);

const form = useForm({
    title: props.creditNote.title ?? '',
    date: props.creditNote.date,
    reason: props.creditNote.reason ?? '',
    restock: props.creditNote.restock,
    introduction: props.creditNote.introduction ?? '',
    notes: props.creditNote.notes ?? '',
    issue_now: false,
});

const totals = computed(() =>
    calculateTotals(
        lines.value,
        props.taxRates,
        discountPercent.value,
        props.creditNote.further_tax_rate,
    ),
);

const creditable = computed(() =>
    Number(props.creditNote.invoice?.creditable_amount ?? 0),
);
const tooMuch = computed(() => totals.value.total > creditable.value + 0.001);

const submit = (issue: boolean) => {
    form.issue_now = issue;
    form.transform((data) => ({ ...data, lines: linesPayload(lines.value) }));
    form.put(update([slug.value, props.creditNote.id]).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit(false)">
        <Card>
            <CardHeader>
                <CardTitle>Credit note details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-if="creditNote.invoice"
                    class="rounded-md bg-muted/50 p-3 text-sm sm:col-span-2 lg:col-span-4"
                >
                    Credits invoice
                    <strong>{{ creditNote.invoice.number }}</strong> of
                    {{ formatDate(creditNote.invoice.date) }} ({{
                        formatMoney(
                            creditNote.invoice.total,
                            creditNote.currency,
                        )
                    }}) for {{ creditNote.customer.name }}. Up to
                    <strong>{{
                        formatMoney(creditable, creditNote.currency)
                    }}</strong>
                    can still be credited.
                </div>
                <Field
                    label="Subject"
                    :error="form.errors.title"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.title" />
                </Field>
                <Field label="Date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field
                    label="Reason"
                    :error="form.errors.reason"
                    hint="Printed on the credit note"
                >
                    <Input
                        v-model="form.reason"
                        placeholder="e.g. Goods returned"
                    />
                </Field>
                <label
                    class="flex items-start gap-2 text-sm sm:col-span-2 lg:col-span-4"
                >
                    <Checkbox
                        :model-value="form.restock"
                        class="mt-0.5"
                        @update:model-value="form.restock = Boolean($event)"
                    />
                    <span>
                        Goods were returned — put tracked products back in stock
                        when the credit note is issued
                    </span>
                </label>
            </CardContent>
        </Card>

        <DocumentLinesCard
            v-model:lines="lines"
            v-model:introduction="form.introduction"
            v-model:discount-percent="discountPercent"
            v-model:apply-further-tax="applyFurtherTax"
            :tax-rates="taxRates"
            :currency="creditNote.currency"
            :totals="totals"
            :further-tax-rate="creditNote.further_tax_rate"
            :errors="form.errors"
            locked-totals
        />

        <DocumentNotesCard
            v-model:notes="form.notes"
            :errors="form.errors"
            :show-terms="false"
        />

        <p v-if="tooMuch" class="text-right text-sm text-destructive">
            The credit note is more than can still be credited on the invoice.
        </p>

        <div
            class="flex flex-col-reverse items-stretch justify-end gap-2 sm:flex-row sm:items-center"
        >
            <Button variant="ghost" as-child>
                <Link :href="show([slug, creditNote.id]).url">Cancel</Link>
            </Button>
            <Button type="submit" variant="outline" :disabled="form.processing">
                Save draft
            </Button>
            <Button
                type="button"
                :disabled="form.processing || tooMuch"
                @click="submit(true)"
            >
                Save & issue
            </Button>
        </div>
    </form>
</template>
