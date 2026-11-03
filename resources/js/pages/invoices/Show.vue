<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Banknote,
    BellRing,
    CopyPlus,
    FileDown,
    FileMinus,
    Link2,
    Mail,
    Pencil,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import DocumentView from '@/components/documents/DocumentView.vue';
import SendDocumentDialog from '@/components/documents/SendDocumentDialog.vue';
import InputError from '@/components/InputError.vue';
import RecordPaymentDialog from '@/components/invoicing/RecordPaymentDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { show as showCreditNote } from '@/routes/credit-notes';
import { show as showDeal } from '@/routes/deals';
import {
    credit,
    destroy,
    duplicate,
    edit,
    fbrNumber,
    index,
    issue,
    markSent,
    pdf,
    remind,
    send,
    show,
} from '@/routes/invoices';
import { destroy as destroyPayment } from '@/routes/invoices/payments';
import { show as showQuotation } from '@/routes/quotations';
import { show as showRecurring } from '@/routes/recurring-invoices';
import type {
    Attachment,
    EmailDraft,
    Invoice,
    InvoicePayment,
    Option,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    invoice: Invoice;
    timeline: TimelineItem[];
    attachments: Attachment[];
    emailDraft: EmailDraft | null;
    reminderDraft: EmailDraft | null;
    paymentMethods: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; invoice: Invoice }) => ({
        breadcrumbs: [
            { title: 'Invoices', href: index(props.currentTeam.slug) },
            {
                title: props.invoice.number ?? 'Draft invoice',
                href: show([props.currentTeam.slug, props.invoice.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const isDraft = computed(() => props.invoice.status === 'draft');
const isOpen = computed(() => props.invoice.status === 'open');
const heading = computed(() =>
    props.invoice.number ? `Invoice ${props.invoice.number}` : 'Draft invoice',
);

const sending = ref(false);
const reminding = ref(false);
const paying = ref(false);
const deleting = ref(false);
const issuing = ref(false);
const paymentToDelete = ref<InvoicePayment | null>(null);

const post = (url: string) => router.post(url, {}, { preserveScroll: true });

const fbrForm = useForm({
    fbr_invoice_number: props.invoice.fbr_invoice_number ?? '',
});

const saveFbrNumber = () => {
    fbrForm.patch(fbrNumber([slug.value, props.invoice.id]).url, {
        preserveScroll: true,
    });
};

const copyLink = async () => {
    await navigator.clipboard.writeText(props.invoice.public_url);
    toast.success(
        'Customer link copied. Your customer can view and download the invoice there.',
    );
};

const removePayment = () => {
    if (!paymentToDelete.value) {
        return;
    }

    router.delete(
        destroyPayment([slug.value, props.invoice.id, paymentToDelete.value.id])
            .url,
        {
            preserveScroll: true,
            onFinish: () => {
                paymentToDelete.value = null;
            },
        },
    );
};

const meta = computed(() => [
    { label: 'Invoice no.', value: props.invoice.number ?? 'DRAFT' },
    { label: 'Date', value: formatDate(props.invoice.date) },
    {
        label: 'Due date',
        value: props.invoice.due_date
            ? formatDate(props.invoice.due_date)
            : null,
    },
    { label: 'FBR invoice no.', value: props.invoice.fbr_invoice_number },
]);

const hasWithholding = (payment: InvoicePayment) =>
    Number(payment.income_tax_withheld) > 0 ||
    Number(payment.sales_tax_withheld) > 0;
</script>

<template>
    <Head :title="heading" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="heading" :description="invoice.title">
            <template #badges>
                <Pill :color="invoice.status_color">{{
                    invoice.status_label
                }}</Pill>
                <Pill v-if="invoice.sent_at" color="gray">Sent</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(invoice.total, invoice.currency)
                    }}</span>
                    ·
                    <Link
                        v-if="invoice.company"
                        :href="showCompany([slug, invoice.company.id]).url"
                        class="hover:underline"
                    >
                        {{ invoice.customer.name }}
                    </Link>
                    <span v-else>{{ invoice.customer.name }}</span>
                    <template v-if="invoice.deal">
                        · Deal
                        <Link
                            :href="showDeal([slug, invoice.deal.id]).url"
                            class="hover:underline"
                            >{{ invoice.deal.title }}</Link
                        >
                    </template>
                    <template v-if="invoice.quotation">
                        · Quotation
                        <Link
                            :href="
                                showQuotation([slug, invoice.quotation.id]).url
                            "
                            class="hover:underline"
                            >{{ invoice.quotation.number }}</Link
                        >
                    </template>
                    <template v-if="invoice.recurring_invoice">
                        · From
                        <Link
                            :href="
                                showRecurring([
                                    slug,
                                    invoice.recurring_invoice.id,
                                ]).url
                            "
                            class="hover:underline"
                            >{{ invoice.recurring_invoice.title }}</Link
                        >
                    </template>
                </p>
            </template>
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="pdf([slug, invoice.id]).url" target="_blank"
                        ><FileDown /> PDF</a
                    >
                </Button>
                <template v-if="canEdit('invoicing')">
                    <template v-if="isDraft">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="deleting = true"
                        >
                            <Trash2 />
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="edit([slug, invoice.id]).url"
                                ><Pencil /> Edit</Link
                            >
                        </Button>
                        <Button size="sm" @click="issuing = true">
                            <BadgeCheck /> Issue invoice
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="outline" size="sm" @click="copyLink">
                            <Link2 /> Customer link
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="post(duplicate([slug, invoice.id]).url)"
                        >
                            <CopyPlus /> Duplicate
                        </Button>
                        <Button
                            v-if="Number(invoice.creditable_amount) > 0"
                            variant="outline"
                            size="sm"
                            @click="post(credit([slug, invoice.id]).url)"
                        >
                            <FileMinus /> Credit note
                        </Button>
                        <Button
                            v-if="emailDraft"
                            variant="outline"
                            size="sm"
                            @click="sending = true"
                        >
                            <Send />
                            {{ invoice.sent_at ? 'Send again' : 'Send' }}
                        </Button>
                        <Button v-if="isOpen" size="sm" @click="paying = true">
                            <Banknote /> Record payment
                        </Button>
                    </template>
                </template>
            </template>
        </PageHeader>

        <InputError :message="errors.invoice" />

        <div
            v-if="canEdit('invoicing') && (isDraft || isOpen)"
            class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 p-3 text-sm"
        >
            <template v-if="isDraft">
                <span class="text-muted-foreground">
                    This invoice is a draft. Issue it to give it its number;
                    after that it can only be corrected with a credit note.
                </span>
            </template>
            <template v-else>
                <span
                    :class="
                        invoice.is_overdue
                            ? 'font-medium text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    <template v-if="invoice.is_overdue">
                        {{ invoice.days_overdue }} day(s) overdue (due
                        {{ formatDate(invoice.due_date) }}).
                    </template>
                    <template v-else>
                        Due {{ formatDate(invoice.due_date) }}.
                    </template>
                    <template v-if="invoice.reminder_count">
                        {{ invoice.reminder_count }} reminder(s) sent, last on
                        {{ formatDateTime(invoice.last_reminder_at) }}.
                    </template>
                </span>
                <Button
                    v-if="reminderDraft"
                    size="sm"
                    variant="ghost"
                    @click="reminding = true"
                >
                    <BellRing /> Send reminder
                </Button>
                <Button
                    v-if="!invoice.sent_at"
                    size="sm"
                    variant="ghost"
                    @click="post(markSent([slug, invoice.id]).url)"
                >
                    <Mail /> Mark as sent without e-mailing
                </Button>
            </template>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <Tabs default-value="document">
                    <TabsList>
                        <TabsTrigger value="document">Document</TabsTrigger>
                        <TabsTrigger value="timeline">History</TabsTrigger>
                        <TabsTrigger value="files"
                            >Files ({{ attachments.length }})</TabsTrigger
                        >
                    </TabsList>
                    <TabsContent value="document">
                        <DocumentView
                            title="Invoice"
                            :meta="meta"
                            :customer="invoice.customer"
                            :lines="invoice.lines ?? []"
                            :taxes="invoice.taxes ?? []"
                            :currency="invoice.currency"
                            :subtotal="invoice.subtotal"
                            :discount-percent="invoice.discount_percent"
                            :discount-amount="invoice.discount_amount"
                            :further-tax-rate="invoice.further_tax_rate"
                            :further-tax-amount="invoice.further_tax_amount"
                            :total="invoice.total"
                            :introduction="invoice.introduction"
                            :terms="invoice.terms"
                            :heading="invoice.title"
                        >
                            <div
                                v-if="invoice.notes"
                                class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
                            >
                                <p class="text-xs font-medium uppercase">
                                    Internal notes
                                </p>
                                <p class="whitespace-pre-line">
                                    {{ invoice.notes }}
                                </p>
                            </div>
                        </DocumentView>
                    </TabsContent>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="invoice"
                                    :notable-id="invoice.id"
                                    :can-write="canEdit('invoicing')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="files">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="invoice"
                                    :attachable-id="invoice.id"
                                    :can-write="canEdit('invoicing')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Payment</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <dl class="space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Total</dt>
                                <dd>
                                    {{
                                        formatMoney(
                                            invoice.total,
                                            invoice.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="Number(invoice.amount_paid)"
                                class="flex justify-between"
                            >
                                <dt class="text-muted-foreground">Paid</dt>
                                <dd>
                                    −
                                    {{
                                        formatMoney(
                                            invoice.amount_paid,
                                            invoice.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="Number(invoice.amount_credited)"
                                class="flex justify-between"
                            >
                                <dt class="text-muted-foreground">Credited</dt>
                                <dd>
                                    −
                                    {{
                                        formatMoney(
                                            invoice.amount_credited,
                                            invoice.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between border-t pt-1 text-base font-semibold"
                            >
                                <dt>
                                    {{
                                        Number(invoice.balance_due) < 0
                                            ? 'To refund'
                                            : 'Balance due'
                                    }}
                                </dt>
                                <dd>
                                    {{
                                        formatMoney(
                                            Math.abs(
                                                Number(invoice.balance_due),
                                            ),
                                            invoice.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <p
                            v-if="invoice.paid_at"
                            class="text-green-700 dark:text-green-400"
                        >
                            Paid in full on
                            {{ formatDateTime(invoice.paid_at) }}.
                        </p>

                        <div v-if="invoice.payments?.length" class="space-y-2">
                            <p class="text-xs font-medium uppercase">
                                Payments
                            </p>
                            <ul class="divide-y rounded-md border">
                                <li
                                    v-for="payment in invoice.payments"
                                    :key="payment.id"
                                    class="flex items-start justify-between gap-2 p-2"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{
                                                formatMoney(
                                                    payment.settled_amount,
                                                    invoice.currency,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ formatDate(payment.date) }} ·
                                            {{ payment.method_label }}
                                            <template v-if="payment.reference">
                                                · {{ payment.reference }}
                                            </template>
                                        </p>
                                        <p
                                            v-if="hasWithholding(payment)"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Received
                                            {{
                                                formatMoney(
                                                    payment.amount,
                                                    invoice.currency,
                                                )
                                            }}
                                            <template
                                                v-if="
                                                    Number(
                                                        payment.income_tax_withheld,
                                                    )
                                                "
                                            >
                                                · income tax
                                                {{
                                                    formatMoney(
                                                        payment.income_tax_withheld,
                                                        invoice.currency,
                                                    )
                                                }}
                                            </template>
                                            <template
                                                v-if="
                                                    Number(
                                                        payment.sales_tax_withheld,
                                                    )
                                                "
                                            >
                                                · sales tax
                                                {{
                                                    formatMoney(
                                                        payment.sales_tax_withheld,
                                                        invoice.currency,
                                                    )
                                                }}
                                            </template>
                                        </p>
                                    </div>
                                    <Button
                                        v-if="canEdit('invoicing')"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        aria-label="Remove payment"
                                        @click="paymentToDelete = payment"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </li>
                            </ul>
                        </div>

                        <div
                            v-if="invoice.credit_notes?.length"
                            class="space-y-2"
                        >
                            <p class="text-xs font-medium uppercase">
                                Credit notes
                            </p>
                            <ul class="divide-y rounded-md border">
                                <li
                                    v-for="note in invoice.credit_notes"
                                    :key="note.id"
                                    class="flex items-center justify-between gap-2 p-2"
                                >
                                    <Link
                                        :href="
                                            showCreditNote([slug, note.id]).url
                                        "
                                        class="font-medium hover:underline"
                                    >
                                        {{ note.number ?? 'Draft' }}
                                    </Link>
                                    <Pill :color="note.status_color">{{
                                        note.status_label
                                    }}</Pill>
                                    <span>{{
                                        formatMoney(note.total, note.currency)
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="!isDraft">
                    <CardHeader>
                        <CardTitle>FBR</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            v-if="canEdit('invoicing')"
                            class="flex gap-2"
                            @submit.prevent="saveFbrNumber"
                        >
                            <Input
                                v-model="fbrForm.fbr_invoice_number"
                                placeholder="FBR invoice number"
                                aria-label="FBR invoice number"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="
                                    fbrForm.processing || !fbrForm.isDirty
                                "
                            >
                                Save
                            </Button>
                        </form>
                        <p v-else class="text-sm">
                            {{ invoice.fbr_invoice_number ?? '—' }}
                        </p>
                        <InputError
                            class="mt-1"
                            :message="fbrForm.errors.fbr_invoice_number"
                        />
                        <p class="mt-2 text-xs text-muted-foreground">
                            The number FBR's system gives this invoice, if you
                            report it (it is printed on the PDF).
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <SendDocumentDialog
            v-if="emailDraft"
            v-model:open="sending"
            :title="`Send invoice ${invoice.number}`"
            :draft="emailDraft"
            :url="send([slug, invoice.id]).url"
            :attachment-name="`invoice-${invoice.number}.pdf`"
        />

        <SendDocumentDialog
            v-if="reminderDraft"
            v-model:open="reminding"
            :title="`Payment reminder for ${invoice.number}`"
            :draft="reminderDraft"
            :url="remind([slug, invoice.id]).url"
            :attachment-name="`invoice-${invoice.number}.pdf`"
        />

        <RecordPaymentDialog
            v-if="isOpen"
            v-model:open="paying"
            :invoice="invoice"
            :methods="paymentMethods"
        />

        <ConfirmDialog
            v-model:open="issuing"
            title="Issue this invoice?"
            description="It gets the next invoice number and can no longer be changed or deleted — only corrected with a credit note. Tracked products are taken out of stock."
            confirm-label="Issue invoice"
            @confirm="
                issuing = false;
                post(issue([slug, invoice.id]).url);
            "
        />

        <ConfirmDialog
            :open="paymentToDelete !== null"
            title="Remove this payment?"
            description="Use this for a bounced cheque or a payment recorded by mistake. The invoice balance goes back up."
            confirm-label="Remove payment"
            destructive
            @update:open="paymentToDelete = $event ? paymentToDelete : null"
            @confirm="removePayment"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete draft invoice?"
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, invoice.id]).url)"
        />
    </div>
</template>
