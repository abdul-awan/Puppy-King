<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Check,
    ChevronDown,
    Download,
    FileText,
    Lock,
    LockOpen,
    Mail,
    MoreHorizontal,
    Pencil,
    RefreshCw,
    Trash2,
    TriangleAlert,
    Undo2,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import Field from '@/components/app/Field.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Timeline from '@/components/app/Timeline.vue';
import InputError from '@/components/InputError.vue';
import PayDialog from '@/components/payroll/PayDialog.vue';
import PayslipDialog from '@/components/payroll/PayslipDialog.vue';
import ReportFigure from '@/components/reports/ReportFigure.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatMoney } from '@/lib/format';
import { edit as editEmployee, show as showEmployee } from '@/routes/employees';
import {
    approve,
    bankTransfers,
    destroy,
    email,
    index,
    pay,
    pdf,
    recalculate,
    register,
    reopen,
    show,
    unpay,
    update,
} from '@/routes/payroll';
import { store as addEmployeeRoute } from '@/routes/payroll/employees';
import {
    destroy as destroyPayslip,
    email as emailPayslip,
    pay as payPayslip,
    pdf as payslipPdf,
    unpay as unpayPayslip,
} from '@/routes/payslips';
import { edit as payrollSettings } from '@/routes/settings/payroll';
import type {
    IdName,
    OpenAdvance,
    PayrollRun,
    Payslip,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    run: PayrollRun;
    payslips: Payslip[];
    advances: Record<string, OpenAdvance[]>;
    missingEmployees: IdName[];
    earlierDrafts: string[];
    missingHistory: { employee_id: number; name: string; since: string }[];
    taxTable: { tax_year: number; table_year: number | null };
    componentNames: { earning: string[]; deduction: string[] };
    timeline: TimelineItem[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; run: PayrollRun }) => ({
        breadcrumbs: [
            { title: 'Payroll', href: index(props.currentTeam.slug) },
            {
                title: props.run.label,
                href: show([props.currentTeam.slug, props.run.id]),
            },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit, canView } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const money = (value: string | number) => formatMoney(value, currency.value);
const days = (value: string) => String(Number(value));

const isDraft = computed(() => props.run.status === 'draft');
const anyPaid = computed(() =>
    props.payslips.some((payslip) => payslip.paid_on),
);
const allPaid = computed(
    () =>
        props.payslips.length > 0 &&
        props.payslips.every((payslip) => payslip.paid_on),
);
const toEmail = computed(
    () =>
        props.payslips.filter(
            (payslip) => payslip.employee_email && !payslip.emailed_at,
        ).length,
);

const editing = ref<Payslip | null>(null);
const editOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editing.value = null;
        }
    },
});

const payingAll = ref(false);
const payingOne = ref<Payslip | null>(null);
const payOneOpen = computed({
    get: () => payingOne.value !== null,
    set: (open: boolean) => {
        if (!open) {
            payingOne.value = null;
        }
    },
});

const approving = ref(false);
const deleting = ref(false);
const removing = ref<Payslip | null>(null);

const post = (url: string) => router.post(url, {}, { preserveScroll: true });

const remove = (url: string) => router.delete(url, { preserveScroll: true });

const otherDeductions = (payslip: Payslip) =>
    Number(payslip.deductions) - Number(payslip.income_tax);

const addForm = useForm({ employee_id: null as number | null });
const addEmployee = () => {
    addForm.post(addEmployeeRoute([slug.value, props.run.id]).url, {
        preserveScroll: true,
        onSuccess: () => addForm.reset(),
    });
};

const runForm = useForm({
    pay_date: props.run.pay_date ?? '',
    notes: props.run.notes ?? '',
});
const saveRun = () => {
    runForm
        .transform((data) => ({ ...data, pay_date: data.pay_date || null }))
        .patch(update([slug.value, props.run.id]).url, {
            preserveScroll: true,
        });
};
</script>

<template>
    <Head :title="`Payroll ${run.label}`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`Payroll ${run.label}`"
            :description="`${run.employees_count} payslips${run.pay_date ? ` · pay date ${formatDate(run.pay_date)}` : ''}`"
        >
            <template #badges>
                <Pill :color="run.status_color">{{ run.status_label }}</Pill>
            </template>
            <template #actions>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm">
                            <Download /> Download <ChevronDown />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem as-child>
                            <a
                                :href="pdf([slug, run.id]).url"
                                target="_blank"
                                rel="noopener"
                                ><FileText /> All payslips (PDF)</a
                            >
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="register([slug, run.id]).url"
                                ><Download /> Payroll register (Excel)</a
                            >
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="bankTransfers([slug, run.id]).url"
                                ><Banknote /> Bank transfers (Excel)</a
                            >
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <template v-if="canEdit('payroll')">
                    <template v-if="isDraft">
                        <Button
                            variant="outline"
                            size="sm"
                            title="Delete the draft payroll"
                            @click="deleting = true"
                        >
                            <Trash2 />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="post(recalculate([slug, run.id]).url)"
                        >
                            <RefreshCw /> Work out again
                        </Button>
                        <Button size="sm" @click="approving = true">
                            <Lock /> Approve
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            v-if="toEmail > 0"
                            variant="outline"
                            size="sm"
                            @click="post(email([slug, run.id]).url)"
                        >
                            <Mail /> E-mail payslips ({{ toEmail }})
                        </Button>
                        <Button
                            v-if="run.status === 'approved' && !anyPaid"
                            variant="outline"
                            size="sm"
                            @click="post(reopen([slug, run.id]).url)"
                        >
                            <LockOpen /> Change again
                        </Button>
                        <Button
                            v-if="anyPaid"
                            variant="outline"
                            size="sm"
                            @click="remove(unpay([slug, run.id]).url)"
                        >
                            <Undo2 /> Undo payments
                        </Button>
                        <Button
                            v-if="!allPaid"
                            size="sm"
                            @click="payingAll = true"
                        >
                            <Check /> Mark as paid
                        </Button>
                    </template>
                </template>
            </template>
        </PageHeader>

        <InputError :message="errors.run" />

        <div
            v-if="isDraft && earlierDrafts.length"
            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <p>
                The payroll of {{ earlierDrafts.join(', ') }} is still a draft.
                Approve it first: the income tax of this month builds on the
                earlier months of the tax year.
            </p>
        </div>

        <div
            v-if="isDraft && missingHistory.length"
            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <div class="space-y-1">
                <p>
                    No salary is recorded for the earlier months of tax year
                    {{ run.tax_year }} for:
                    <template
                        v-for="(employee, position) in missingHistory"
                        :key="employee.employee_id"
                    >
                        <Link
                            :href="
                                editEmployee([slug, employee.employee_id]).url
                            "
                            class="font-medium underline"
                            >{{ employee.name }}</Link
                        >
                        <span class="text-muted-foreground">
                            (since {{ employee.since }})</span
                        >{{ position < missingHistory.length - 1 ? ', ' : '.' }}
                    </template>
                </p>
                <p class="text-muted-foreground">
                    If they were paid before you started using this payroll,
                    fill in that salary and the tax deducted on the employee
                    (Salary and tax before this payroll), then work the payslips
                    out again. Otherwise their income tax is too low.
                </p>
            </div>
        </div>

        <div
            v-if="isDraft && taxTable.table_year !== taxTable.tax_year"
            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
            <p>
                <template v-if="taxTable.table_year === null"
                    >There are no income tax rates for salaries yet.</template
                >
                <template v-else
                    >The income tax uses the rates of tax year
                    {{ taxTable.table_year }}: the rates of tax year
                    {{ taxTable.tax_year }} are not set up yet.</template
                >
                <Link
                    v-if="canView('settings')"
                    :href="payrollSettings(slug).url"
                    class="font-medium underline"
                    >Add them in Company settings → Payroll</Link
                >, then work the payslips out again.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <ReportFigure label="Gross pay" :value="money(run.gross)" />
            <ReportFigure
                label="Income tax"
                :value="money(run.income_tax)"
                hint="Deducted from the salaries"
            />
            <ReportFigure
                label="Net pay"
                :value="money(run.net)"
                :hint="`Total deductions ${money(run.deductions)}`"
            />
            <ReportFigure
                label="Cost to the company"
                :value="money(Number(run.gross) + Number(run.employer_cost))"
                :hint="`With ${money(run.employer_cost)} employer contributions`"
            />
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Employee</TableHead>
                        <TableHead class="hidden text-right md:table-cell"
                            >Days paid</TableHead
                        >
                        <TableHead class="text-right">Gross</TableHead>
                        <TableHead class="hidden text-right sm:table-cell"
                            >Income tax</TableHead
                        >
                        <TableHead class="hidden text-right lg:table-cell"
                            >Other deductions</TableHead
                        >
                        <TableHead class="text-right">Net pay</TableHead>
                        <TableHead class="w-24" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="payslips.length === 0">
                        <TableCell
                            colspan="7"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            Nobody is on this payroll.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="payslip in payslips" :key="payslip.id">
                        <TableCell>
                            <Link
                                :href="
                                    showEmployee([slug, payslip.employee_id])
                                        .url
                                "
                                class="font-medium hover:underline"
                            >
                                {{ payslip.employee_name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ payslip.number ?? payslip.employee_code }}
                                <template v-if="payslip.designation">
                                    · {{ payslip.designation }}
                                </template>
                            </p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Pill v-if="payslip.paid_on" color="green"
                                    >Paid
                                    {{ formatDate(payslip.paid_on) }}</Pill
                                >
                                <Pill v-if="payslip.emailed_at" color="gray"
                                    >E-mailed</Pill
                                >
                                <Pill v-if="Number(payslip.net) < 0" color="red"
                                    >Net below zero</Pill
                                >
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden text-right text-sm md:table-cell"
                        >
                            {{ days(payslip.payable_days) }} /
                            {{ payslip.days_in_period }}
                        </TableCell>
                        <TableCell class="text-right text-sm tabular-nums">{{
                            money(payslip.gross)
                        }}</TableCell>
                        <TableCell
                            class="hidden text-right text-sm tabular-nums sm:table-cell"
                            >{{ money(payslip.income_tax) }}</TableCell
                        >
                        <TableCell
                            class="hidden text-right text-sm tabular-nums lg:table-cell"
                            >{{ money(otherDeductions(payslip)) }}</TableCell
                        >
                        <TableCell
                            class="text-right text-sm font-semibold tabular-nums"
                            >{{ money(payslip.net) }}</TableCell
                        >
                        <TableCell class="text-right">
                            <Button
                                v-if="isDraft && canEdit('payroll')"
                                variant="ghost"
                                size="icon-sm"
                                title="Change the payslip"
                                @click="editing = payslip"
                            >
                                <Pencil />
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        title="More"
                                    >
                                        <MoreHorizontal />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <a
                                            :href="
                                                payslipPdf([slug, payslip.id])
                                                    .url
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            ><FileText /> Payslip (PDF)</a
                                        >
                                    </DropdownMenuItem>
                                    <template
                                        v-if="!isDraft && canEdit('payroll')"
                                    >
                                        <DropdownMenuItem
                                            v-if="payslip.employee_email"
                                            @select="
                                                post(
                                                    emailPayslip([
                                                        slug,
                                                        payslip.id,
                                                    ]).url,
                                                )
                                            "
                                        >
                                            <Mail />
                                            {{
                                                payslip.emailed_at
                                                    ? 'E-mail again'
                                                    : 'E-mail to the employee'
                                            }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="!payslip.paid_on"
                                            @select="payingOne = payslip"
                                        >
                                            <Check /> Mark as paid
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-else
                                            @select="
                                                remove(
                                                    unpayPayslip([
                                                        slug,
                                                        payslip.id,
                                                    ]).url,
                                                )
                                            "
                                        >
                                            <Undo2 /> Undo payment
                                        </DropdownMenuItem>
                                    </template>
                                    <template
                                        v-if="isDraft && canEdit('payroll')"
                                    >
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="text-destructive"
                                            @select="removing = payslip"
                                        >
                                            <Trash2 /> Take off this payroll
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6">
                <Card
                    v-if="
                        isDraft && canEdit('payroll') && missingEmployees.length
                    "
                >
                    <CardHeader>
                        <CardTitle>Add an employee</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            Employees who work in {{ run.label }} but are not on
                            this payroll.
                        </p>
                        <SelectInput
                            v-model="addForm.employee_id"
                            :options="
                                missingEmployees.map((employee) => ({
                                    value: employee.id,
                                    label: employee.name,
                                }))
                            "
                            placeholder="Choose…"
                        />
                        <InputError :message="addForm.errors.employee_id" />
                        <Button
                            size="sm"
                            :disabled="
                                !addForm.employee_id || addForm.processing
                            "
                            @click="addEmployee"
                        >
                            <UserPlus /> Add to the payroll
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pay date and notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-3" @submit.prevent="saveRun">
                            <Field
                                label="Pay date"
                                :error="runForm.errors.pay_date"
                            >
                                <Input
                                    v-model="runForm.pay_date"
                                    type="date"
                                    :disabled="!canEdit('payroll')"
                                />
                            </Field>
                            <Field label="Notes" :error="runForm.errors.notes">
                                <Textarea
                                    v-model="runForm.notes"
                                    rows="3"
                                    :disabled="!canEdit('payroll')"
                                />
                            </Field>
                            <Button
                                v-if="canEdit('payroll')"
                                type="submit"
                                size="sm"
                                variant="outline"
                                :disabled="
                                    runForm.processing || !runForm.isDirty
                                "
                            >
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>History</CardTitle>
                </CardHeader>
                <CardContent>
                    <Timeline
                        :items="timeline"
                        notable-type="payroll_run"
                        :notable-id="run.id"
                        :can-write="canEdit('payroll')"
                    />
                </CardContent>
            </Card>
        </div>

        <PayslipDialog
            v-model:open="editOpen"
            :payslip="editing"
            :advances="editing ? (advances[editing.employee_id] ?? []) : []"
            :component-names="componentNames"
        />

        <PayDialog
            v-model:open="payingAll"
            title="Mark the salaries as paid"
            :description="`Every payslip of ${run.label} that is not paid yet.`"
            :url="pay([slug, run.id]).url"
            :date="run.pay_date"
        />
        <PayDialog
            v-model:open="payOneOpen"
            title="Mark the salary as paid"
            :description="payingOne?.employee_name ?? ''"
            :url="payingOne ? payPayslip([slug, payingOne.id]).url : ''"
            :date="run.pay_date"
        />

        <ConfirmDialog
            v-model:open="approving"
            :title="`Approve the payroll of ${run.label}?`"
            description="The payslips are numbered and locked. You can still make the payroll a draft again until a salary is paid."
            confirm-label="Approve"
            @confirm="post(approve([slug, run.id]).url)"
        />

        <ConfirmDialog
            v-model:open="deleting"
            :title="`Delete the payroll of ${run.label}?`"
            description="The draft payslips are deleted. Advance installments on them are not recovered."
            confirm-label="Delete"
            destructive
            @confirm="router.delete(destroy([slug, run.id]).url)"
        />

        <ConfirmDialog
            :open="removing !== null"
            title="Take this employee off the payroll?"
            :description="removing?.employee_name ?? ''"
            confirm-label="Take off"
            destructive
            @update:open="removing = $event ? removing : null"
            @confirm="
                removing && remove(destroyPayslip([slug, removing.id]).url)
            "
        />
    </div>
</template>
