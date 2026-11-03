<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import Field from '@/components/app/Field.vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TaxTableDialog from '@/components/payroll/TaxTableDialog.vue';
import type { TaxTable } from '@/components/payroll/TaxTableDialog.vue';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatMoney } from '@/lib/format';
import { edit as settingsHome } from '@/routes/settings/general';
import {
    destroy as destroyComponent,
    store as storeComponent,
    update as updateComponent,
} from '@/routes/settings/pay-components';
import { edit, update } from '@/routes/settings/payroll';
import { destroy as destroyTable } from '@/routes/settings/salary-tax-tables';
import type { Option, Team } from '@/types';

type Component = {
    id: number;
    name: string;
    kind: string;
    kind_label: string;
    amount_type: string;
    amount_type_label: string;
    default_value: string;
    taxable: boolean;
    tax_free_percent: string | null;
    tax_label: string | null;
    is_active: boolean;
    sort: number;
    employees: number;
};

const props = defineProps<{
    settings: {
        days_basis: string;
        whole_rupees: boolean;
        pay_day: number;
        eobi_default: boolean;
        eobi_wage_base: string;
        eobi_employer_percent: string;
        eobi_employee_percent: string;
        eobi_employer_share: string;
        eobi_employee_share: string;
        show_employer_contributions: boolean;
        payslip_note: string;
    };
    daysBases: Option[];
    components: Component[];
    taxTables: TaxTable[];
    currentTaxYear: number;
    kinds: Option[];
    amountTypes: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Payroll', href: edit(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const money = (value: string | number) => formatMoney(value, currency.value);

const form = useForm({
    days_basis: props.settings.days_basis,
    whole_rupees: props.settings.whole_rupees,
    pay_day: props.settings.pay_day,
    eobi_default: props.settings.eobi_default,
    eobi_wage_base: Number(props.settings.eobi_wage_base),
    eobi_employer_percent: Number(props.settings.eobi_employer_percent),
    eobi_employee_percent: Number(props.settings.eobi_employee_percent),
    show_employer_contributions: props.settings.show_employer_contributions,
    payslip_note: props.settings.payslip_note,
});

const payDays = [
    { value: 0, label: 'Last day of the month' },
    ...Array.from({ length: 28 }, (_, day) => ({
        value: day + 1,
        label: `Day ${day + 1} of the next month`,
    })),
];

const eobiShare = (percent: number) =>
    money((Number(form.eobi_wage_base || 0) * Number(percent || 0)) / 100);

const save = () => {
    form.put(update(slug.value).url, { preserveScroll: true });
};

const componentFields: ResourceField[] = [
    { key: 'name', label: 'Name', type: 'text', required: true, wide: true },
    {
        key: 'kind',
        label: 'Kind',
        type: 'select',
        options: props.kinds,
        required: true,
    },
    {
        key: 'amount_type',
        label: 'Worked out as',
        type: 'select',
        options: props.amountTypes,
        required: true,
    },
    {
        key: 'default_value',
        label: 'Default amount or %',
        type: 'number',
        step: '0.01',
        hint: 'Filled in when it is put on a salary',
    },
    { key: 'sort', label: 'Order', type: 'number' },
    {
        key: 'taxable',
        label: 'Taxable (counts as taxable salary)',
        type: 'checkbox',
        visible: (data) => data.kind === 'earning',
    },
    {
        key: 'tax_free_percent',
        label: 'Tax-free up to this % of the basic salary',
        type: 'number',
        step: '0.01',
        hint: 'E.g. 10 for the medical allowance; empty when fully taxable',
        visible: (data) => data.kind === 'earning' && Boolean(data.taxable),
    },
    { key: 'is_active', label: 'Can be put on salaries', type: 'checkbox' },
];

const latest = computed(() => props.taxTables[0] ?? null);
const hasCurrentYear = computed(() =>
    props.taxTables.some((table) => table.tax_year === props.currentTaxYear),
);
const nextYear = computed(() =>
    hasCurrentYear.value
        ? Math.max(...props.taxTables.map((table) => table.tax_year)) + 1
        : props.currentTaxYear,
);

const adding = ref(false);
const editing = ref<TaxTable | null>(null);
const editOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editing.value = null;
        }
    },
});

const removeTable = (table: TaxTable) => {
    if (window.confirm(`Delete the tax rates of tax year ${table.tax_year}?`)) {
        router.delete(destroyTable([slug.value, table.id]).url, {
            preserveScroll: true,
        });
    }
};

const plain = (value: string) =>
    new Intl.NumberFormat('en-US').format(Number(value));
</script>

<template>
    <Head title="Payroll settings" />

    <div class="space-y-10">
        <section class="space-y-6">
            <Heading
                variant="small"
                title="Payroll"
                description="How salaries are worked out: part months, rounding, the pay day, EOBI and the payslip."
            />

            <form class="space-y-6" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Salary for part of a month"
                        hint="Joining or leaving during the month, and unpaid days"
                        :error="form.errors.days_basis"
                    >
                        <SelectInput
                            v-model="form.days_basis"
                            :options="daysBases"
                            :disabled="!canEdit('settings')"
                        />
                    </Field>
                    <Field label="Pay day" :error="form.errors.pay_day">
                        <SelectInput
                            v-model="form.pay_day"
                            :options="payDays"
                            :disabled="!canEdit('settings')"
                        />
                    </Field>
                </div>

                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.whole_rupees"
                            :disabled="!canEdit('settings')"
                            @update:model-value="
                                form.whole_rupees = Boolean($event)
                            "
                        />
                        Round the worked out amounts (part months, percentages,
                        tax) to whole rupees
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.show_employer_contributions"
                            :disabled="!canEdit('settings')"
                            @update:model-value="
                                form.show_employer_contributions =
                                    Boolean($event)
                            "
                        />
                        Show what the employer pays on top (EOBI, provident
                        fund…) on payslips
                    </label>
                </div>

                <div class="space-y-3 rounded-lg border p-4">
                    <p class="text-sm font-medium">EOBI</p>
                    <p class="text-xs text-muted-foreground">
                        Contributions are a percentage of the minimum wage set
                        by the government (Rs 37,000 a month when this app was
                        set up). Update the wage when the government changes it.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Minimum wage a month"
                            :error="form.errors.eobi_wage_base"
                        >
                            <Input
                                v-model="form.eobi_wage_base"
                                type="number"
                                step="0.01"
                                min="0"
                                :disabled="!canEdit('settings')"
                            />
                        </Field>
                        <Field
                            label="Employer %"
                            :hint="`${eobiShare(form.eobi_employer_percent)} a month`"
                            :error="form.errors.eobi_employer_percent"
                        >
                            <Input
                                v-model="form.eobi_employer_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :disabled="!canEdit('settings')"
                            />
                        </Field>
                        <Field
                            label="Employee %"
                            :hint="`${eobiShare(form.eobi_employee_percent)} a month`"
                            :error="form.errors.eobi_employee_percent"
                        >
                            <Input
                                v-model="form.eobi_employee_percent"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :disabled="!canEdit('settings')"
                            />
                        </Field>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.eobi_default"
                            :disabled="!canEdit('settings')"
                            @update:model-value="
                                form.eobi_default = Boolean($event)
                            "
                        />
                        New employees pay EOBI contributions
                    </label>
                </div>

                <Field
                    label="Note at the bottom of payslips"
                    :error="form.errors.payslip_note"
                >
                    <Textarea
                        v-model="form.payslip_note"
                        rows="2"
                        maxlength="500"
                        placeholder="E.g. Salaries are confidential. Questions? Contact HR."
                        :disabled="!canEdit('settings')"
                    />
                </Field>

                <Button
                    v-if="canEdit('settings')"
                    type="submit"
                    :disabled="form.processing"
                >
                    Save payroll settings
                </Button>
            </form>
        </section>

        <section class="space-y-3">
            <InputError :message="errors.component" />
            <ResourceList
                title="Allowances, deductions and employer contributions"
                description="What can be put on salaries. Allowances are taxable unless you say otherwise."
                item-label="pay component"
                :items="components"
                :columns="[
                    { key: 'name', label: 'Name' },
                    { key: 'kind_label', label: 'Kind' },
                    { key: 'amount', label: 'Worked out as' },
                    {
                        key: 'tax_label',
                        label: 'Income tax',
                        class: 'hidden md:table-cell',
                    },
                    {
                        key: 'employees',
                        label: 'On salaries',
                        class: 'hidden sm:table-cell text-right',
                    },
                ]"
                :fields="componentFields"
                :defaults="{
                    name: '',
                    kind: 'earning',
                    amount_type: 'fixed',
                    default_value: null,
                    taxable: true,
                    tax_free_percent: null,
                    is_active: true,
                    sort: null,
                }"
                :store-url="storeComponent(slug).url"
                :update-url="(item) => updateComponent([slug, item.id]).url"
                :destroy-url="(item) => destroyComponent([slug, item.id]).url"
                :can-write="canEdit('settings')"
            >
                <template #cell-name="{ item }">
                    <span class="font-medium">{{ item.name }}</span>
                    <Pill v-if="!item.is_active" color="gray" class="ml-2"
                        >Off</Pill
                    >
                </template>
                <template #cell-amount="{ item }">
                    {{
                        item.amount_type === 'fixed'
                            ? Number(item.default_value)
                                ? money(item.default_value as string)
                                : 'Fixed amount'
                            : `${Number(item.default_value)}% of ${item.amount_type === 'percent_basic' ? 'basic' : 'gross'}`
                    }}
                </template>
                <template #cell-tax_label="{ item }">
                    {{ item.tax_label ?? '—' }}
                </template>
            </ResourceList>
        </section>

        <section class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <Heading
                    variant="small"
                    title="Income tax on salaries"
                    description="The slabs for salaried individuals of each tax year (July to June). They change with every budget: check them against the Finance Act."
                />
                <Button
                    v-if="canEdit('settings')"
                    size="sm"
                    @click="adding = true"
                >
                    <Plus /> Add tax year {{ nextYear }}
                </Button>
            </div>

            <div
                v-if="!hasCurrentYear"
                class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/40"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-600" />
                <p>
                    Tax year {{ currentTaxYear }} (July
                    {{ currentTaxYear - 1 }} – June {{ currentTaxYear }}) has no
                    rates yet<template v-if="latest"
                        >, so payslips use the rates of tax year
                        {{ latest.tax_year }}</template
                    >. Add the slabs of the latest Finance Act; the dialog
                    starts with the previous year's slabs to change.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div
                    v-for="table in taxTables"
                    :key="table.id"
                    class="space-y-3 rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ table.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ table.name }}
                            </p>
                        </div>
                        <div v-if="canEdit('settings')" class="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Edit"
                                @click="editing = table"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Delete"
                                @click="removeTable(table)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="pb-1 font-normal">
                                    Yearly income over
                                </th>
                                <th class="pb-1 text-right font-normal">Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="slab in table.slabs"
                                :key="slab.from"
                                class="border-t"
                            >
                                <td class="py-1 tabular-nums">
                                    {{ plain(slab.from) }}
                                </td>
                                <td class="py-1 text-right tabular-nums">
                                    <template v-if="Number(slab.fixed)">
                                        {{ plain(slab.fixed) }} +
                                    </template>
                                    {{ Number(slab.rate) }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="
                            table.surcharge_threshold &&
                            Number(table.surcharge_percent)
                        "
                        class="text-xs text-muted-foreground"
                    >
                        Surcharge {{ Number(table.surcharge_percent) }}% of the
                        tax when the income is over
                        {{ plain(table.surcharge_threshold) }}.
                    </p>
                </div>
            </div>

            <p
                v-if="taxTables.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No tax rates yet: no income tax is deducted from salaries.
            </p>
        </section>

        <TaxTableDialog
            v-model:open="adding"
            :copy-from="latest"
            :year="nextYear"
        />
        <TaxTableDialog
            v-model:open="editOpen"
            :table="editing"
            :year="editing?.tax_year ?? nextYear"
        />
    </div>
</template>
