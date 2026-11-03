<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import DailyExpenseDialog from '@/components/daily-expenses/DailyExpenseDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { destroy, index, show } from '@/routes/daily-expenses';
import type {
    Attachment,
    DailyExpense,
    IdName,
    Option,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    expense: DailyExpense;
    attachments: Attachment[];
    timeline: TimelineItem[];
    categories: IdName[];
    paymentMethods: Option[];
    people: string[];
    payees: string[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; expense: DailyExpense }) => ({
        breadcrumbs: [
            { title: 'Daily expenses', href: index(props.currentTeam.slug) },
            {
                title: props.expense.number ?? 'Expense',
                href: show([props.currentTeam.slug, props.expense.id]),
            },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const editing = ref(false);
const deleting = ref(false);

const remove = () =>
    router.delete(destroy([slug.value, props.expense.id]).url, {
        data: { to_list: true },
    });
</script>

<template>
    <Head :title="`Daily expense ${expense.number ?? ''}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="expense.description"
            :description="expense.number ?? ''"
        >
            <template #badges>
                <Pill v-if="expense.category" color="gray">{{
                    expense.category.name
                }}</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(expense.amount, currency)
                    }}</span>
                    · {{ formatDate(expense.date) }} ·
                    {{ expense.payment_method_label }}
                </p>
            </template>
            <template v-if="canEdit('daily_expenses')" #actions>
                <Button variant="outline" size="sm" @click="deleting = true">
                    <Trash2 />
                </Button>
                <Button variant="outline" size="sm" @click="editing = true">
                    <Pencil /> Edit
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <DetailItem label="Date">{{
                        formatDate(expense.date)
                    }}</DetailItem>
                    <DetailItem label="Paid with">{{
                        expense.payment_method_label
                    }}</DetailItem>
                    <DetailItem label="Paid by">{{
                        expense.paid_by ?? '—'
                    }}</DetailItem>
                    <DetailItem label="Paid to">{{
                        expense.paid_to ?? '—'
                    }}</DetailItem>
                    <DetailItem label="Receipt no.">{{
                        expense.reference ?? '—'
                    }}</DetailItem>
                    <DetailItem label="Entered by">
                        {{ expense.creator?.name ?? '—' }},
                        {{ formatDateTime(expense.created_at) }}
                    </DetailItem>
                    <DetailItem v-if="expense.notes" label="Notes">
                        <span class="whitespace-pre-line">{{
                            expense.notes
                        }}</span>
                    </DetailItem>
                </CardContent>
            </Card>

            <div class="lg:col-span-2">
                <Tabs default-value="receipts">
                    <TabsList>
                        <TabsTrigger value="receipts"
                            >Receipts ({{ attachments.length }})</TabsTrigger
                        >
                        <TabsTrigger value="timeline">History</TabsTrigger>
                    </TabsList>
                    <TabsContent value="receipts">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="daily_expense"
                                    :attachable-id="expense.id"
                                    :can-write="canEdit('daily_expenses')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="daily_expense"
                                    :notable-id="expense.id"
                                    :can-write="canEdit('daily_expenses')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <DailyExpenseDialog
            v-model:open="editing"
            :expense="expense"
            :categories="categories"
            :payment-methods="paymentMethods"
            :people="people"
            :payees="payees"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete this expense?"
            :description="`${expense.number ?? 'The expense'} will be deleted.`"
            confirm-label="Delete"
            destructive
            @confirm="remove"
        />
    </div>
</template>
