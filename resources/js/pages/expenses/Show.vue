<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Banknote, Check, Pencil, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import PayExpenseDialog from '@/components/expenses/PayExpenseDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatPercent,
} from '@/lib/format';
import { show as showCompany } from '@/routes/companies';
import { approve, destroy, edit, index, reject, show } from '@/routes/expenses';
import { destroy as destroyPayment } from '@/routes/expenses/payments';
import type {
    Attachment,
    Expense,
    ExpensePayment,
    Option,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    expense: Expense;
    timeline: TimelineItem[];
    attachments: Attachment[];
    paymentMethods: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; expense: Expense }) => ({
        breadcrumbs: [
            { title: 'Expenses', href: index(props.currentTeam.slug) },
            {
                title: props.expense.number ?? 'Expense',
                href: show([props.currentTeam.slug, props.expense.id]),
            },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const paying = ref(false);
const deleting = ref(false);
const rejecting = ref(false);
const rejectReason = ref('');
const paymentToDelete = ref<ExpensePayment | null>(null);

const hasPayments = computed(() => (props.expense.payments?.length ?? 0) > 0);
const withheld = computed(
    () =>
        Number(props.expense.income_tax_withheld) +
        Number(props.expense.sales_tax_withheld),
);

const approveExpense = () =>
    router.post(
        approve([slug.value, props.expense.id]).url,
        {},
        {
            preserveScroll: true,
        },
    );

const submitReject = () => {
    router.post(
        reject([slug.value, props.expense.id]).url,
        { reason: rejectReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejecting.value = false;
            },
        },
    );
};

const removePayment = () => {
    if (!paymentToDelete.value) {
        return;
    }

    router.delete(
        destroyPayment([slug.value, props.expense.id, paymentToDelete.value.id])
            .url,
        {
            preserveScroll: true,
            onFinish: () => {
                paymentToDelete.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Expense ${expense.number}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`Expense ${expense.number}`"
            :description="expense.title"
        >
            <template #badges>
                <Pill :color="expense.status_color">{{
                    expense.status_label
                }}</Pill>
                <Pill v-if="expense.category" color="gray">{{
                    expense.category.name
                }}</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    <span class="text-lg font-semibold text-foreground">{{
                        formatMoney(expense.total, expense.currency)
                    }}</span>
                    ·
                    <Link
                        v-if="expense.supplier"
                        :href="showCompany([slug, expense.supplier.id]).url"
                        class="hover:underline"
                    >
                        {{ expense.supplier_name }}
                    </Link>
                    <span v-else>{{ expense.supplier_name }}</span>
                    <template v-if="expense.bill_number">
                        · bill {{ expense.bill_number }}
                    </template>
                </p>
            </template>
            <template v-if="canEdit('expenses')" #actions>
                <template v-if="!hasPayments">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="deleting = true"
                    >
                        <Trash2 />
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="edit([slug, expense.id]).url"
                            ><Pencil /> Edit</Link
                        >
                    </Button>
                </template>
                <Button
                    v-if="expense.status === 'approved' && !expense.is_paid"
                    size="sm"
                    @click="paying = true"
                >
                    <Banknote /> Pay supplier
                </Button>
            </template>
        </PageHeader>

        <InputError :message="errors.expense" />

        <div
            v-if="canEdit('expenses') && expense.status !== 'approved'"
            class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"
            :class="
                expense.status === 'rejected'
                    ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40'
                    : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40'
            "
        >
            <span v-if="expense.status === 'pending'">
                This bill is waiting for approval before it can be paid.
            </span>
            <span v-else>
                Rejected{{
                    expense.rejection_reason
                        ? `: ${expense.rejection_reason}`
                        : '.'
                }}
            </span>
            <Button
                size="sm"
                variant="ghost"
                class="text-green-700"
                @click="approveExpense"
            >
                <Check /> Approve
            </Button>
            <Button
                v-if="expense.status === 'pending'"
                size="sm"
                variant="ghost"
                class="text-destructive"
                @click="rejecting = true"
            >
                <X /> Reject
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Amounts</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Excl. tax</dt>
                                <dd>
                                    {{
                                        formatMoney(
                                            expense.net_amount,
                                            expense.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">
                                    Sales tax
                                    <template v-if="Number(expense.tax_rate)"
                                        >({{
                                            formatPercent(expense.tax_rate)
                                        }})</template
                                    >
                                </dt>
                                <dd>
                                    {{
                                        formatMoney(
                                            expense.tax_amount,
                                            expense.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between border-t pt-1 font-semibold"
                            >
                                <dt>Total</dt>
                                <dd>
                                    {{
                                        formatMoney(
                                            expense.total,
                                            expense.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <template v-if="withheld">
                                <div
                                    v-if="Number(expense.income_tax_withheld)"
                                    class="flex justify-between"
                                >
                                    <dt class="text-muted-foreground">
                                        Income tax withheld
                                    </dt>
                                    <dd>
                                        −
                                        {{
                                            formatMoney(
                                                expense.income_tax_withheld,
                                                expense.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    v-if="Number(expense.sales_tax_withheld)"
                                    class="flex justify-between"
                                >
                                    <dt class="text-muted-foreground">
                                        Sales tax withheld
                                    </dt>
                                    <dd>
                                        −
                                        {{
                                            formatMoney(
                                                expense.sales_tax_withheld,
                                                expense.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-muted-foreground">
                                        To pay the supplier
                                    </dt>
                                    <dd>
                                        {{
                                            formatMoney(
                                                expense.payable,
                                                expense.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </template>
                            <div
                                v-if="Number(expense.amount_paid)"
                                class="flex justify-between"
                            >
                                <dt class="text-muted-foreground">Paid</dt>
                                <dd>
                                    −
                                    {{
                                        formatMoney(
                                            expense.amount_paid,
                                            expense.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="expense.status === 'approved'"
                                class="flex justify-between border-t pt-1 font-semibold"
                            >
                                <dt>Still to pay</dt>
                                <dd>
                                    {{
                                        formatMoney(
                                            expense.balance_due,
                                            expense.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-muted-foreground">
                            {{
                                expense.tax_claimable
                                    ? 'The sales tax is claimed as input tax.'
                                    : 'The sales tax is not claimable.'
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <DetailItem label="Supplier NTN">{{
                            expense.supplier_ntn ?? '—'
                        }}</DetailItem>
                        <DetailItem label="Supplier STRN">{{
                            expense.supplier_strn ?? '—'
                        }}</DetailItem>
                        <DetailItem label="Bill date">{{
                            formatDate(expense.date)
                        }}</DetailItem>
                        <DetailItem label="Pay by">{{
                            formatDate(expense.due_date)
                        }}</DetailItem>
                        <DetailItem label="Entered by">
                            {{ expense.creator?.name ?? '—' }}
                        </DetailItem>
                        <DetailItem v-if="expense.approver" label="Approved by">
                            {{ expense.approver.name }},
                            {{ formatDateTime(expense.approved_at) }}
                        </DetailItem>
                        <DetailItem v-if="expense.notes" label="Notes">
                            <span class="whitespace-pre-line">{{
                                expense.notes
                            }}</span>
                        </DetailItem>
                    </CardContent>
                </Card>

                <Card v-if="hasPayments">
                    <CardHeader>
                        <CardTitle>Payments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y rounded-md border text-sm">
                            <li
                                v-for="payment in expense.payments"
                                :key="payment.id"
                                class="flex items-start justify-between gap-2 p-2"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{
                                            formatMoney(
                                                payment.amount,
                                                expense.currency,
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDate(payment.date) }} ·
                                        {{ payment.method_label }}
                                        <template v-if="payment.reference">
                                            · {{ payment.reference }}
                                        </template>
                                    </p>
                                </div>
                                <Button
                                    v-if="canEdit('expenses')"
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
                    </CardContent>
                </Card>
            </div>

            <div class="lg:col-span-2">
                <Tabs default-value="bill">
                    <TabsList>
                        <TabsTrigger value="bill"
                            >Bill ({{ attachments.length }})</TabsTrigger
                        >
                        <TabsTrigger value="timeline">History</TabsTrigger>
                    </TabsList>
                    <TabsContent value="bill">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="expense"
                                    :attachable-id="expense.id"
                                    :can-write="canEdit('expenses')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="expense"
                                    :notable-id="expense.id"
                                    :can-write="canEdit('expenses')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <PayExpenseDialog
            v-if="expense.status === 'approved'"
            v-model:open="paying"
            :expense="expense"
            :methods="paymentMethods"
        />

        <ConfirmDialog
            v-model:open="rejecting"
            title="Reject this bill?"
            confirm-label="Reject"
            destructive
            @confirm="submitReject"
        >
            <Textarea
                v-model="rejectReason"
                rows="3"
                placeholder="Reason (e.g. duplicate bill, wrong amount)"
            />
        </ConfirmDialog>

        <ConfirmDialog
            :open="paymentToDelete !== null"
            title="Remove this payment?"
            confirm-label="Remove payment"
            destructive
            @update:open="paymentToDelete = $event ? paymentToDelete : null"
            @confirm="removePayment"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete this expense?"
            :description="`Expense ${expense.number} will be deleted.`"
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, expense.id]).url)"
        />
    </div>
</template>
