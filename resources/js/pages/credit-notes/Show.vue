<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BadgeCheck, FileDown, Link2, Pencil, Send, Trash2 } from '@lucide/vue';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import {
    destroy,
    edit,
    index,
    issue,
    pdf,
    send,
    show,
} from '@/routes/credit-notes';
import { show as showInvoice } from '@/routes/invoices';
import type {
    Attachment,
    CreditNote,
    EmailDraft,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    creditNote: CreditNote;
    timeline: TimelineItem[];
    attachments: Attachment[];
    emailDraft: EmailDraft | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; creditNote: CreditNote }) => ({
        breadcrumbs: [
            { title: 'Credit notes', href: index(props.currentTeam.slug) },
            {
                title: props.creditNote.number ?? 'Draft credit note',
                href: show([props.currentTeam.slug, props.creditNote.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const isDraft = computed(() => props.creditNote.status === 'draft');
const heading = computed(() =>
    props.creditNote.number
        ? `Credit note ${props.creditNote.number}`
        : 'Draft credit note',
);

const sending = ref(false);
const deleting = ref(false);
const issuing = ref(false);

const copyLink = async () => {
    await navigator.clipboard.writeText(props.creditNote.public_url);
    toast.success('Customer link copied.');
};

const meta = computed(() => [
    { label: 'Credit note no.', value: props.creditNote.number ?? 'DRAFT' },
    { label: 'Date', value: formatDate(props.creditNote.date) },
    {
        label: 'Original invoice',
        value: props.creditNote.invoice?.number ?? null,
    },
    {
        label: 'Invoice date',
        value: props.creditNote.invoice
            ? formatDate(props.creditNote.invoice.date)
            : null,
    },
]);
</script>

<template>
    <Head :title="heading" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="heading" :description="creditNote.title">
            <template #badges>
                <Pill :color="creditNote.status_color">{{
                    creditNote.status_label
                }}</Pill>
                <Pill v-if="creditNote.restock" color="teal">Restock</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(creditNote.total, creditNote.currency)
                    }}</span>
                    · {{ creditNote.customer.name }}
                    <template v-if="creditNote.invoice">
                        · Invoice
                        <Link
                            :href="
                                showInvoice([slug, creditNote.invoice.id]).url
                            "
                            class="hover:underline"
                            >{{ creditNote.invoice.number }}</Link
                        >
                    </template>
                </p>
            </template>
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="pdf([slug, creditNote.id]).url" target="_blank"
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
                            <Link :href="edit([slug, creditNote.id]).url"
                                ><Pencil /> Edit</Link
                            >
                        </Button>
                        <Button size="sm" @click="issuing = true">
                            <BadgeCheck /> Issue credit note
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="outline" size="sm" @click="copyLink">
                            <Link2 /> Customer link
                        </Button>
                        <Button
                            v-if="emailDraft"
                            size="sm"
                            @click="sending = true"
                        >
                            <Send />
                            {{ creditNote.sent_at ? 'Send again' : 'Send' }}
                        </Button>
                    </template>
                </template>
            </template>
        </PageHeader>

        <InputError :message="errors.credit_note" />

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
                    title="Credit note"
                    :meta="meta"
                    :customer="creditNote.customer"
                    :lines="creditNote.lines ?? []"
                    :taxes="creditNote.taxes ?? []"
                    :currency="creditNote.currency"
                    :subtotal="creditNote.subtotal"
                    :discount-percent="creditNote.discount_percent"
                    :discount-amount="creditNote.discount_amount"
                    :further-tax-rate="creditNote.further_tax_rate"
                    :further-tax-amount="creditNote.further_tax_amount"
                    :total="creditNote.total"
                    :introduction="creditNote.introduction"
                    :heading="creditNote.title"
                >
                    <p v-if="creditNote.reason" class="text-sm">
                        <span class="text-muted-foreground">Reason:</span>
                        {{ creditNote.reason }}
                    </p>
                </DocumentView>
            </TabsContent>
            <TabsContent value="timeline">
                <Card>
                    <CardContent class="pt-6">
                        <Timeline
                            :items="timeline"
                            notable-type="credit_note"
                            :notable-id="creditNote.id"
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
                            attachable-type="credit_note"
                            :attachable-id="creditNote.id"
                            :can-write="canEdit('invoicing')"
                        />
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <SendDocumentDialog
            v-if="emailDraft"
            v-model:open="sending"
            :title="`Send credit note ${creditNote.number}`"
            :draft="emailDraft"
            :url="send([slug, creditNote.id]).url"
            :attachment-name="`credit-note-${creditNote.number}.pdf`"
        />

        <ConfirmDialog
            v-model:open="issuing"
            title="Issue this credit note?"
            description="It gets the next credit note number and lowers the balance of the invoice. It cannot be changed afterwards."
            confirm-label="Issue credit note"
            @confirm="
                issuing = false;
                router.post(
                    issue([slug, creditNote.id]).url,
                    {},
                    { preserveScroll: true },
                );
            "
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete draft credit note?"
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, creditNote.id]).url)"
        />
    </div>
</template>
