<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarPlus,
    Check,
    FileText,
    Pencil,
    ReceiptText,
    RotateCcw,
    ThumbsDown,
    ThumbsUp,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import CustomFieldValues from '@/components/app/CustomFieldValues.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import Field from '@/components/app/Field.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Timeline from '@/components/app/Timeline.vue';
import RelatedDocuments from '@/components/documents/RelatedDocuments.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index as calendarIndex } from '@/routes/calendar';
import { show as showCompany } from '@/routes/companies';
import { show as showContact } from '@/routes/contacts';
import {
    destroy,
    edit,
    index,
    lost,
    move,
    reopen,
    show,
    won,
} from '@/routes/deals';
import { create as createInvoice } from '@/routes/invoices';
import { create as createProject } from '@/routes/projects';
import { create as createQuotation } from '@/routes/quotations';
import type {
    Attachment,
    CustomFieldDefinition,
    Deal,
    IdName,
    PhaseOption,
    RelatedDocumentGroups,
    RelatedDocumentKey,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    deal: Deal;
    phases: PhaseOption[];
    lostReasons: IdName[];
    timeline: TimelineItem[];
    attachments: Attachment[];
    customFields: CustomFieldDefinition[];
    related: RelatedDocumentGroups;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; deal: Deal }) => ({
        breadcrumbs: [
            { title: 'Deals', href: index(props.currentTeam.slug) },
            {
                title: props.deal.title,
                href: show([props.currentTeam.slug, props.deal.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const newQuotationUrl = computed(
    () => createQuotation(slug.value, { query: { deal: props.deal.id } }).url,
);
const newInvoiceUrl = computed(
    () => createInvoice(slug.value, { query: { deal: props.deal.id } }).url,
);

const documentLinks = computed(() => {
    const links: Partial<Record<RelatedDocumentKey, string>> = {};

    if (canEdit('quotations')) {
        links.quotations = newQuotationUrl.value;
    }

    if (canEdit('invoicing')) {
        links.invoices = newInvoiceUrl.value;
    }

    if (canEdit('projects')) {
        links.projects = createProject(slug.value, {
            query: { deal: props.deal.id },
        }).url;
    }

    return links;
});

const deleting = ref(false);
const losing = ref(false);
const lostForm = useForm({
    lost_reason_id: null as number | null,
    lost_note: '',
});

const phaseIndex = () =>
    props.phases.findIndex((phase) => phase.id === props.deal.phase_id);

const moveTo = (phase: PhaseOption) => {
    if (
        !canEdit('deals') ||
        props.deal.status !== 'open' ||
        phase.id === props.deal.phase_id
    ) {
        return;
    }

    router.patch(
        move([slug.value, props.deal.id]).url,
        { phase_id: phase.id },
        { preserveScroll: true },
    );
};

const markWon = () => {
    router.post(
        won([slug.value, props.deal.id]).url,
        {},
        { preserveScroll: true },
    );
};

const markLost = () => {
    lostForm.post(lost([slug.value, props.deal.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            losing.value = false;
        },
    });
};

const reopenDeal = () => {
    router.post(
        reopen([slug.value, props.deal.id]).url,
        {},
        { preserveScroll: true },
    );
};

const destroyDeal = () => {
    router.delete(destroy([slug.value, props.deal.id]).url);
};
</script>

<template>
    <Head :title="deal.title" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="deal.title">
            <template #badges>
                <Pill :color="deal.status_color">{{ deal.status_label }}</Pill>
                <Pill
                    v-for="tag in deal.tags ?? []"
                    :key="tag.id"
                    :color="tag.color"
                    >{{ tag.name }}</Pill
                >
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(deal.value, deal.currency)
                    }}</span>
                    <template v-if="deal.company">
                        ·
                        <Link
                            :href="showCompany([slug, deal.company.id]).url"
                            class="hover:underline"
                        >
                            {{ deal.company.name }}
                        </Link>
                    </template>
                    <template v-if="deal.contact">
                        ·
                        <Link
                            :href="showContact([slug, deal.contact.id]).url"
                            class="hover:underline"
                        >
                            {{ deal.contact.name }}
                        </Link>
                    </template>
                </p>
            </template>
            <template v-if="canEdit('deals')" #actions>
                <template v-if="deal.status === 'open'">
                    <Button
                        size="sm"
                        class="bg-green-600 text-white hover:bg-green-700"
                        @click="markWon"
                    >
                        <ThumbsUp /> Won
                    </Button>
                    <Button size="sm" variant="outline" @click="losing = true">
                        <ThumbsDown /> Lost
                    </Button>
                </template>
                <Button v-else size="sm" variant="outline" @click="reopenDeal">
                    <RotateCcw /> Reopen
                </Button>
                <Button
                    v-if="canEdit('calendar')"
                    size="sm"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            calendarIndex(slug, {
                                query: { plan: 1, deal: deal.id },
                            }).url
                        "
                        ><CalendarPlus /> Plan</Link
                    >
                </Button>
                <Button size="sm" variant="outline" @click="deleting = true"
                    ><Trash2
                /></Button>
                <Button
                    v-if="canEdit('quotations')"
                    size="sm"
                    variant="outline"
                    as-child
                >
                    <Link :href="newQuotationUrl"><FileText /> Quotation</Link>
                </Button>
                <Button
                    v-if="canEdit('invoicing')"
                    size="sm"
                    variant="outline"
                    as-child
                >
                    <Link :href="newInvoiceUrl"><ReceiptText /> Invoice</Link>
                </Button>
                <Button size="sm" as-child>
                    <Link :href="edit([slug, deal.id]).url"
                        ><Pencil /> Edit</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <ol
            v-if="deal.status === 'open'"
            class="flex overflow-x-auto rounded-lg border"
        >
            <li
                v-for="(phase, position) in phases"
                :key="phase.id"
                class="min-w-32 flex-1 border-r last:border-r-0"
            >
                <button
                    type="button"
                    class="flex h-full w-full items-center justify-center gap-1.5 px-3 py-2.5 text-sm transition"
                    :class="[
                        position < phaseIndex()
                            ? 'bg-primary/10 text-primary'
                            : '',
                        position === phaseIndex()
                            ? 'bg-primary font-medium text-primary-foreground'
                            : '',
                        canEdit('deals')
                            ? 'hover:bg-primary/20'
                            : 'cursor-default',
                    ]"
                    @click="moveTo(phase)"
                >
                    <Check v-if="position < phaseIndex()" class="size-3.5" />
                    {{ phase.name }}
                </button>
            </li>
        </ol>
        <div
            v-else
            class="rounded-lg border p-4 text-sm"
            :class="
                deal.status === 'won'
                    ? 'border-green-300 bg-green-50 dark:bg-green-950/30'
                    : 'border-red-300 bg-red-50 dark:bg-red-950/30'
            "
        >
            <p class="font-medium">
                {{ deal.status === 'won' ? 'Won' : 'Lost' }} on
                {{ formatDateTime(deal.closed_at) }}
                <template v-if="deal.lost_reason">
                    — {{ deal.lost_reason.name }}</template
                >
            </p>
            <p
                v-if="deal.lost_note"
                class="mt-1 whitespace-pre-line text-muted-foreground"
            >
                {{ deal.lost_note }}
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl>
                        <DetailItem
                            label="Pipeline"
                            :value="deal.pipeline?.name"
                        />
                        <DetailItem label="Phase" :value="deal.phase?.name" />
                        <DetailItem
                            label="Probability"
                            :value="`${deal.effective_probability ?? 0}%`"
                        />
                        <DetailItem
                            label="Weighted value"
                            :value="
                                formatMoney(
                                    deal.weighted_value ?? 0,
                                    deal.currency,
                                )
                            "
                        />
                        <DetailItem
                            label="Expected close"
                            :value="
                                deal.expected_close_date
                                    ? formatDate(deal.expected_close_date)
                                    : null
                            "
                        />
                        <DetailItem
                            label="Responsible"
                            :value="deal.responsible_user?.name"
                        />
                        <DetailItem label="Source" :value="deal.source?.name" />
                        <DetailItem
                            label="Created"
                            :value="formatDate(deal.created_at)"
                        />
                    </dl>
                    <template v-if="deal.description">
                        <Separator class="my-3" />
                        <p
                            class="text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ deal.description }}
                        </p>
                    </template>
                    <template v-if="customFields.length > 0">
                        <Separator class="my-3" />
                        <CustomFieldValues
                            :fields="customFields"
                            :values="deal.custom_fields"
                        />
                    </template>
                </CardContent>
            </Card>

            <div class="lg:col-span-2">
                <Tabs default-value="timeline">
                    <TabsList>
                        <TabsTrigger value="timeline">Timeline</TabsTrigger>
                        <TabsTrigger value="documents">Documents</TabsTrigger>
                        <TabsTrigger value="files"
                            >Files ({{ attachments.length }})</TabsTrigger
                        >
                    </TabsList>
                    <TabsContent value="documents">
                        <Card>
                            <CardContent class="pt-6">
                                <RelatedDocuments
                                    :related="related"
                                    :create-links="documentLinks"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="deal"
                                    :notable-id="deal.id"
                                    :can-write="canEdit('deals')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="files">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="deal"
                                    :attachable-id="deal.id"
                                    :can-write="canEdit('deals')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <Dialog v-model:open="losing">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Why was this deal lost?</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="markLost">
                    <Field
                        label="Reason"
                        :error="lostForm.errors.lost_reason_id"
                    >
                        <SelectInput
                            v-model="lostForm.lost_reason_id"
                            :options="idOptions(lostReasons)"
                            placeholder="—"
                        />
                    </Field>
                    <Field label="Note" :error="lostForm.errors.lost_note">
                        <Textarea v-model="lostForm.lost_note" rows="3" />
                    </Field>
                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="losing = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="lostForm.processing"
                            >Mark as lost</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete deal?"
            :description="`${deal.title} will be removed.`"
            confirm-label="Delete"
            destructive
            @confirm="destroyDeal"
        />
    </div>
</template>
