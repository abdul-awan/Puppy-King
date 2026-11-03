<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, FileDown } from '@lucide/vue';
import { computed } from 'vue';
import DocumentView from '@/components/documents/DocumentView.vue';
import type { DocumentSeller } from '@/components/documents/DocumentView.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatMoney } from '@/lib/format';
import type { CreditNote, DocumentPresentation, Invoice } from '@/types';

const props = defineProps<{
    kind: 'invoice' | 'credit_note';
    document: Invoice | CreditNote;
    title: string;
    meta: { label: string; value: string | null }[];
    seller: DocumentSeller;
    amountInWords: string | null;
    pdfUrl: string;
    payment: string[] | null;
    balance: { paid: string; credited: string; balance_due: string } | null;
    presentation: DocumentPresentation;
}>();

const invoice = computed(() =>
    props.kind === 'invoice' ? (props.document as Invoice) : null,
);

const extraTotals = computed(() => {
    if (!props.balance) {
        return [];
    }

    const rows: { label: string; value: string; bold?: boolean }[] = [];

    if (Number(props.balance.paid)) {
        rows.push({ label: 'Paid', value: props.balance.paid });
    }

    if (Number(props.balance.credited)) {
        rows.push({ label: 'Credited', value: props.balance.credited });
    }

    if (rows.length) {
        rows.push({
            label: 'Balance due',
            value: props.balance.balance_due,
            bold: true,
        });
    }

    return rows;
});
</script>

<template>
    <Head :title="`${title} ${document.number ?? ''} — ${seller.name ?? ''}`" />

    <div class="min-h-screen bg-muted/40 py-6 sm:py-10">
        <div class="mx-auto w-full max-w-4xl space-y-6 px-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">
                        {{ title }} from
                    </p>
                    <p class="text-lg font-semibold">{{ seller.name }}</p>
                </div>
                <Button variant="outline" as-child>
                    <a :href="pdfUrl" target="_blank"
                        ><FileDown /> Download PDF</a
                    >
                </Button>
            </div>

            <div
                v-if="invoice?.status === 'paid'"
                class="flex items-center gap-3 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:bg-green-950/40 dark:text-green-200"
            >
                <CheckCircle2 class="size-5 shrink-0" />
                <p>This invoice has been paid. Thank you!</p>
            </div>
            <div
                v-else-if="invoice?.status === 'open'"
                class="rounded-lg border p-4"
                :class="
                    invoice.is_overdue
                        ? 'border-red-300 bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200'
                        : 'bg-background'
                "
            >
                <p class="text-lg font-semibold">
                    {{ formatMoney(invoice.balance_due, invoice.currency) }}
                    due
                    {{
                        invoice.is_overdue
                            ? `since ${formatDate(invoice.due_date)}`
                            : `by ${formatDate(invoice.due_date)}`
                    }}
                </p>
                <p class="text-sm opacity-80">
                    Please quote {{ invoice.number }} with your payment.
                </p>
            </div>

            <DocumentView
                :title="title"
                :meta="meta"
                :customer="document.customer"
                :lines="document.lines ?? []"
                :taxes="document.taxes ?? []"
                :currency="document.currency"
                :subtotal="document.subtotal"
                :discount-percent="document.discount_percent"
                :discount-amount="document.discount_amount"
                :further-tax-rate="document.further_tax_rate"
                :further-tax-amount="document.further_tax_amount"
                :total="document.total"
                :introduction="document.introduction"
                :terms="document.terms"
                :heading="document.title"
                :amount-in-words="amountInWords"
                :seller="seller"
                :extra-totals="extraTotals"
                :presentation="presentation"
            />

            <Card v-if="payment && invoice?.status === 'open'">
                <CardHeader>
                    <CardTitle>How to pay</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-sm">
                    <p v-for="line in payment" :key="line">{{ line }}</p>
                </CardContent>
            </Card>

            <p class="text-center text-xs text-muted-foreground">
                Questions? Contact {{ seller.name
                }}{{ seller.email ? ` at ${seller.email}` : ''
                }}{{ seller.phone ? ` or ${seller.phone}` : '' }}.
            </p>
        </div>
    </div>
</template>
