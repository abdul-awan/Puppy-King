<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { FileText, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
import InputError from '@/components/InputError.vue';
import AdvanceDialog from '@/components/payroll/AdvanceDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { destroy as destroyAdvance } from '@/routes/advances';
import { destroy, edit, index, show, taxCertificate } from '@/routes/employees';
import { show as showRun } from '@/routes/payroll';
import { pdf as payslipPdf } from '@/routes/payslips';
import type {
    Attachment,
    Employee,
    EmployeeAdvanceRow,
    SalaryEstimate,
    Team,
    TimelineItem,
} from '@/types';

type PayslipRow = {
    id: number;
    payroll_run_id: number;
    number: string | null;
    period: string;
    period_label: string;
    gross: string;
    income_tax: string;
    net: string;
    paid_on: string | null;
    is_draft: boolean;
};

const props = defineProps<{
    employee: Employee;
    estimate: SalaryEstimate;
    advances: EmployeeAdvanceRow[];
    payslips: PayslipRow[];
    taxYears: number[];
    attachments: Attachment[];
    timeline: TimelineItem[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; employee: Employee }) => ({
        breadcrumbs: [
            { title: 'Employees', href: index(props.currentTeam.slug) },
            {
                title: props.employee.name,
                href: show([props.currentTeam.slug, props.employee.id]),
            },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const money = (value: string | number) => formatMoney(value, currency.value);

const deleting = ref(false);
const addingAdvance = ref(false);
const editingAdvance = ref<EmployeeAdvanceRow | null>(null);
const advanceToDelete = ref<EmployeeAdvanceRow | null>(null);
const editAdvanceOpen = computed({
    get: () => editingAdvance.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editingAdvance.value = null;
        }
    },
});

const itemsOf = (kind: string) =>
    (props.employee.items ?? []).filter((item) => item.kind === kind);

const itemValue = (value: string, type: string) =>
    type === 'fixed'
        ? money(value)
        : `${Number(value)}% of ${type === 'percent_basic' ? 'basic' : 'gross'}`;

const open = computed(() =>
    props.advances.filter((advance) => Number(advance.outstanding) > 0),
);

const removeAdvance = () => {
    if (!advanceToDelete.value) {
        return;
    }

    router.delete(destroyAdvance([slug.value, advanceToDelete.value.id]).url, {
        preserveScroll: true,
        onFinish: () => {
            advanceToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head :title="employee.name" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="employee.name"
            :description="
                [employee.code, employee.designation, employee.department]
                    .filter(Boolean)
                    .join(' · ')
            "
        >
            <template #badges>
                <Pill :color="employee.status_color">{{
                    employee.status_label
                }}</Pill>
                <Pill color="gray">{{ employee.employment_type_label }}</Pill>
            </template>
            <template v-if="canEdit('payroll')" #actions>
                <Button
                    v-if="payslips.length === 0"
                    variant="outline"
                    size="sm"
                    title="Remove"
                    @click="deleting = true"
                >
                    <Trash2 />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="addingAdvance = true"
                >
                    <Plus /> Advance
                </Button>
                <Button size="sm" as-child>
                    <Link :href="edit([slug, employee.id]).url"
                        ><Pencil /> Edit</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <InputError :message="errors.employee ?? errors.advance" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Salary a month</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <dl class="space-y-1">
                            <div class="flex justify-between gap-2">
                                <dt>Basic salary</dt>
                                <dd class="tabular-nums">
                                    {{ money(employee.basic_salary) }}
                                </dd>
                            </div>
                            <div
                                v-for="item in itemsOf('earning')"
                                :key="item.id"
                                class="flex justify-between gap-2"
                            >
                                <dt>
                                    {{ item.name }}
                                    <span
                                        v-if="
                                            item.tax_label &&
                                            item.tax_label !== 'Taxable'
                                        "
                                        class="text-xs text-muted-foreground"
                                        >({{ item.tax_label }})</span
                                    >
                                </dt>
                                <dd class="tabular-nums">
                                    {{
                                        itemValue(item.value, item.amount_type)
                                    }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-2 border-t pt-1 font-semibold"
                            >
                                <dt>Gross</dt>
                                <dd class="tabular-nums">
                                    {{ money(estimate.gross) }}
                                </dd>
                            </div>
                            <div
                                v-for="item in itemsOf('deduction')"
                                :key="item.id"
                                class="flex justify-between gap-2 text-muted-foreground"
                            >
                                <dt>{{ item.name }}</dt>
                                <dd class="tabular-nums">
                                    −
                                    {{
                                        itemValue(item.value, item.amount_type)
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="employee.eobi"
                                class="flex justify-between gap-2 text-muted-foreground"
                            >
                                <dt>EOBI (employee share)</dt>
                                <dd>included</dd>
                            </div>
                            <div
                                class="flex justify-between gap-2 text-muted-foreground"
                            >
                                <dt>Income tax (estimate)</dt>
                                <dd class="tabular-nums">
                                    − {{ money(estimate.income_tax) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between gap-2 border-t pt-1 font-semibold"
                            >
                                <dt>Net pay (estimate)</dt>
                                <dd class="tabular-nums">
                                    {{ money(estimate.net) }}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-xs text-muted-foreground">
                            {{ employee.tax_mode_label }}.
                            <template v-if="employee.tax_mode === 'auto'">
                                Expected taxable salary
                                {{ money(estimate.yearly_taxable) }} in tax year
                                {{ estimate.tax_year }}: tax
                                {{ money(estimate.yearly_tax) }}
                                <template
                                    v-if="
                                        estimate.table_year !== null &&
                                        estimate.table_year !==
                                            estimate.tax_year
                                    "
                                >
                                    (with the rates of tax year
                                    {{ estimate.table_year }})</template
                                >.
                            </template>
                            The payroll works out the exact tax with the days
                            paid and the earlier months.
                        </p>
                        <p
                            v-if="Number(estimate.employer)"
                            class="text-xs text-muted-foreground"
                        >
                            The company also pays
                            {{ money(estimate.employer) }} a month on top (EOBI
                            and other contributions): total cost
                            {{ money(estimate.cost) }}.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <DetailItem label="Father's / husband's name">{{
                            employee.father_name ?? '—'
                        }}</DetailItem>
                        <DetailItem label="CNIC">{{
                            employee.cnic ?? '—'
                        }}</DetailItem>
                        <DetailItem label="Date of birth">{{
                            formatDate(employee.date_of_birth)
                        }}</DetailItem>
                        <DetailItem label="Phone">{{
                            employee.phone ?? '—'
                        }}</DetailItem>
                        <DetailItem label="E-mail">{{
                            employee.email ?? '—'
                        }}</DetailItem>
                        <DetailItem v-if="employee.address" label="Address">
                            <span class="whitespace-pre-line">{{
                                employee.address
                            }}</span>
                        </DetailItem>
                        <DetailItem label="Joined">{{
                            formatDate(employee.joining_date)
                        }}</DetailItem>
                        <DetailItem
                            v-if="employee.leaving_date"
                            label="Leaves"
                            >{{ formatDate(employee.leaving_date) }}</DetailItem
                        >
                        <DetailItem label="EOBI">{{
                            employee.eobi
                                ? `Yes${employee.eobi_number ? ` · ${employee.eobi_number}` : ''}`
                                : 'No'
                        }}</DetailItem>
                        <DetailItem
                            v-if="employee.opening_tax_year"
                            label="Salary before this payroll"
                        >
                            Tax year {{ employee.opening_tax_year }}:
                            {{ money(employee.opening_taxable ?? 0) }} taxable,
                            {{ money(employee.opening_tax ?? 0) }} tax deducted
                        </DetailItem>
                        <DetailItem label="Login">{{
                            employee.user
                                ? `${employee.user.name} (sees own payslips)`
                                : 'None'
                        }}</DetailItem>
                        <DetailItem v-if="employee.notes" label="Notes">
                            <span class="whitespace-pre-line">{{
                                employee.notes
                            }}</span>
                        </DetailItem>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <DetailItem label="Salary paid by">{{
                            employee.payment_method_label
                        }}</DetailItem>
                        <template v-if="employee.payment_method !== 'cash'">
                            <DetailItem label="Bank">{{
                                employee.bank_name ?? '—'
                            }}</DetailItem>
                            <DetailItem label="Account title">{{
                                employee.bank_account_title ?? '—'
                            }}</DetailItem>
                            <DetailItem label="Account / IBAN">{{
                                employee.bank_account_number ?? '—'
                            }}</DetailItem>
                        </template>
                    </CardContent>
                </Card>
            </div>

            <div class="lg:col-span-2">
                <Tabs default-value="payslips">
                    <TabsList>
                        <TabsTrigger value="payslips"
                            >Payslips ({{ payslips.length }})</TabsTrigger
                        >
                        <TabsTrigger value="advances"
                            >Advances ({{ open.length }})</TabsTrigger
                        >
                        <TabsTrigger value="files"
                            >Files ({{ attachments.length }})</TabsTrigger
                        >
                        <TabsTrigger value="timeline">History</TabsTrigger>
                    </TabsList>

                    <TabsContent value="payslips">
                        <Card>
                            <CardContent class="pt-6">
                                <p
                                    v-if="payslips.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No payslips yet. They are made when you run
                                    the payroll of a month.
                                </p>
                                <Table v-else>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Month</TableHead>
                                            <TableHead class="text-right"
                                                >Gross</TableHead
                                            >
                                            <TableHead
                                                class="hidden text-right sm:table-cell"
                                                >Income tax</TableHead
                                            >
                                            <TableHead class="text-right"
                                                >Net</TableHead
                                            >
                                            <TableHead>Status</TableHead>
                                            <TableHead class="w-12" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="payslip in payslips"
                                            :key="payslip.id"
                                        >
                                            <TableCell>
                                                <Link
                                                    :href="
                                                        showRun([
                                                            slug,
                                                            payslip.payroll_run_id,
                                                        ]).url
                                                    "
                                                    class="font-medium hover:underline"
                                                >
                                                    {{ payslip.period_label }}
                                                </Link>
                                                <p
                                                    v-if="payslip.number"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ payslip.number }}
                                                </p>
                                            </TableCell>
                                            <TableCell
                                                class="text-right text-sm tabular-nums"
                                                >{{
                                                    money(payslip.gross)
                                                }}</TableCell
                                            >
                                            <TableCell
                                                class="hidden text-right text-sm tabular-nums sm:table-cell"
                                                >{{
                                                    money(payslip.income_tax)
                                                }}</TableCell
                                            >
                                            <TableCell
                                                class="text-right text-sm font-medium tabular-nums"
                                                >{{
                                                    money(payslip.net)
                                                }}</TableCell
                                            >
                                            <TableCell>
                                                <Pill
                                                    :color="
                                                        payslip.is_draft
                                                            ? 'gray'
                                                            : payslip.paid_on
                                                              ? 'green'
                                                              : 'blue'
                                                    "
                                                >
                                                    {{
                                                        payslip.is_draft
                                                            ? 'Draft'
                                                            : payslip.paid_on
                                                              ? 'Paid'
                                                              : 'To pay'
                                                    }}
                                                </Pill>
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    as-child
                                                >
                                                    <a
                                                        :href="
                                                            payslipPdf([
                                                                slug,
                                                                payslip.id,
                                                            ]).url
                                                        "
                                                        target="_blank"
                                                        rel="noopener"
                                                        title="Payslip PDF"
                                                        ><FileText
                                                    /></a>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                                <p
                                    v-if="taxYears.length"
                                    class="mt-4 flex flex-wrap items-center gap-2 text-sm"
                                >
                                    <span class="text-muted-foreground"
                                        >Salary & income tax certificate:</span
                                    >
                                    <a
                                        v-for="year in taxYears"
                                        :key="year"
                                        :href="
                                            taxCertificate([
                                                slug,
                                                employee.id,
                                                year,
                                            ]).url
                                        "
                                        target="_blank"
                                        rel="noopener"
                                        class="font-medium hover:underline"
                                        >Tax year {{ year }}</a
                                    >
                                </p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="advances">
                        <Card>
                            <CardContent class="space-y-3 pt-6">
                                <p
                                    v-if="advances.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No advances or loans. An advance is
                                    recovered from the salary in monthly
                                    installments.
                                </p>
                                <ul
                                    v-else
                                    class="divide-y rounded-md border text-sm"
                                >
                                    <li
                                        v-for="advance in advances"
                                        :key="advance.id"
                                        class="flex items-start justify-between gap-3 p-3"
                                    >
                                        <div class="min-w-0">
                                            <p class="font-medium">
                                                {{ money(advance.amount) }}
                                                <span
                                                    class="font-normal text-muted-foreground"
                                                    >·
                                                    {{ formatDate(advance.date)
                                                    }}<template
                                                        v-if="advance.reason"
                                                    >
                                                        ·
                                                        {{
                                                            advance.reason
                                                        }}</template
                                                    ></span
                                                >
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ money(advance.installment) }}
                                                a month · recovered
                                                {{ money(advance.recovered) }}
                                                <template
                                                    v-if="
                                                        Number(
                                                            advance.scheduled,
                                                        )
                                                    "
                                                >
                                                    (+
                                                    {{
                                                        money(advance.scheduled)
                                                    }}
                                                    in a draft payroll)
                                                </template>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <Pill
                                                :color="
                                                    Number(advance.outstanding)
                                                        ? 'amber'
                                                        : 'green'
                                                "
                                            >
                                                {{
                                                    Number(advance.outstanding)
                                                        ? `${money(advance.outstanding)} left`
                                                        : 'Paid back'
                                                }}
                                            </Pill>
                                            <template v-if="canEdit('payroll')">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    title="Edit"
                                                    @click="
                                                        editingAdvance = advance
                                                    "
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    v-if="advance.can_delete"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    title="Delete"
                                                    @click="
                                                        advanceToDelete =
                                                            advance
                                                    "
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </template>
                                        </div>
                                    </li>
                                </ul>
                                <Button
                                    v-if="canEdit('payroll')"
                                    variant="outline"
                                    size="sm"
                                    @click="addingAdvance = true"
                                >
                                    <Plus /> Give an advance or loan
                                </Button>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="files">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="employee"
                                    :attachable-id="employee.id"
                                    :can-write="canEdit('payroll')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="employee"
                                    :notable-id="employee.id"
                                    :can-write="canEdit('payroll')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <AdvanceDialog
            v-model:open="addingAdvance"
            :employee-id="employee.id"
        />
        <AdvanceDialog
            v-model:open="editAdvanceOpen"
            :employee-id="employee.id"
            :advance="editingAdvance"
        />

        <ConfirmDialog
            :open="advanceToDelete !== null"
            title="Delete this advance?"
            description="Installments planned on draft payrolls are removed too."
            confirm-label="Delete"
            destructive
            @update:open="advanceToDelete = $event ? advanceToDelete : null"
            @confirm="removeAdvance"
        />

        <ConfirmDialog
            v-model:open="deleting"
            title="Remove this employee?"
            :description="`${employee.name} will be removed from the employees.`"
            confirm-label="Remove"
            destructive
            @confirm="router.delete(destroy([slug, employee.id]).url)"
        />
    </div>
</template>
