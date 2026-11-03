<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    CalendarPlus,
    Mail,
    Pencil,
    Phone,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import CustomFieldValues from '@/components/app/CustomFieldValues.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import RelatedDocuments from '@/components/documents/RelatedDocuments.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate } from '@/lib/format';
import { index as calendarIndex } from '@/routes/calendar';
import { show as showCompany } from '@/routes/companies';
import { archive, destroy, edit, index, show } from '@/routes/contacts';
import { create as createInvoice } from '@/routes/invoices';
import { create as createQuotation } from '@/routes/quotations';
import { create as createTicket } from '@/routes/tickets';
import { create as createWorkOrder } from '@/routes/work-orders';
import type {
    Attachment,
    Contact,
    CustomFieldDefinition,
    RelatedDocumentGroups,
    RelatedDocumentKey,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    contact: Contact;
    timeline: TimelineItem[];
    attachments: Attachment[];
    customFields: CustomFieldDefinition[];
    related: RelatedDocumentGroups;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; contact: Contact }) => ({
        breadcrumbs: [
            { title: 'Contacts', href: index(props.currentTeam.slug) },
            {
                title: props.contact.full_name,
                href: show([props.currentTeam.slug, props.contact.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const deleting = ref(false);

const createLinks = computed(() => {
    const query = { contact: props.contact.id };
    const links: Partial<Record<RelatedDocumentKey, string>> = {};

    if (canEdit('quotations')) {
        links.quotations = createQuotation(slug.value, { query }).url;
    }

    if (canEdit('invoicing')) {
        links.invoices = createInvoice(slug.value, { query }).url;
    }

    if (canEdit('tickets')) {
        links.tickets = createTicket(slug.value, { query }).url;
    }

    if (canEdit('work_orders')) {
        links.work_orders = createWorkOrder(slug.value, { query }).url;
    }

    return links;
});

const toggleArchive = () => {
    router.patch(
        archive([slug.value, props.contact.id]).url,
        {},
        {
            preserveScroll: true,
        },
    );
};

const destroyContact = () => {
    router.delete(destroy([slug.value, props.contact.id]).url);
};
</script>

<template>
    <Head :title="contact.full_name" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="
                [contact.salutation, contact.full_name]
                    .filter(Boolean)
                    .join(' ')
            "
        >
            <template #badges>
                <Pill v-if="contact.archived_at" color="gray">Archived</Pill>
                <Pill
                    v-for="tag in contact.tags ?? []"
                    :key="tag.id"
                    :color="tag.color"
                >
                    {{ tag.name }}
                </Pill>
            </template>
            <template #meta>
                <div
                    class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
                >
                    <template
                        v-for="company in contact.companies ?? []"
                        :key="company.id"
                    >
                        <span>
                            {{
                                company.position
                                    ? `${company.position} at `
                                    : ''
                            }}
                            <Link
                                :href="showCompany([slug, company.id]).url"
                                class="font-medium text-foreground hover:underline"
                            >
                                {{ company.name }}
                            </Link>
                        </span>
                    </template>
                    <a
                        v-if="contact.email"
                        :href="`mailto:${contact.email}`"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <Mail class="size-3.5" /> {{ contact.email }}
                    </a>
                    <a
                        v-if="contact.mobile || contact.phone"
                        :href="`tel:${contact.mobile ?? contact.phone}`"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <Phone class="size-3.5" />
                        {{ contact.mobile ?? contact.phone }}
                    </a>
                </div>
            </template>
            <template v-if="canEdit('crm')" #actions>
                <Button
                    v-if="canEdit('calendar')"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link
                        :href="
                            calendarIndex(slug, {
                                query: { plan: 1, contact: contact.id },
                            }).url
                        "
                        ><CalendarPlus /> Plan</Link
                    >
                </Button>
                <Button variant="outline" size="sm" @click="toggleArchive">
                    <ArchiveRestore v-if="contact.archived_at" />
                    <Archive v-else />
                    {{ contact.archived_at ? 'Restore' : 'Archive' }}
                </Button>
                <Button variant="outline" size="sm" @click="deleting = true">
                    <Trash2 /> Delete
                </Button>
                <Button size="sm" as-child>
                    <Link :href="edit([slug, contact.id]).url"
                        ><Pencil /> Edit</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl>
                        <DetailItem label="Mobile" :value="contact.mobile" />
                        <DetailItem label="Phone" :value="contact.phone" />
                        <DetailItem label="CNIC" :value="contact.cnic" />
                        <DetailItem
                            label="Date of birth"
                            :value="
                                contact.birthdate
                                    ? formatDate(contact.birthdate)
                                    : null
                            "
                        />
                        <DetailItem
                            label="Account manager"
                            :value="contact.responsible_user?.name"
                        />
                        <DetailItem
                            label="Marketing"
                            :value="
                                contact.marketing_consent
                                    ? 'Agreed'
                                    : 'Not agreed'
                            "
                        />
                    </dl>
                    <template v-if="contact.addresses?.length">
                        <Separator class="my-3" />
                        <dl>
                            <DetailItem
                                v-for="address in contact.addresses"
                                :key="address.id"
                                :label="address.type_label ?? address.type"
                                :value="address.single_line"
                            />
                        </dl>
                    </template>
                    <template v-if="customFields.length > 0">
                        <Separator class="my-3" />
                        <CustomFieldValues
                            :fields="customFields"
                            :values="contact.custom_fields"
                        />
                    </template>
                    <template v-if="contact.remarks">
                        <Separator class="my-3" />
                        <p
                            class="text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ contact.remarks }}
                        </p>
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
                                    :create-links="createLinks"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="contact"
                                    :notable-id="contact.id"
                                    :can-write="canEdit('crm')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="files">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="contact"
                                    :attachable-id="contact.id"
                                    :can-write="canEdit('crm')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete contact?"
            :description="`${contact.full_name} will be removed from your lists.`"
            confirm-label="Delete"
            destructive
            @confirm="destroyContact"
        />
    </div>
</template>
