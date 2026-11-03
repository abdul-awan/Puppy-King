<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney, formatNumber, formatPercent } from '@/lib/format';
import type {
    DocumentCustomer,
    DocumentLine,
    DocumentPresentation,
    TaxBreakdown,
} from '@/types';

export type DocumentSeller = {
    name: string | null;
    address: string[];
    ntn: string | null;
    strn: string | null;
    email: string | null;
    phone: string | null;
    logo: string | null;
};

const props = defineProps<{
    title: string;
    meta: { label: string; value: string | null }[];
    customer: DocumentCustomer;
    customerLabel?: string;
    lines: DocumentLine[];
    taxes: TaxBreakdown[];
    currency: string;
    subtotal: string;
    discountPercent: string;
    discountAmount: string;
    furtherTaxRate: string | null;
    furtherTaxAmount: string;
    total: string;
    introduction?: string | null;
    terms?: string | null;
    heading?: string | null;
    amountInWords?: string | null;
    seller?: DocumentSeller | null;
    extraTotals?: { label: string; value: string | number; bold?: boolean }[];
    // The document as the company chose to show it (public pages): same columns and totals as the PDF.
    presentation?: DocumentPresentation | null;
}>();

const shownTitle = computed(() => props.presentation?.title ?? props.title);
const shownMeta = computed(() => props.presentation?.meta ?? props.meta);
const shownCustomerLabel = computed(
    () =>
        props.presentation?.customer_heading ??
        props.customerLabel ??
        'Customer',
);
const shownAmountInWords = computed(() =>
    props.presentation
        ? props.presentation.amount_in_words
        : props.amountInWords,
);
const shownTerms = computed(() =>
    props.presentation ? props.presentation.terms : props.terms,
);

const showHsCode = computed(() => props.lines.some((line) => line.hs_code));
const showDiscount = computed(() =>
    props.lines.some((line) => Number(line.discount_percent) > 0),
);
const itemNumbers = computed(() => {
    let number = 0;

    return props.lines.map((line) => (line.type === 'item' ? ++number : 0));
});
const taxedTaxes = computed(() =>
    props.taxes.filter((tax) => Number(tax.rate) > 0),
);
</script>

<template>
    <div
        class="space-y-6 rounded-lg border bg-background p-4 text-sm shadow-xs sm:p-8"
    >
        <div class="flex flex-col justify-between gap-6 sm:flex-row">
            <div v-if="seller" class="space-y-0.5">
                <img
                    v-if="seller.logo"
                    :src="seller.logo"
                    alt=""
                    class="mb-2 max-h-14 max-w-52 object-contain"
                />
                <p class="text-base font-semibold">{{ seller.name }}</p>
                <p
                    v-for="line in seller.address"
                    :key="line"
                    class="text-muted-foreground"
                >
                    {{ line }}
                </p>
                <p v-if="seller.ntn" class="text-muted-foreground">
                    NTN: {{ seller.ntn }}
                </p>
                <p v-if="seller.strn" class="text-muted-foreground">
                    STRN: {{ seller.strn }}
                </p>
            </div>
            <div
                class="space-y-1 rounded-md border p-3 sm:min-w-64"
                :class="{ 'sm:order-first': !seller }"
            >
                <p class="text-xs text-muted-foreground uppercase">
                    {{ shownCustomerLabel }}
                </p>
                <p class="font-semibold">{{ customer.name ?? '—' }}</p>
                <p
                    v-if="customer.address"
                    class="whitespace-pre-line text-muted-foreground"
                >
                    {{ customer.address }}
                </p>
                <p v-if="customer.ntn" class="text-muted-foreground">
                    NTN: {{ customer.ntn }}
                </p>
                <p v-if="customer.strn" class="text-muted-foreground">
                    STRN: {{ customer.strn }}
                </p>
                <p v-if="customer.cnic" class="text-muted-foreground">
                    CNIC: {{ customer.cnic }}
                </p>
            </div>
            <div class="space-y-1 sm:text-right">
                <p class="text-xl font-bold tracking-wide uppercase">
                    {{ shownTitle }}
                </p>
                <dl class="space-y-0.5">
                    <div
                        v-for="row in shownMeta.filter((item) => item.value)"
                        :key="row.label"
                        class="flex gap-2 sm:justify-end"
                    >
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="font-medium">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <p v-if="heading" class="text-base font-semibold">{{ heading }}</p>
        <p v-if="introduction" class="whitespace-pre-line">
            {{ introduction }}
        </p>

        <div v-if="presentation" class="overflow-x-auto">
            <table class="w-full min-w-[36rem]">
                <thead>
                    <tr
                        class="border-b text-left text-xs text-muted-foreground uppercase"
                    >
                        <th
                            v-for="column in presentation.columns"
                            :key="column.key"
                            class="py-2 pr-2 last:pr-0"
                            :class="{ 'text-right': column.align === 'right' }"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in presentation.rows"
                        :key="index"
                        class="border-b last:border-0"
                    >
                        <td
                            v-if="row.type === 'section'"
                            :colspan="presentation.columns.length"
                            class="bg-muted/40 py-2 font-semibold"
                        >
                            {{ row.description }}
                        </td>
                        <td
                            v-else-if="row.type === 'text'"
                            :colspan="presentation.columns.length"
                            class="py-2 whitespace-pre-line text-muted-foreground italic"
                        >
                            {{ row.description }}
                        </td>
                        <template v-else>
                            <td
                                v-for="column in presentation.columns"
                                :key="column.key"
                                class="py-2 pr-2 align-top last:pr-0"
                                :class="{
                                    'text-right whitespace-nowrap':
                                        column.align === 'right',
                                    'text-muted-foreground':
                                        column.key === 'number',
                                    'font-medium': column.key === 'amount',
                                }"
                            >
                                <template v-if="column.key === 'description'">
                                    <span
                                        v-if="row.code"
                                        class="mr-1 text-xs text-muted-foreground"
                                        >{{ row.code }}</span
                                    >{{ row.description }}
                                    <p
                                        v-if="row.details"
                                        class="text-xs whitespace-pre-line text-muted-foreground"
                                    >
                                        {{ row.details }}
                                    </p>
                                </template>
                                <template v-else>{{
                                    row.cells?.[column.key] ?? ''
                                }}</template>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[36rem]">
                <thead>
                    <tr
                        class="border-b text-left text-xs text-muted-foreground uppercase"
                    >
                        <th class="w-8 py-2 pr-2">#</th>
                        <th class="py-2 pr-2">Description</th>
                        <th v-if="showHsCode" class="py-2 pr-2">HS code</th>
                        <th class="py-2 pr-2 text-right">Qty</th>
                        <th class="py-2 pr-2 text-right">Unit price</th>
                        <th v-if="showDiscount" class="py-2 pr-2 text-right">
                            Disc.
                        </th>
                        <th class="py-2 pr-2 text-right">Tax</th>
                        <th class="py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(line, index) in lines"
                        :key="line.id ?? index"
                        class="border-b last:border-0"
                    >
                        <template v-if="line.type === 'section'">
                            <td
                                colspan="8"
                                class="bg-muted/40 py-2 font-semibold"
                            >
                                {{ line.description }}
                            </td>
                        </template>
                        <template v-else-if="line.type === 'text'">
                            <td />
                            <td
                                colspan="7"
                                class="py-2 whitespace-pre-line text-muted-foreground italic"
                            >
                                {{ line.description }}
                            </td>
                        </template>
                        <template v-else>
                            <td
                                class="py-2 pr-2 align-top text-muted-foreground"
                            >
                                {{ itemNumbers[index] }}
                            </td>
                            <td class="py-2 pr-2 align-top">
                                {{ line.description }}
                                <p
                                    v-if="line.details"
                                    class="text-xs whitespace-pre-line text-muted-foreground"
                                >
                                    {{ line.details }}
                                </p>
                            </td>
                            <td v-if="showHsCode" class="py-2 pr-2 align-top">
                                {{ line.hs_code }}
                            </td>
                            <td
                                class="py-2 pr-2 text-right align-top whitespace-nowrap"
                            >
                                {{ formatNumber(line.quantity, 3) }}
                                {{ line.unit }}
                            </td>
                            <td
                                class="py-2 pr-2 text-right align-top whitespace-nowrap"
                            >
                                {{ formatMoney(line.unit_price, currency) }}
                            </td>
                            <td
                                v-if="showDiscount"
                                class="py-2 pr-2 text-right align-top"
                            >
                                {{
                                    Number(line.discount_percent) > 0
                                        ? formatPercent(line.discount_percent)
                                        : ''
                                }}
                            </td>
                            <td class="py-2 pr-2 text-right align-top">
                                {{
                                    Number(line.tax_rate) > 0
                                        ? formatPercent(line.tax_rate)
                                        : '—'
                                }}
                            </td>
                            <td
                                class="py-2 text-right align-top font-medium whitespace-nowrap"
                            >
                                {{ formatMoney(line.subtotal, currency) }}
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-col justify-between gap-6 sm:flex-row">
            <div class="max-w-sm space-y-1">
                <template v-if="shownAmountInWords">
                    <p class="text-xs text-muted-foreground uppercase">
                        Amount in words
                    </p>
                    <p>{{ shownAmountInWords }}</p>
                </template>
                <slot name="below-words" />
            </div>
            <dl v-if="presentation" class="w-full max-w-sm space-y-1.5">
                <div
                    v-for="row in presentation.totals"
                    :key="row.label"
                    class="flex justify-between gap-4"
                    :class="{
                        'border-t pt-2 text-base font-bold':
                            row.style === 'grand',
                        'font-semibold': row.style === 'strong',
                    }"
                >
                    <dt
                        :class="{
                            'text-muted-foreground': row.style === 'normal',
                        }"
                    >
                        {{ row.label }}
                    </dt>
                    <dd class="text-right whitespace-nowrap">
                        {{ row.value }}
                    </dd>
                </div>
                <p
                    v-if="presentation.tax_note"
                    class="text-right text-xs text-muted-foreground"
                >
                    {{ presentation.tax_note }}
                </p>
            </dl>
            <dl v-else class="w-full max-w-sm space-y-1.5">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Subtotal</dt>
                    <dd>{{ formatMoney(subtotal, currency) }}</dd>
                </div>
                <div
                    v-if="Number(discountAmount) !== 0"
                    class="flex justify-between gap-4"
                >
                    <dt class="text-muted-foreground">
                        Discount ({{ formatPercent(discountPercent) }})
                    </dt>
                    <dd>− {{ formatMoney(discountAmount, currency) }}</dd>
                </div>
                <div
                    v-for="tax in taxedTaxes"
                    :key="`${tax.rate}-${tax.name}`"
                    class="flex justify-between gap-4"
                >
                    <dt class="text-muted-foreground">
                        {{ tax.name || 'Tax' }} on
                        {{ formatMoney(tax.net, currency) }}
                    </dt>
                    <dd>{{ formatMoney(tax.tax, currency) }}</dd>
                </div>
                <div
                    v-if="Number(furtherTaxAmount) > 0"
                    class="flex justify-between gap-4"
                >
                    <dt class="text-muted-foreground">
                        Further tax ({{ formatPercent(furtherTaxRate) }})
                    </dt>
                    <dd>{{ formatMoney(furtherTaxAmount, currency) }}</dd>
                </div>
                <div
                    class="flex justify-between gap-4 border-t pt-2 text-base font-bold"
                >
                    <dt>Total</dt>
                    <dd>{{ formatMoney(total, currency) }}</dd>
                </div>
                <div
                    v-for="row in extraTotals ?? []"
                    :key="row.label"
                    class="flex justify-between gap-4"
                    :class="{ 'font-semibold': row.bold }"
                >
                    <dt :class="{ 'text-muted-foreground': !row.bold }">
                        {{ row.label }}
                    </dt>
                    <dd>{{ formatMoney(row.value, currency) }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="shownTerms" class="space-y-1">
            <p class="text-xs text-muted-foreground uppercase">
                Terms &amp; conditions
            </p>
            <p class="whitespace-pre-line">{{ shownTerms }}</p>
        </div>

        <slot />
    </div>
</template>
