<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    CopyPlus,
    FileDown,
    FolderKanban,
    Link2,
    Mail,
    Pencil,
    ReceiptText,
    RotateCcw,
    Send,
    Trash2,
    X,
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
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { show as showDeal } from '@/routes/deals';
import {
    accept,
    decline,
    destroy,
    duplicate,
    edit,
    index,
    invoice,
    markSent,
    pdf,
    reopen,
    send,
    show,
} from '@/routes/quotations';
import { create as createProject } from '@/routes/projects';
import type {
    Attachment,
    EmailDraft,
    Quotation,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    quotation: Quotation;
    timeline: TimelineItem[];
    attachments: Attachment[];
    emailDraft: EmailDraft;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; quotation: Quotation }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index(props.currentTeam.slug) },
            {
                title: props.quotation.number ?? 'Quotation',
                href: show([props.currentTeam.slug, props.quotation.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const sending = ref(false);
const deleting = ref(false);
const declining = ref(false);
const declineReason = ref('');

const editable = computed(() =>
    ['draft', 'sent'].includes(props.quotation.status),
);
const canInvoice = computed(
    () =>
        canEdit('invoicing') &&
        ['sent', 'accepted'].includes(props.quotation.status),
);
const post = (url: string) => router.post(url, {}, { preserveScroll: true });

const copyLink = async () => {
    await navigator.clipboard.writeText(props.quotation.public_url);
    toast.success(
        'Customer link copied. Share it with your customer to view and accept the quotation.',
    );
};

const submitDecline = () => {
    router.post(
        decline([slug.value, props.quotation.id]).url,
        { reason: declineReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                declining.value = false;
            },
        },
    );
};

const meta = computed(() => [
    { label: 'Quotation no.', value: props.quotation.number },
    { label: 'Date', value: formatDate(props.quotation.date) },
    {
        label: 'Valid until',
        value: props.quotation.valid_until
            ? formatDate(props.quotation.valid_until)
            : null,
    },
]);
</script>

<template>
    <Head :title="`Quotation ${quotation.number}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`Quotation ${quotation.number}`"
            :description="quotation.title"
        >
            <template #badges>
                <Pill :color="quotation.status_color">{{
                    quotation.status_label
                }}</Pill>
                <Pill v-if="quotation.invoiced_at" color="purple"
                    >Invoiced</Pill
                >
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(quotation.total, quotation.currency)
                    }}</span>
                    ·
                    <Link
                        v-if="quotation.company"
                        :href="showCompany([slug, quotation.company.id]).url"
                        class="hover:underline"
                    >
                        {{ quotation.customer.name }}
                    </Link>
                    <span v-else>{{ quotation.customer.name }}</span>
                    <template v-if="quotation.deal">
                        · Deal
                        <Link
                            :href="showDeal([slug, quotation.deal.id]).url"
                            class="hover:underline"
                            >{{ quotation.deal.title }}</Link
                        >
                    </template>
                </p>
            </template>
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a :href="pdf([slug, quotation.id]).url" target="_blank"
                        ><FileDown /> PDF</a
                    >
                </Button>
                <template v-if="canEdit('quotations')">
                    <Button
                        v-if="quotation.status !== 'draft'"
                        variant="outline"
                        size="sm"
                        @click="copyLink"
                    >
                        <Link2 /> Customer link
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="post(duplicate([slug, quotation.id]).url)"
                    >
                        <CopyPlus /> Duplicate
                    </Button>
                    <Button
                        v-if="editable"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="edit([slug, quotation.id]).url"
                            ><Pencil /> Edit</Link
                        >
                    </Button>
                    <Button variant="outline" size="sm" @click="deleting = true"
                        ><Trash2
                    /></Button>
                    <Button v-if="editable" size="sm" @click="sending = true">
                        <Send />
                        {{
                            quotation.status === 'draft' ? 'Send' : 'Send again'
                        }}
                    </Button>
                    <Button
                        v-if="
                            quotation.status === 'accepted' &&
                            canEdit('projects')
                        "
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link
                            :href="
                                createProject(slug, {
                                    query: { quotation: quotation.id },
                                }).url
                            "
                            ><FolderKanban /> Project</Link
                        >
                    </Button>
                    <Button
                        v-if="canInvoice"
                        size="sm"
                        :variant="
                            quotation.status === 'accepted'
                                ? 'default'
                                : 'outline'
                        "
                        @click="post(invoice([slug, quotation.id]).url)"
                    >
                        <ReceiptText /> Create invoice
                    </Button>
                </template>
            </template>
        </PageHeader>

        <InputError :message="errors.quotation" />

        <div
            v-if="canEdit('quotations')"
            class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 p-3 text-sm"
        >
            <template v-if="quotation.status === 'draft'">
                <span class="text-muted-foreground"
                    >This quotation is a draft.</span
                >
                <Button
                    size="sm"
                    variant="ghost"
                    @click="post(markSent([slug, quotation.id]).url)"
                >
                    <Mail /> Mark as sent without e-mailing
                </Button>
            </template>
            <template v-else-if="quotation.status === 'sent'">
                <span class="text-muted-foreground">
                    Sent {{ formatDateTime(quotation.sent_at) }}.
                    {{
                        quotation.is_expired
                            ? 'The validity date has passed.'
                            : 'Waiting for the customer.'
                    }}
                </span>
                <Button
                    size="sm"
                    variant="ghost"
                    class="text-green-700"
                    @click="post(accept([slug, quotation.id]).url)"
                >
                    <Check /> Customer accepted
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    @click="declining = true"
                >
                    <X /> Customer declined
                </Button>
            </template>
            <template v-else>
                <span class="text-muted-foreground">
                    <template v-if="quotation.status === 'accepted'">
                        Accepted {{ formatDateTime(quotation.accepted_at)
                        }}{{
                            quotation.accepted_by_name
                                ? ` by ${quotation.accepted_by_name}`
                                : ''
                        }}.
                    </template>
                    <template v-else>
                        Declined {{ formatDateTime(quotation.declined_at)
                        }}{{
                            quotation.decline_reason
                                ? `: ${quotation.decline_reason}`
                                : ''
                        }}.
                    </template>
                </span>
                <Button
                    size="sm"
                    variant="ghost"
                    @click="post(reopen([slug, quotation.id]).url)"
                >
                    <RotateCcw /> Reopen
                </Button>
            </template>
            <Button
                v-if="quotation.status !== 'draft'"
                size="sm"
                variant="ghost"
                class="ml-auto"
                @click="copyLink"
            >
                <Copy /> Copy link
            </Button>
        </div>

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
                    title="Quotation"
                    :meta="meta"
                    :customer="quotation.customer"
                    :lines="quotation.lines ?? []"
                    :taxes="quotation.taxes ?? []"
                    :currency="quotation.currency"
                    :subtotal="quotation.subtotal"
                    :discount-percent="quotation.discount_percent"
                    :discount-amount="quotation.discount_amount"
                    :further-tax-rate="quotation.further_tax_rate"
                    :further-tax-amount="quotation.further_tax_amount"
                    :total="quotation.total"
                    :introduction="quotation.introduction"
                    :terms="quotation.terms"
                    :heading="quotation.title"
                >
                    <div
                        v-if="quotation.notes"
                        class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
                    >
                        <p class="text-xs font-medium uppercase">
                            Internal notes
                        </p>
                        <p class="whitespace-pre-line">{{ quotation.notes }}</p>
                    </div>
                </DocumentView>
            </TabsContent>
            <TabsContent value="timeline">
                <Card>
                    <CardContent class="pt-6">
                        <Timeline
                            :items="timeline"
                            notable-type="quotation"
                            :notable-id="quotation.id"
                            :can-write="canEdit('quotations')"
                        />
                    </CardContent>
                </Card>
            </TabsContent>
            <TabsContent value="files">
                <Card>
                    <CardContent class="pt-6">
                        <AttachmentsPanel
                            :attachments="attachments"
                            attachable-type="quotation"
                            :attachable-id="quotation.id"
                            :can-write="canEdit('quotations')"
                        />
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <SendDocumentDialog
            v-model:open="sending"
            :title="`Send quotation ${quotation.number}`"
            :draft="emailDraft"
            :url="send([slug, quotation.id]).url"
            :attachment-name="`quotation-${quotation.number}.pdf`"
        />

        <ConfirmDialog
            v-model:open="declining"
            title="Customer declined the quotation"
            confirm-label="Mark as declined"
            destructive
            @confirm="submitDecline"
        >
            <Textarea
                v-model="declineReason"
                rows="3"
                placeholder="Reason (optional)"
            />
        </ConfirmDialog>

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete quotation?"
            :description="`Quotation ${quotation.number} will be deleted.`"
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, quotation.id]).url)"
        />
    </div>
</template>
