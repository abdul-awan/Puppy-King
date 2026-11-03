<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ClipboardPen, Clock, Link2, Lock, Mail, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Timeline from '@/components/app/Timeline.vue';
import InputError from '@/components/InputError.vue';
import TimeEntryDialog from '@/components/time/TimeEntryDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatDuration } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { show as showCompany } from '@/routes/companies';
import { show as showContact } from '@/routes/contacts';
import { show as showProject } from '@/routes/projects';
import { destroy, index, quickUpdate, reply, show } from '@/routes/tickets';
import { create as createWorkOrder } from '@/routes/work-orders';
import type {
    Attachment,
    IdName,
    Option,
    Team,
    Ticket,
    TimeEntry,
    TimelineItem,
    WorkTypeOption,
} from '@/types';

const props = defineProps<{
    ticket: Ticket;
    timeEntries: TimeEntry[];
    loggedMinutes: number;
    attachments: Attachment[];
    timeline: TimelineItem[];
    workTypes: WorkTypeOption[];
    users: IdName[];
    statuses: Option[];
    priorities: Option[];
    channels: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; ticket: Ticket }) => ({
        breadcrumbs: [
            { title: 'Tickets', href: index(props.currentTeam.slug) },
            {
                title: props.ticket.number ?? 'Ticket',
                href: show([props.currentTeam.slug, props.ticket.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const canWrite = computed(() => canEdit('tickets'));

const logging = ref(false);
const deleting = ref(false);

// Reply box.
const mode = ref<'reply' | 'note'>('reply');
const form = useForm({
    body: '',
    email: true,
    status: 'waiting' as string | null,
});

watch(mode, (value) => {
    form.status = value === 'reply' ? 'waiting' : null;
});

const send = () => {
    form.transform((data) => ({
        body: data.body,
        internal: mode.value === 'note',
        email:
            mode.value === 'reply' &&
            data.email &&
            props.ticket.reply_to !== null,
        status: data.status,
    })).post(reply([slug.value, props.ticket.id]).url, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};

const change = (
    field: 'status' | 'priority' | 'assignee_id',
    value: string | number | null | undefined,
) => {
    const payload: Record<string, string | number | null> = {
        [field]: value ?? null,
    };

    router.patch(quickUpdate([slug.value, props.ticket.id]).url, payload, {
        preserveScroll: true,
    });
};

const copyLink = async () => {
    await navigator.clipboard.writeText(props.ticket.public_url);
    toast.success(
        'Link copied. The customer can follow the ticket and reply there.',
    );
};

const statusAfter = computed(() => [
    ...(mode.value === 'note' ? [{ value: '', label: 'Keep the status' }] : []),
    ...props.statuses,
]);
</script>

<template>
    <Head :title="`${ticket.number} ${ticket.subject}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="ticket.subject">
            <template #badges>
                <Pill :color="ticket.status_color">{{
                    ticket.status_label
                }}</Pill>
                <Pill
                    v-if="ticket.priority !== 'normal'"
                    :color="ticket.priority_color"
                >
                    {{ ticket.priority_label }}
                </Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    {{ ticket.number }} · {{ ticket.customer_label }} ·
                    {{ ticket.channel_label }} · opened
                    {{ formatDateTime(ticket.created_at) }}
                </p>
            </template>
            <template #actions>
                <Button variant="outline" size="sm" @click="copyLink">
                    <Link2 /> Customer link
                </Button>
                <Button
                    v-if="canEdit('time_tracking')"
                    variant="outline"
                    size="sm"
                    @click="logging = true"
                >
                    <Clock /> Log time
                </Button>
                <Button
                    v-if="canEdit('work_orders')"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="
                            createWorkOrder(slug, {
                                query: { ticket: ticket.id },
                            }).url
                        "
                        ><ClipboardPen /> Work order</Link
                    >
                </Button>
                <Button
                    v-if="canWrite"
                    variant="outline"
                    size="sm"
                    @click="deleting = true"
                >
                    <Trash2 />
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <p
                    v-if="!ticket.messages?.length"
                    class="rounded-lg border p-6 text-center text-sm text-muted-foreground"
                >
                    No messages yet.
                </p>
                <div
                    v-for="message in ticket.messages"
                    :key="message.id"
                    class="rounded-lg border p-4"
                    :class="{
                        'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40':
                            message.is_internal,
                        'bg-muted/40': message.from_customer,
                        'ml-6 bg-primary/5':
                            !message.from_customer && !message.is_internal,
                    }"
                >
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-medium text-foreground">{{
                            message.author
                        }}</span>
                        <span class="text-muted-foreground">{{
                            formatDateTime(message.created_at)
                        }}</span>
                        <span
                            v-if="message.is_internal"
                            class="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300"
                        >
                            <Lock class="size-3" /> internal note
                        </span>
                        <span
                            v-else-if="message.emailed_at"
                            class="inline-flex items-center gap-1 text-muted-foreground"
                        >
                            <Mail class="size-3" /> e-mailed
                        </span>
                        <span
                            v-else-if="message.from_customer"
                            class="text-muted-foreground"
                        >
                            customer
                        </span>
                    </div>
                    <p class="text-sm whitespace-pre-line">
                        {{ message.body }}
                    </p>
                </div>

                <Card v-if="canWrite">
                    <CardContent class="space-y-3 pt-6">
                        <div class="inline-flex rounded-md border p-0.5">
                            <Button
                                size="sm"
                                :variant="
                                    mode === 'reply' ? 'secondary' : 'ghost'
                                "
                                @click="mode = 'reply'"
                            >
                                Reply to customer
                            </Button>
                            <Button
                                size="sm"
                                :variant="
                                    mode === 'note' ? 'secondary' : 'ghost'
                                "
                                @click="mode = 'note'"
                            >
                                <Lock /> Internal note
                            </Button>
                        </div>
                        <Textarea
                            v-model="form.body"
                            rows="5"
                            :placeholder="
                                mode === 'reply'
                                    ? 'Write your answer…'
                                    : 'Only your team sees this note'
                            "
                            :class="{
                                'bg-amber-50 dark:bg-amber-950/30':
                                    mode === 'note',
                            }"
                        />
                        <InputError :message="form.errors.body" />
                        <div class="flex flex-wrap items-center gap-3">
                            <label
                                v-if="mode === 'reply'"
                                class="flex items-center gap-2 text-sm"
                                :class="{ 'opacity-50': !ticket.reply_to }"
                            >
                                <Checkbox
                                    :model-value="
                                        form.email && !!ticket.reply_to
                                    "
                                    :disabled="!ticket.reply_to"
                                    @update:model-value="
                                        form.email = Boolean($event)
                                    "
                                />
                                {{
                                    ticket.reply_to
                                        ? `E-mail to ${ticket.reply_to}`
                                        : 'No e-mail address; the customer reads it through the link'
                                }}
                            </label>
                            <div class="ml-auto flex items-center gap-2">
                                <span class="text-sm text-muted-foreground"
                                    >then</span
                                >
                                <SelectInput
                                    :model-value="form.status ?? ''"
                                    :options="statusAfter"
                                    class="w-52"
                                    @update:model-value="
                                        form.status = $event
                                            ? String($event)
                                            : null
                                    "
                                />
                                <Button
                                    :disabled="
                                        form.processing ||
                                        form.body.trim() === ''
                                    "
                                    @click="send"
                                >
                                    {{ mode === 'reply' ? 'Send' : 'Add note' }}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardContent class="space-y-3 pt-6">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="mb-1 text-xs text-muted-foreground">
                                    Status
                                </p>
                                <SelectInput
                                    :model-value="ticket.status"
                                    :options="statuses"
                                    :disabled="!canWrite"
                                    @update:model-value="
                                        change('status', $event)
                                    "
                                />
                            </div>
                            <div>
                                <p class="mb-1 text-xs text-muted-foreground">
                                    Priority
                                </p>
                                <SelectInput
                                    :model-value="ticket.priority"
                                    :options="priorities"
                                    :disabled="!canWrite"
                                    @update:model-value="
                                        change('priority', $event)
                                    "
                                />
                            </div>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-muted-foreground">
                                Handled by
                            </p>
                            <SelectInput
                                :model-value="ticket.assignee_id"
                                :options="idOptions(users)"
                                placeholder="Nobody yet"
                                :disabled="!canWrite"
                                @update:model-value="
                                    change('assignee_id', $event)
                                "
                            />
                        </div>
                        <DetailItem label="Customer">
                            <Link
                                v-if="ticket.contact"
                                :href="
                                    showContact([slug, ticket.contact.id]).url
                                "
                                class="hover:underline"
                                >{{ ticket.contact.name }}</Link
                            >
                            <template v-if="ticket.contact && ticket.company">
                                ·
                            </template>
                            <Link
                                v-if="ticket.company"
                                :href="
                                    showCompany([slug, ticket.company.id]).url
                                "
                                class="hover:underline"
                                >{{ ticket.company.name }}</Link
                            >
                            <template
                                v-if="!ticket.contact && !ticket.company"
                                >{{ ticket.customer_label }}</template
                            >
                        </DetailItem>
                        <DetailItem label="Replies go to">{{
                            ticket.reply_to ?? '—'
                        }}</DetailItem>
                        <DetailItem v-if="ticket.project" label="Project">
                            <Link
                                :href="
                                    showProject([slug, ticket.project.id]).url
                                "
                                class="hover:underline"
                                >{{ ticket.project.title }}</Link
                            >
                        </DetailItem>
                        <DetailItem label="First answer">{{
                            ticket.first_response_at
                                ? formatDateTime(ticket.first_response_at)
                                : 'Not answered yet'
                        }}</DetailItem>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle
                            >Time ({{
                                formatDuration(loggedMinutes)
                            }})</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="!timeEntries.length"
                            class="text-sm text-muted-foreground"
                        >
                            No time logged on this ticket.
                        </p>
                        <ul v-else class="space-y-1 text-sm">
                            <li
                                v-for="entry in timeEntries"
                                :key="entry.id"
                                class="flex justify-between gap-2"
                            >
                                <span class="truncate">
                                    {{ formatDate(entry.date) }} ·
                                    {{ entry.user?.name }}
                                    <template v-if="entry.description">
                                        · {{ entry.description }}</template
                                    >
                                </span>
                                <span class="shrink-0">{{
                                    formatDuration(entry.duration_minutes)
                                }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Files</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <AttachmentsPanel
                            :attachments="attachments"
                            attachable-type="ticket"
                            :attachable-id="ticket.id"
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
                            notable-type="ticket"
                            :notable-id="ticket.id"
                            :can-write="false"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>

        <TimeEntryDialog
            v-model:open="logging"
            :ticket-id="ticket.id"
            :project-id="ticket.project_id"
            :project-label="ticket.project?.title"
            :work-types="workTypes"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete this ticket?"
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, ticket.id]).url)"
        />
    </div>
</template>
