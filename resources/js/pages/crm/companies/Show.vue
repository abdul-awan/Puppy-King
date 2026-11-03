<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    CalendarPlus,
    Link2,
    Mail,
    Pencil,
    Phone,
    Plus,
    Star,
    Trash2,
    Unlink,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import CustomFieldValues from '@/components/app/CustomFieldValues.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import RelatedDocuments from '@/components/documents/RelatedDocuments.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { index as calendarIndex } from '@/routes/calendar';
import { archive, destroy, edit, index, show } from '@/routes/companies';
import {
    destroy as unlinkContact,
    store as linkContact,
} from '@/routes/companies/contacts';
import {
    create as createContact,
    show as showContact,
} from '@/routes/contacts';
import {
    create as createExpense,
    index as expensesIndex,
} from '@/routes/expenses';
import {
    create as createInvoice,
    index as invoicesIndex,
} from '@/routes/invoices';
import {
    create as createProject,
    index as projectsIndex,
} from '@/routes/projects';
import { create as createTicket } from '@/routes/tickets';
import {
    create as createWorkOrder,
    index as workOrdersIndex,
} from '@/routes/work-orders';
import {
    create as createQuotation,
    index as quotationsIndex,
} from '@/routes/quotations';
import type {
    Attachment,
    Company,
    CompanyContactLink,
    CustomFieldDefinition,
    RelatedDocumentGroups,
    RelatedDocumentKey,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    company: Company;
    timeline: TimelineItem[];
    attachments: Attachment[];
    customFields: CustomFieldDefinition[];
    related: RelatedDocumentGroups;
}>();

defineOptions({
    layout: (props: { currentTeam: Team; company: Company }) => ({
        breadcrumbs: [
            { title: 'Companies', href: index(props.currentTeam.slug) },
            {
                title: props.company.name,
                href: show([props.currentTeam.slug, props.company.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const documentLinks = computed(() => {
    const query = { company: props.company.id };
    const create: Partial<Record<RelatedDocumentKey, string>> = {};

    if (canEdit('quotations')) {
        create.quotations = createQuotation(slug.value, { query }).url;
    }

    if (canEdit('invoicing')) {
        create.invoices = createInvoice(slug.value, { query }).url;
    }

    if (canEdit('expenses')) {
        create.expenses = createExpense(slug.value, { query }).url;
    }

    if (canEdit('projects')) {
        create.projects = createProject(slug.value, { query }).url;
    }

    if (canEdit('tickets')) {
        create.tickets = createTicket(slug.value, { query }).url;
    }

    if (canEdit('work_orders')) {
        create.work_orders = createWorkOrder(slug.value, { query }).url;
    }

    return {
        create,
        list: {
            projects: projectsIndex(slug.value, {
                query: { ...query, status: 'all' },
            }).url,
            quotations: quotationsIndex(slug.value, { query }).url,
            invoices: invoicesIndex(slug.value, { query }).url,
            expenses: expensesIndex(slug.value, { query }).url,
            work_orders: workOrdersIndex(slug.value, { query }).url,
        },
    };
});

const deleting = ref(false);
const linkContactId = ref<number | null>(null);
const linkPosition = ref('');

const toggleArchive = () => {
    router.patch(
        archive([slug.value, props.company.id]).url,
        {},
        {
            preserveScroll: true,
        },
    );
};

const destroyCompany = () => {
    router.delete(destroy([slug.value, props.company.id]).url);
};

const addContactLink = () => {
    if (!linkContactId.value) {
        return;
    }

    router.post(
        linkContact([slug.value, props.company.id]).url,
        {
            contact_id: linkContactId.value,
            position: linkPosition.value || null,
            is_primary: (props.company.contacts ?? []).length === 0,
            is_decision_maker: false,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                linkContactId.value = null;
                linkPosition.value = '';
            },
        },
    );
};

const removeContactLink = (contact: CompanyContactLink) => {
    if (
        !window.confirm(
            `Unlink ${contact.full_name} from ${props.company.name}?`,
        )
    ) {
        return;
    }

    router.delete(
        unlinkContact([slug.value, props.company.id, contact.id]).url,
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="company.name" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="company.name">
            <template #badges>
                <Pill :color="company.relationship_color">
                    {{ company.relationship_label }}
                </Pill>
                <Pill v-if="company.archived_at" color="gray">Archived</Pill>
                <Pill
                    v-for="tag in company.tags ?? []"
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
                    <a
                        v-if="company.email"
                        :href="`mailto:${company.email}`"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <Mail class="size-3.5" /> {{ company.email }}
                    </a>
                    <a
                        v-if="company.phone || company.mobile"
                        :href="`tel:${company.phone ?? company.mobile}`"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <Phone class="size-3.5" />
                        {{ company.phone ?? company.mobile }}
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
                                query: { plan: 1, company: company.id },
                            }).url
                        "
                        ><CalendarPlus /> Plan</Link
                    >
                </Button>
                <Button variant="outline" size="sm" @click="toggleArchive">
                    <ArchiveRestore v-if="company.archived_at" />
                    <Archive v-else />
                    {{ company.archived_at ? 'Restore' : 'Archive' }}
                </Button>
                <Button variant="outline" size="sm" @click="deleting = true">
                    <Trash2 /> Delete
                </Button>
                <Button size="sm" as-child>
                    <Link :href="edit([slug, company.id]).url">
                        <Pencil /> Edit
                    </Link>
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
                        <DetailItem
                            label="Business type"
                            :value="company.business_type_label"
                        />
                        <DetailItem
                            label="Industry"
                            :value="company.industry"
                        />
                        <DetailItem label="Mobile" :value="company.mobile" />
                        <DetailItem label="Website">
                            <a
                                v-if="company.website"
                                :href="company.website"
                                target="_blank"
                                rel="noopener"
                                class="text-primary hover:underline"
                            >
                                {{ company.website }}
                            </a>
                            <span v-else class="text-muted-foreground">—</span>
                        </DetailItem>
                        <DetailItem
                            label="Account manager"
                            :value="company.responsible_user?.name"
                        />
                    </dl>

                    <Separator class="my-3" />
                    <p
                        class="mb-1 text-xs font-medium text-muted-foreground uppercase"
                    >
                        Tax registration
                    </p>
                    <dl>
                        <DetailItem label="NTN" :value="company.ntn" />
                        <DetailItem label="STRN" :value="company.strn" />
                        <DetailItem label="CNIC" :value="company.cnic" />
                        <DetailItem
                            label="Sales tax"
                            :value="
                                company.sales_tax_registered
                                    ? 'Registered'
                                    : 'Not registered'
                            "
                        />
                    </dl>

                    <Separator class="my-3" />
                    <p
                        class="mb-1 text-xs font-medium text-muted-foreground uppercase"
                    >
                        Invoicing
                    </p>
                    <dl>
                        <DetailItem
                            label="Payment term"
                            :value="
                                company.payment_term?.name ?? 'Team default'
                            "
                        />
                        <DetailItem
                            label="Currency"
                            :value="company.currency ?? 'Team default'"
                        />
                        <DetailItem label="IBAN" :value="company.iban" />
                    </dl>

                    <template v-if="company.addresses?.length">
                        <Separator class="my-3" />
                        <p
                            class="mb-1 text-xs font-medium text-muted-foreground uppercase"
                        >
                            Addresses
                        </p>
                        <dl>
                            <DetailItem
                                v-for="address in company.addresses"
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
                            :values="company.custom_fields"
                        />
                    </template>

                    <template v-if="company.remarks">
                        <Separator class="my-3" />
                        <p
                            class="text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ company.remarks }}
                        </p>
                    </template>
                </CardContent>
            </Card>

            <div class="lg:col-span-2">
                <Tabs default-value="timeline">
                    <TabsList>
                        <TabsTrigger value="timeline">Timeline</TabsTrigger>
                        <TabsTrigger value="contacts">
                            Contacts ({{ company.contacts?.length ?? 0 }})
                        </TabsTrigger>
                        <TabsTrigger value="documents">Documents</TabsTrigger>
                        <TabsTrigger value="files">
                            Files ({{ attachments.length }})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="documents">
                        <Card>
                            <CardContent class="pt-6">
                                <RelatedDocuments
                                    :related="related"
                                    :create-links="documentLinks.create"
                                    :list-links="documentLinks.list"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="company"
                                    :notable-id="company.id"
                                    :can-write="canEdit('crm')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="contacts">
                        <Card>
                            <CardContent class="space-y-4 pt-6">
                                <div
                                    v-if="canEdit('crm')"
                                    class="flex flex-col gap-2 sm:flex-row"
                                >
                                    <EntitySelect
                                        v-model="linkContactId"
                                        type="contacts"
                                        placeholder="Link an existing contact…"
                                        class="sm:flex-1"
                                    />
                                    <Input
                                        v-model="linkPosition"
                                        placeholder="Position (optional)"
                                        class="sm:w-48"
                                    />
                                    <Button
                                        variant="outline"
                                        :disabled="!linkContactId"
                                        @click="addContactLink"
                                    >
                                        <Link2 /> Link
                                    </Button>
                                    <Button as-child>
                                        <Link
                                            :href="
                                                createContact(slug, {
                                                    query: {
                                                        company: company.id,
                                                    },
                                                }).url
                                            "
                                        >
                                            <Plus /> New contact
                                        </Link>
                                    </Button>
                                </div>

                                <p
                                    v-if="!company.contacts?.length"
                                    class="py-6 text-center text-sm text-muted-foreground"
                                >
                                    No contacts linked to this company yet.
                                </p>

                                <ul v-else class="divide-y rounded-md border">
                                    <li
                                        v-for="contact in company.contacts"
                                        :key="contact.id"
                                        class="flex items-center justify-between gap-3 px-3 py-2.5"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Link
                                                    :href="
                                                        showContact([
                                                            slug,
                                                            contact.id,
                                                        ]).url
                                                    "
                                                    class="font-medium hover:underline"
                                                >
                                                    {{ contact.full_name }}
                                                </Link>
                                                <Star
                                                    v-if="contact.is_primary"
                                                    class="size-3.5 fill-amber-400 text-amber-400"
                                                />
                                                <Pill
                                                    v-if="
                                                        contact.is_decision_maker
                                                    "
                                                    color="indigo"
                                                >
                                                    Decision maker
                                                </Pill>
                                            </div>
                                            <p
                                                class="truncate text-sm text-muted-foreground"
                                            >
                                                {{
                                                    [
                                                        contact.position,
                                                        contact.email,
                                                        contact.mobile ??
                                                            contact.phone,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')
                                                }}
                                            </p>
                                        </div>
                                        <Button
                                            v-if="canEdit('crm')"
                                            variant="ghost"
                                            size="icon-sm"
                                            title="Unlink"
                                            @click="removeContactLink(contact)"
                                        >
                                            <Unlink />
                                        </Button>
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="files">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="company"
                                    :attachable-id="company.id"
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
            title="Delete company?"
            :description="`${company.name} will be removed from your lists. Its history is kept.`"
            confirm-label="Delete"
            destructive
            @confirm="destroyCompany"
        />
    </div>
</template>
