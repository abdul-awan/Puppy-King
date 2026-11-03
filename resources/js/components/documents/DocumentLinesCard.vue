<script setup lang="ts">
import Field from '@/components/app/Field.vue';
import LineItemsEditor from '@/components/documents/LineItemsEditor.vue';
import TotalsPanel from '@/components/documents/TotalsPanel.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import type { DocumentTotals, EditableLine } from '@/lib/documents';
import type { TaxRateOption } from '@/types';

/**
 * The "Lines" card of the document forms: introduction, line editor and totals.
 */
defineProps<{
    taxRates: TaxRateOption[];
    currency: string;
    totals: DocumentTotals;
    furtherTaxRate: string | null;
    errors: Partial<Record<string, string>>;
    /** Discount and further tax are shown but cannot be changed. */
    lockedTotals?: boolean;
}>();

const lines = defineModel<EditableLine[]>('lines', { required: true });
const introduction = defineModel<string | null>('introduction', {
    default: '',
});
const discountPercent = defineModel<string | number>('discountPercent', {
    required: true,
});
const applyFurtherTax = defineModel<boolean>('applyFurtherTax', {
    required: true,
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Lines</CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <Field
                label="Introduction"
                hint="Printed above the lines"
                :error="errors.introduction"
            >
                <Textarea v-model="introduction" rows="2" />
            </Field>
            <LineItemsEditor
                v-model="lines"
                :tax-rates="taxRates"
                :currency="currency"
                :line-totals="totals.lines"
                :errors="errors"
            />
            <div class="flex justify-end">
                <TotalsPanel
                    v-model:discount-percent="discountPercent"
                    v-model:apply-further-tax="applyFurtherTax"
                    :totals="totals"
                    :currency="currency"
                    :further-tax-rate="furtherTaxRate"
                    :locked="lockedTotals"
                    class="w-full max-w-md"
                />
            </div>
        </CardContent>
    </Card>
</template>
