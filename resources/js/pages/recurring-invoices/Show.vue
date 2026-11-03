<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pause, Pencil, Play, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import DocumentView from '@/components/documents/DocumentView.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import {
    destroy,
    edit,
    generate,
    index,
    show,
    toggle,
} from '@/routes/recurring-invoices';
import type {
    DocumentSummary,
    RecurringInvoice,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    profile: RecurringInvoice;
    invoices: DocumentSummary[];
    upcoming: string[];
    timeline: TimelineItem[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; profile: RecurringInvoice }) => ({
        breadcrumbs: [
            {
                title: 'Recurring invoices',
                href: index(props.currentTeam.slug),
            },
            {
                title: props.profile.title,
                href: show([props.currentTeam.slug, props.profile.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const deleting = ref(false);
const generating = ref(false);

const post = (url: string) => router.post(url, {}, { preserveScroll: true });

const customer = computed(() => ({
    name: props.profile.customer_name,
    address: null,
    email: null,
    ntn: null,
    strn: null,
    cnic: null,
    registered: false,
}));
</script>

<template>
    <Head :title="profile.title" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="profile.title">
            <template #badges>
                <Pill :color="profile.status_color">{{
                    profile.status_label
                }}</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(profile.total, profile.currency)
                    }}</span>
                    · {{ profile.frequency_label.toLowerCase() }} ·
                    <Link
                        v-if="profile.company"
                        :href="showCompany([slug, profile.company.id]).url"
                        class="hover:underline"
                    >
                        {{ profile.customer_name }}
                    </Link>
                    <span v-else>{{ profile.customer_name }}</span>
                </p>
            </template>
            <template v-if="canEdit('invoicing')" #actions>
                <Button variant="outline" size="sm" @click="deleting = true">
                    <Trash2 />
                </Button>
                <Button
                    v-if="profile.next_date"
                    variant="outline"
                    size="sm"
                    @click="post(toggle([slug, profile.id]).url)"
                >
                    <template v-if="profile.is_active"
                        ><Pause /> Pause</template
                    >
                    <template v-else><Play /> Resume</template>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="edit([slug, profile.id]).url"
                        ><Pencil /> Edit</Link
                    >
                </Button>
                <Button
                    v-if="profile.next_date"
                    size="sm"
                    @click="generating = true"
                >
                    <Plus /> Create next invoice now
                </Button>
            </template>
        </PageHeader>

        <InputError :message="errors.profile" />

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle>Schedule</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <DetailItem label="Repeats">{{
                        profile.frequency_label
                    }}</DetailItem>
                    <DetailItem label="Started">{{
                        formatDate(profile.start_date)
                    }}</DetailItem>
                    <DetailItem label="Ends">{{
                        profile.end_date
                            ? formatDate(profile.end_date)
                            : 'Never'
                    }}</DetailItem>
                    <DetailItem label="Next invoice">{{
                        profile.next_date
                            ? formatDate(profile.next_date)
                            : 'None — finished'
                    }}</DetailItem>
                    <DetailItem label="Payment term">{{
                        profile.payment_term?.name ?? '—'
                    }}</DetailItem>
                    <DetailItem label="When created">
                        {{
                            profile.auto_issue
                                ? profile.auto_send
                                    ? 'Issued and e-mailed automatically'
                                    : 'Issued automatically'
                                : 'Saved as a draft to check'
                        }}
                    </DetailItem>
                    <DetailItem label="Invoices created">
                        {{ profile.generated_count }}
                        <span
                            v-if="profile.last_generated_at"
                            class="text-muted-foreground"
                        >
                            (last
                            {{ formatDateTime(profile.last_generated_at) }})
                        </span>
                    </DetailItem>
                    <div v-if="upcoming.length" class="space-y-1">
                        <p
                            class="text-xs font-medium text-muted-foreground uppercase"
                        >
                            Coming up
                        </p>
                        <ul class="text-sm">
                            <li v-for="date in upcoming" :key="date">
                                {{ formatDate(date) }}
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <div class="lg:col-span-2">
                <Tabs default-value="invoices">
                    <TabsList>
                        <TabsTrigger value="invoices"
                            >Invoices ({{ invoices.length }})</TabsTrigger
                        >
                        <TabsTrigger value="template">Template</TabsTrigger>
                        <TabsTrigger value="timeline">History</TabsTrigger>
                    </TabsList>
                    <TabsContent value="invoices">
                        <Card>
                            <CardContent class="pt-6">
                                <p
                                    v-if="invoices.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No invoices created yet. The first one is
                                    created on
                                    {{ formatDate(profile.next_date) }}.
                                </p>
                                <ul v-else class="divide-y rounded-md border">
                                    <li
                                        v-for="invoice in invoices"
                                        :key="invoice.id"
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm"
                                    >
                                        <Link
                                            :href="invoice.url"
                                            class="font-medium hover:underline"
                                        >
                                            {{ invoice.number ?? 'Draft' }}
                                        </Link>
                                        <span
                                            class="min-w-0 flex-1 truncate text-muted-foreground"
                                            >{{ invoice.title }}</span
                                        >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                formatDate(invoice.date)
                                            }}</span
                                        >
                                        <Pill :color="invoice.status_color">{{
                                            invoice.status_label
                                        }}</Pill>
                                        <span
                                            class="w-32 text-right font-medium"
                                            >{{
                                                formatMoney(
                                                    invoice.total,
                                                    invoice.currency,
                                                )
                                            }}</span
                                        >
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="template">
                        <DocumentView
                            title="Invoice"
                            :meta="[
                                {
                                    label: 'Repeats',
                                    value: profile.frequency_label,
                                },
                            ]"
                            :customer="customer"
                            :lines="profile.lines ?? []"
                            :taxes="profile.taxes ?? []"
                            :currency="profile.currency"
                            :subtotal="profile.subtotal"
                            :discount-percent="profile.discount_percent"
                            :discount-amount="profile.discount_amount"
                            :further-tax-rate="profile.further_tax_rate"
                            :further-tax-amount="profile.further_tax_amount"
                            :total="profile.total"
                            :introduction="profile.introduction"
                            :terms="profile.terms"
                            :heading="profile.title"
                        />
                    </TabsContent>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="recurring_invoice"
                                    :notable-id="profile.id"
                                    :can-write="canEdit('invoicing')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <ConfirmDialog
            v-model:open="generating"
            title="Create the next invoice now?"
            :description="`The invoice for ${formatDate(profile.next_date)} is created now instead of on its date.`"
            confirm-label="Create invoice"
            @confirm="
                generating = false;
                post(generate([slug, profile.id]).url);
            "
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete this recurring invoice?"
            description="No more invoices will be created. Invoices created before are kept."
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, profile.id]).url)"
        />
    </div>
</template>
