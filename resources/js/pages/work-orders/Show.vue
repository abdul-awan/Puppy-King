<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    FileDown,
    Link2,
    Mail,
    Package,
    Pencil,
    PenLine,
    ReceiptText,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import SendDocumentDialog from '@/components/documents/SendDocumentDialog.vue';
import InputError from '@/components/InputError.vue';
import MaterialDialog from '@/components/projects/MaterialDialog.vue';
import TimeEntryDialog from '@/components/time/TimeEntryDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SignWorkOrderDialog from '@/components/work-orders/SignWorkOrderDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import {
    formatDate,
    formatDateTime,
    formatDuration,
    formatMoney,
} from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { show as showContact } from '@/routes/contacts';
import { show as showInvoice } from '@/routes/invoices';
import { show as showProject } from '@/routes/projects';
import { show as showTicket } from '@/routes/tickets';
import { destroy as destroyTime } from '@/routes/time';
import {
    complete,
    destroy,
    edit,
    index,
    invoice,
    pdf,
    reopen,
    send,
    show,
} from '@/routes/work-orders';
import { destroy as destroyMaterial } from '@/routes/work-orders/materials';
import type {
    Attachment,
    EmailDraft,
    IdName,
    ProjectMaterial,
    TaxRateOption,
    Team,
    TimeEntry,
    TimelineItem,
    WorkOrder,
    WorkOrderTotals,
    WorkTypeOption,
} from '@/types';

const props = defineProps<{
    workOrder: WorkOrder;
    signature: string | null;
    timeEntries: TimeEntry[];
    materials: ProjectMaterial[];
    totals: WorkOrderTotals;
    currency: string;
    attachments: Attachment[];
    timeline: TimelineItem[];
    workTypes: WorkTypeOption[];
    taxRates: TaxRateOption[];
    users: IdName[];
    isManager: boolean;
    emailDraft: EmailDraft;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; workOrder: WorkOrder }) => ({
        breadcrumbs: [
            { title: 'Work orders', href: index(props.currentTeam.slug) },
            {
                title: props.workOrder.number ?? 'Work order',
                href: show([props.currentTeam.slug, props.workOrder.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();

const canWrite = computed(() => canEdit('work_orders'));
const isOpen = computed(() => props.workOrder.status === 'open');
const isInvoiced = computed(() => props.workOrder.invoice_id !== null);
const canSign = computed(
    () => canWrite.value && !props.workOrder.is_signed && !isInvoiced.value,
);
const canLogTime = computed(() => isOpen.value && canEdit('time_tracking'));
const currentUserId = computed(() => page.props.auth.user?.id ?? null);

const errors = computed(() => page.props.errors as Record<string, string>);
const actionError = computed(
    () =>
        errors.value.work_order ??
        errors.value.work_orders ??
        errors.value.entry ??
        errors.value.material,
);

// Dialogs.
const signing = ref(false);
const sending = ref(false);
const deleting = ref(false);
const reopening = ref(false);
const loggingTime = ref(false);
const editingEntry = ref<TimeEntry | null>(null);
const addingMaterial = ref(false);
const editingMaterial = ref<ProjectMaterial | null>(null);

const openTimeDialog = (entry: TimeEntry | null) => {
    editingEntry.value = entry;
    loggingTime.value = true;
};

const openMaterialDialog = (material: ProjectMaterial | null) => {
    editingMaterial.value = material;
    addingMaterial.value = true;
};

const canChangeEntry = (entry: TimeEntry) =>
    canLogTime.value &&
    entry.invoice_id === null &&
    (props.isManager || entry.user_id === currentUserId.value);

const removeEntry = (entry: TimeEntry) => {
    router.delete(destroyTime([slug.value, entry.id]).url, {
        preserveScroll: true,
    });
};

const removeMaterial = (material: ProjectMaterial) => {
    router.delete(
        destroyMaterial([slug.value, props.workOrder.id, material.id]).url,
        { preserveScroll: true },
    );
};

const markComplete = () => {
    router.post(
        complete([slug.value, props.workOrder.id]).url,
        {},
        { preserveScroll: true },
    );
};

const reopenWorkOrder = () => {
    router.post(
        reopen([slug.value, props.workOrder.id]).url,
        {},
        { preserveScroll: true },
    );
};

const createInvoice = () => {
    router.post(invoice(slug.value).url, {
        work_orders: [props.workOrder.id],
    });
};

const copyLink = async () => {
    await navigator.clipboard.writeText(props.workOrder.public_url);
    toast.success(
        'Link copied. The customer can see, download and sign the work order there.',
    );
};

const money = (value: string | number) => formatMoney(value, props.currency);
</script>

<template>
    <Head :title="`${workOrder.number} ${workOrder.title}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="workOrder.title">
            <template #badges>
                <Pill :color="workOrder.status_color">{{
                    workOrder.status_label
                }}</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    {{ workOrder.number }} · {{ workOrder.customer_label }} ·
                    {{ formatDate(workOrder.date) }}
                    <template v-if="workOrder.assignee">
                        · {{ workOrder.assignee.name }}</template
                    >
                </p>
            </template>
            <template #actions>
                <Button
                    v-if="isOpen && canWrite"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="edit([slug, workOrder.id]).url"
                        ><Pencil /> Edit</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="pdf([slug, workOrder.id]).url" target="_blank"
                        ><FileDown /> PDF</a
                    >
                </Button>
                <Button variant="outline" size="sm" @click="copyLink">
                    <Link2 /> Customer link
                </Button>
                <Button
                    v-if="canWrite"
                    variant="outline"
                    size="sm"
                    @click="sending = true"
                >
                    <Mail /> Send
                </Button>
                <Button
                    v-if="
                        canWrite &&
                        !isInvoiced &&
                        !isOpen &&
                        (!workOrder.is_signed || isManager)
                    "
                    variant="outline"
                    size="sm"
                    @click="reopening = true"
                >
                    <RotateCcw /> Reopen
                </Button>
                <Button
                    v-if="canWrite && !isInvoiced"
                    variant="outline"
                    size="sm"
                    aria-label="Delete"
                    @click="deleting = true"
                >
                    <Trash2 />
                </Button>
                <Button
                    v-if="isOpen && canWrite && !workOrder.is_signed"
                    variant="outline"
                    size="sm"
                    @click="markComplete"
                >
                    <CheckCircle2 /> Complete
                </Button>
                <Button v-if="canSign" size="sm" @click="signing = true">
                    <PenLine /> Customer signs
                </Button>
                <Button
                    v-if="!isOpen && !isInvoiced && canEdit('invoicing')"
                    size="sm"
                    @click="createInvoice"
                >
                    <ReceiptText /> Invoice
                </Button>
            </template>
        </PageHeader>

        <InputError :message="actionError" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Work carried out</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="workOrder.description"
                            class="text-sm whitespace-pre-line"
                        >
                            {{ workOrder.description }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Nothing written yet.
                            <Link
                                v-if="isOpen && canWrite"
                                :href="edit([slug, workOrder.id]).url"
                                class="text-foreground underline"
                                >Describe the work</Link
                            >
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-2 space-y-0"
                    >
                        <CardTitle
                            >Hours ({{
                                formatDuration(totals.minutes)
                            }})</CardTitle
                        >
                        <Button
                            v-if="canLogTime"
                            size="sm"
                            variant="outline"
                            @click="openTimeDialog(null)"
                        >
                            <Clock /> Add hours
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="!timeEntries.length"
                            class="text-sm text-muted-foreground"
                        >
                            No hours yet.
                        </p>
                        <ul v-else class="divide-y text-sm">
                            <li
                                v-for="entry in timeEntries"
                                :key="entry.id"
                                class="flex items-start justify-between gap-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p>
                                        <span class="font-medium">{{
                                            entry.user?.name ?? '—'
                                        }}</span>
                                        · {{ formatDate(entry.date) }}
                                        <template v-if="entry.work_type">
                                            ·
                                            {{ entry.work_type.name }}</template
                                        >
                                    </p>
                                    <p
                                        v-if="entry.description"
                                        class="text-muted-foreground"
                                    >
                                        {{ entry.description }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        <template v-if="!entry.is_billable"
                                            >Not billable</template
                                        >
                                        <template v-else
                                            >{{ money(entry.hourly_rate) }} /
                                            hour</template
                                        >
                                        <template v-if="entry.invoice">
                                            · on invoice
                                            {{
                                                entry.invoice.number ??
                                                '(draft)'
                                            }}</template
                                        >
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="tabular-nums">{{
                                        formatDuration(entry.duration_minutes)
                                    }}</span>
                                    <template v-if="canChangeEntry(entry)">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            aria-label="Edit hours"
                                            @click="openTimeDialog(entry)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            aria-label="Remove hours"
                                            @click="removeEntry(entry)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </template>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-2 space-y-0"
                    >
                        <CardTitle>Materials used</CardTitle>
                        <Button
                            v-if="isOpen && canWrite"
                            size="sm"
                            variant="outline"
                            @click="openMaterialDialog(null)"
                        >
                            <Package /> Add material
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="!materials.length"
                            class="text-sm text-muted-foreground"
                        >
                            No materials used.
                        </p>
                        <ul v-else class="divide-y text-sm">
                            <li
                                v-for="material in materials"
                                :key="material.id"
                                class="flex items-start justify-between gap-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="font-medium">
                                        {{ material.description }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ Number(material.quantity) }}
                                        {{ material.unit ?? '' }} ×
                                        {{ money(material.unit_price) }}
                                        <template v-if="!material.is_billable">
                                            · no charge</template
                                        >
                                        <template v-if="material.invoice">
                                            · on invoice
                                            {{
                                                material.invoice.number ??
                                                '(draft)'
                                            }}</template
                                        >
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="tabular-nums">{{
                                        money(material.value)
                                    }}</span>
                                    <template
                                        v-if="
                                            isOpen &&
                                            canWrite &&
                                            material.invoice_id === null
                                        "
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            aria-label="Edit material"
                                            @click="
                                                openMaterialDialog(material)
                                            "
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            aria-label="Remove material"
                                            @click="removeMaterial(material)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </template>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Signature of the customer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="workOrder.is_signed" class="space-y-2">
                            <img
                                v-if="signature"
                                :src="signature"
                                alt="Signature of the customer"
                                class="h-24 rounded-md border bg-white p-2"
                            />
                            <p class="text-sm">
                                Signed by
                                <span class="font-medium">{{
                                    workOrder.signed_by_name
                                }}</span>
                                on {{ formatDateTime(workOrder.signed_at) }}
                            </p>
                            <p
                                v-if="workOrder.customer_remarks"
                                class="text-sm whitespace-pre-line text-muted-foreground"
                            >
                                “{{ workOrder.customer_remarks }}”
                            </p>
                        </div>
                        <div v-else class="space-y-3 text-sm">
                            <p class="text-muted-foreground">
                                Not signed yet. Let the customer sign on your
                                device, or send the work order: the customer can
                                sign online through the link.
                            </p>
                            <Button
                                v-if="canSign"
                                size="sm"
                                @click="signing = true"
                            >
                                <PenLine /> Customer signs
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardContent class="pt-6">
                        <dl>
                            <DetailItem label="Customer">
                                <Link
                                    v-if="workOrder.company"
                                    :href="
                                        showCompany([
                                            slug,
                                            workOrder.company.id,
                                        ]).url
                                    "
                                    class="hover:underline"
                                    >{{ workOrder.company.name }}</Link
                                >
                                <template
                                    v-if="
                                        workOrder.company && workOrder.contact
                                    "
                                >
                                    ·
                                </template>
                                <Link
                                    v-if="workOrder.contact"
                                    :href="
                                        showContact([
                                            slug,
                                            workOrder.contact.id,
                                        ]).url
                                    "
                                    class="hover:underline"
                                    >{{ workOrder.contact.name }}</Link
                                >
                                <template
                                    v-if="
                                        !workOrder.company && !workOrder.contact
                                    "
                                    >—</template
                                >
                            </DetailItem>
                            <DetailItem label="Location">
                                <span class="whitespace-pre-line">{{
                                    workOrder.location ?? '—'
                                }}</span>
                            </DetailItem>
                            <DetailItem
                                label="Done by"
                                :value="workOrder.assignee?.name ?? '—'"
                            />
                            <DetailItem
                                v-if="workOrder.project"
                                label="Project"
                            >
                                <Link
                                    :href="
                                        showProject([
                                            slug,
                                            workOrder.project.id,
                                        ]).url
                                    "
                                    class="hover:underline"
                                    >{{ workOrder.project.number }}
                                    {{ workOrder.project.title }}</Link
                                >
                            </DetailItem>
                            <DetailItem v-if="workOrder.ticket" label="Ticket">
                                <Link
                                    :href="
                                        showTicket([slug, workOrder.ticket.id])
                                            .url
                                    "
                                    class="hover:underline"
                                    >{{ workOrder.ticket.number }}
                                    {{ workOrder.ticket.subject }}</Link
                                >
                            </DetailItem>
                            <DetailItem
                                v-if="workOrder.invoice"
                                label="Invoice"
                            >
                                <Link
                                    :href="
                                        showInvoice([
                                            slug,
                                            workOrder.invoice.id,
                                        ]).url
                                    "
                                    class="hover:underline"
                                    >{{
                                        workOrder.invoice.number ??
                                        'Draft invoice'
                                    }}</Link
                                >
                            </DetailItem>
                            <DetailItem
                                v-if="workOrder.completed_at"
                                label="Completed"
                                :value="formatDateTime(workOrder.completed_at)"
                            />
                            <DetailItem
                                label="Prices on the work order"
                                :value="
                                    workOrder.show_prices ? 'Shown' : 'Hidden'
                                "
                            />
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Value</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">
                                    Billable hours ({{
                                        formatDuration(totals.billable_minutes)
                                    }})
                                </dt>
                                <dd class="tabular-nums">
                                    {{ money(totals.time_value) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-muted-foreground">Materials</dt>
                                <dd class="tabular-nums">
                                    {{ money(totals.materials_value) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-2 border-t pt-1 font-medium"
                            >
                                <dt>Total excl. tax</dt>
                                <dd class="tabular-nums">
                                    {{ money(totals.total_value) }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="workOrder.internal_notes">
                    <CardHeader>
                        <CardTitle>Internal notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-line">
                            {{ workOrder.internal_notes }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Photos &amp; files</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AttachmentsPanel
                            :attachments="attachments"
                            attachable-type="work_order"
                            :attachable-id="workOrder.id"
                            :can-write="canWrite"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Timeline
                            :items="timeline"
                            notable-type="work_order"
                            :notable-id="workOrder.id"
                            :can-write="canWrite"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>

        <TimeEntryDialog
            v-model:open="loggingTime"
            :entry="editingEntry"
            :work-order-id="workOrder.id"
            :default-date="workOrder.date"
            :work-types="workTypes"
            :users="users"
        />

        <MaterialDialog
            v-model:open="addingMaterial"
            :material="editingMaterial"
            :work-order-id="workOrder.id"
            :tax-rates="taxRates"
        />

        <SignWorkOrderDialog v-model:open="signing" :work-order="workOrder" />

        <SendDocumentDialog
            v-model:open="sending"
            :title="`Send work order ${workOrder.number}`"
            :draft="emailDraft"
            :url="send([slug, workOrder.id]).url"
            :attachment-name="`work order ${workOrder.number} (PDF)`"
        />

        <ConfirmDialog
            v-model:open="reopening"
            title="Reopen this work order?"
            :description="
                workOrder.is_signed
                    ? 'The signature of the customer is removed: the customer signed the current version. Let the customer sign again afterwards.'
                    : 'You can change the work order again afterwards.'
            "
            confirm-label="Reopen"
            @confirm="reopenWorkOrder"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete this work order?"
            description="Its hours and materials are deleted too."
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, workOrder.id]).url)"
        />
    </div>
</template>
