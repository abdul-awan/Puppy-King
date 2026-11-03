<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatMoney, formatPercent } from '@/lib/format';
import type { DocumentTotals } from '@/lib/documents';

defineProps<{
    totals: DocumentTotals;
    currency: string;
    furtherTaxRate: string | null;
    /** Show the discount and further tax without letting them be changed (e.g. credit notes follow their invoice). */
    locked?: boolean;
}>();

const discountPercent = defineModel<string | number>('discountPercent', {
    required: true,
});
const applyFurtherTax = defineModel<boolean>('applyFurtherTax', {
    required: true,
});
</script>

<template>
    <div class="space-y-2 text-sm">
        <div class="flex items-center justify-between gap-4">
            <span class="text-muted-foreground">Subtotal</span>
            <span>{{ formatMoney(totals.subtotal, currency) }}</span>
        </div>
        <div
            v-if="!locked || totals.discountAmount"
            class="flex items-center justify-between gap-4"
        >
            <span v-if="locked" class="text-muted-foreground">
                Discount {{ formatPercent(discountPercent) }}
            </span>
            <label v-else class="flex items-center gap-2 text-muted-foreground">
                Discount
                <Input
                    v-model="discountPercent"
                    type="number"
                    step="any"
                    min="0"
                    max="100"
                    class="h-7 w-20 text-right"
                    aria-label="Document discount %"
                />
                %
            </label>
            <span>{{
                totals.discountAmount
                    ? `− ${formatMoney(totals.discountAmount, currency)}`
                    : '—'
            }}</span>
        </div>
        <div
            v-for="tax in totals.taxes"
            :key="`${tax.rate}-${tax.name}`"
            class="flex items-center justify-between gap-4"
        >
            <span class="text-muted-foreground">
                {{ tax.name || `Tax ${formatPercent(tax.rate)}` }} on
                {{ formatMoney(tax.net, currency) }}
            </span>
            <span>{{ formatMoney(tax.tax, currency) }}</span>
        </div>
        <div
            v-if="furtherTaxRate !== null && (!locked || applyFurtherTax)"
            class="flex items-center justify-between gap-4"
        >
            <span v-if="locked" class="text-muted-foreground">
                Further tax {{ formatPercent(furtherTaxRate) }}
            </span>
            <label v-else class="flex items-center gap-2 text-muted-foreground">
                <Checkbox
                    :model-value="applyFurtherTax"
                    @update:model-value="applyFurtherTax = Boolean($event)"
                />
                Further tax {{ formatPercent(furtherTaxRate) }} (unregistered
                buyer)
            </label>
            <span>{{ formatMoney(totals.furtherTaxAmount, currency) }}</span>
        </div>
        <div
            class="flex items-center justify-between gap-4 border-t pt-2 text-base font-semibold"
        >
            <span>Total</span>
            <span>{{ formatMoney(totals.total, currency) }}</span>
        </div>
    </div>
</template>
