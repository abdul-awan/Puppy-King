<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { round2 } from '@/lib/documents';
import { formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/employees';
import { edit as payrollSettings } from '@/routes/settings/payroll';
import type {
    Employee,
    IdName,
    Option,
    PayComponentKind,
    PayComponentOption,
} from '@/types';

export type EmployeePrefill = {
    joining_date: string;
    eobi: boolean;
    next_code: string;
};

const props = defineProps<{
    employee?: Employee | null;
    prefill?: EmployeePrefill;
    components: PayComponentOption[];
    employmentTypes: Option[];
    paymentMethods: Option[];
    taxModes: Option[];
    members: IdName[];
    departments: string[];
    designations: string[];
}>();

const { slug, currency } = useTeam();

// Pakistan's tax year runs from July to June and is named after the year it ends in.
function currentTaxYear(): number {
    const now = new Date();

    return now.getMonth() >= 6 ? now.getFullYear() + 1 : now.getFullYear();
}

const taxYears = [
    ...new Set([
        currentTaxYear(),
        currentTaxYear() - 1,
        ...(props.employee?.opening_tax_year
            ? [props.employee.opening_tax_year]
            : []),
    ]),
].map((year) => ({
    value: year,
    label: `Tax year ${year} (July ${year - 1} – June ${year})`,
}));

type Item = { pay_component_id: number | null; value: string | number };

const form = useForm({
    code: props.employee?.code ?? '',
    name: props.employee?.name ?? '',
    father_name: props.employee?.father_name ?? '',
    cnic: props.employee?.cnic ?? '',
    date_of_birth: props.employee?.date_of_birth ?? '',
    phone: props.employee?.phone ?? '',
    email: props.employee?.email ?? '',
    address: props.employee?.address ?? '',
    designation: props.employee?.designation ?? '',
    department: props.employee?.department ?? '',
    employment_type: props.employee?.employment_type ?? 'permanent',
    joining_date:
        props.employee?.joining_date ?? props.prefill?.joining_date ?? '',
    leaving_date: props.employee?.leaving_date ?? '',
    basic_salary: props.employee
        ? Number(props.employee.basic_salary)
        : ('' as string | number),
    items: (props.employee?.items ?? []).map((item): Item => ({
        pay_component_id: item.pay_component_id,
        value: Number(item.value),
    })),
    tax_mode: props.employee?.tax_mode ?? 'auto',
    fixed_tax: props.employee?.fixed_tax
        ? Number(props.employee.fixed_tax)
        : ('' as string | number),
    eobi: props.employee?.eobi ?? props.prefill?.eobi ?? false,
    eobi_number: props.employee?.eobi_number ?? '',
    opening_tax_year: props.employee?.opening_tax_year ?? currentTaxYear(),
    opening_taxable: props.employee?.opening_taxable
        ? Number(props.employee.opening_taxable)
        : ('' as string | number),
    opening_tax: props.employee?.opening_tax
        ? Number(props.employee.opening_tax)
        : ('' as string | number),
    payment_method: props.employee?.payment_method ?? 'bank_transfer',
    bank_name: props.employee?.bank_name ?? '',
    bank_account_title: props.employee?.bank_account_title ?? '',
    bank_account_number: props.employee?.bank_account_number ?? '',
    user_id: props.employee?.user_id ?? null,
    notes: props.employee?.notes ?? '',
});

const componentById = computed(
    () =>
        new Map(props.components.map((component) => [component.id, component])),
);

const kinds: PayComponentKind[] = ['earning', 'deduction', 'employer'];

const kindLabels: Record<PayComponentKind, string> = {
    earning: 'Allowances',
    deduction: 'Deductions',
    employer: 'Paid by the employer',
};

// Inactive components stay available on salaries that already have them.
const choices = (current: number | null) =>
    props.components.filter(
        (component) => component.is_active || component.id === current,
    );

const addItem = () => {
    form.items.push({ pay_component_id: null, value: '' });
};

const onSelect = (item: Item, event: Event) => {
    const value = (event.target as HTMLSelectElement).value;

    onComponent(item, value ? Number(value) : null);
};

const onComponent = (item: Item, id: number | null) => {
    item.pay_component_id = id;

    const component = id !== null ? componentById.value.get(id) : undefined;

    if (component && (item.value === '' || Number(item.value) === 0)) {
        item.value = Number(component.default_value) || '';
    }
};

const isPercent = (item: Item) => {
    const component =
        item.pay_component_id !== null
            ? componentById.value.get(item.pay_component_id)
            : undefined;

    return component !== undefined && component.amount_type !== 'fixed';
};

// A full month with the salary as entered (the payroll works out the exact amounts and the tax).
const totals = computed(() => {
    const basic = Number(form.basic_salary || 0);
    let gross = basic;
    let deductions = 0;
    let employer = 0;

    const amount = (item: Item, base: number) => {
        const component =
            item.pay_component_id !== null
                ? componentById.value.get(item.pay_component_id)
                : undefined;
        const value = Number(item.value || 0);

        if (!component) {
            return { kind: null, value: 0 };
        }

        return {
            kind: component.kind,
            value:
                component.amount_type === 'fixed'
                    ? value
                    : component.amount_type === 'percent_basic'
                      ? (basic * value) / 100
                      : (base * value) / 100,
        };
    };

    for (const item of form.items) {
        const result = amount(item, basic);

        if (result.kind === 'earning') {
            gross += result.value;
        }
    }

    for (const item of form.items) {
        const result = amount(item, gross);

        if (result.kind === 'deduction') {
            deductions += result.value;
        } else if (result.kind === 'employer') {
            employer += result.value;
        }
    }

    return {
        gross: round2(gross),
        deductions: round2(deductions),
        employer: round2(employer),
    };
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        date_of_birth: data.date_of_birth || null,
        leaving_date: data.leaving_date || null,
        fixed_tax: data.tax_mode === 'fixed' ? data.fixed_tax : null,
        items: data.items.filter((item) => item.pay_component_id !== null),
    }));

    if (props.employee) {
        form.put(update([slug.value, props.employee.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.employee
    ? show([slug.value, props.employee.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Personal details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Field label="Full name" :error="form.errors.name" required>
                    <Input v-model="form.name" required maxlength="150" />
                </Field>
                <Field
                    label="Father's / husband's name"
                    :error="form.errors.father_name"
                >
                    <Input v-model="form.father_name" maxlength="150" />
                </Field>
                <Field
                    label="CNIC"
                    hint="13 digits, e.g. 35202-1234567-1"
                    :error="form.errors.cnic"
                >
                    <Input
                        v-model="form.cnic"
                        inputmode="numeric"
                        placeholder="35202-1234567-1"
                    />
                </Field>
                <Field label="Date of birth" :error="form.errors.date_of_birth">
                    <Input v-model="form.date_of_birth" type="date" />
                </Field>
                <Field label="Mobile / phone" :error="form.errors.phone">
                    <Input v-model="form.phone" type="tel" maxlength="30" />
                </Field>
                <Field
                    label="E-mail"
                    hint="Payslips can be e-mailed to this address"
                    :error="form.errors.email"
                >
                    <Input v-model="form.email" type="email" />
                </Field>
                <Field
                    label="Address"
                    :error="form.errors.address"
                    class="sm:col-span-2 lg:col-span-3"
                >
                    <Textarea v-model="form.address" rows="2" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Job</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Field
                    label="Employee code"
                    :hint="
                        employee
                            ? undefined
                            : `Leave empty to use ${prefill?.next_code ?? 'the next number'}`
                    "
                    :error="form.errors.code"
                >
                    <Input
                        v-model="form.code"
                        maxlength="30"
                        :placeholder="prefill?.next_code"
                    />
                </Field>
                <Field label="Designation" :error="form.errors.designation">
                    <Input
                        v-model="form.designation"
                        list="employee-designations"
                        maxlength="100"
                        placeholder="e.g. Sales officer"
                    />
                    <datalist id="employee-designations">
                        <option
                            v-for="designation in designations"
                            :key="designation"
                            :value="designation"
                        />
                    </datalist>
                </Field>
                <Field label="Department" :error="form.errors.department">
                    <Input
                        v-model="form.department"
                        list="employee-departments"
                        maxlength="100"
                        placeholder="e.g. Sales"
                    />
                    <datalist id="employee-departments">
                        <option
                            v-for="department in departments"
                            :key="department"
                            :value="department"
                        />
                    </datalist>
                </Field>
                <Field label="Employment" :error="form.errors.employment_type">
                    <SelectInput
                        v-model="form.employment_type"
                        :options="employmentTypes"
                    />
                </Field>
                <Field
                    label="Joining date"
                    :error="form.errors.joining_date"
                    required
                >
                    <Input v-model="form.joining_date" type="date" required />
                </Field>
                <Field
                    label="Leaving date"
                    hint="Paid up to this day; left off later payrolls"
                    :error="form.errors.leaving_date"
                >
                    <Input v-model="form.leaving_date" type="date" />
                </Field>
                <Field
                    label="Login (member of the company)"
                    hint="Lets the employee see their own payslips"
                    :error="form.errors.user_id"
                    class="sm:col-span-2"
                >
                    <SelectInput
                        v-model="form.user_id"
                        :options="idOptions(members)"
                        placeholder="No login"
                    />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Salary</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Field
                        :label="`Basic salary a month (${currency})`"
                        :error="form.errors.basic_salary"
                        required
                    >
                        <Input
                            v-model="form.basic_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                    </Field>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">
                        Allowances, deductions and employer contributions
                    </p>
                    <p
                        v-if="form.items.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Only the basic salary. Add house rent, medical,
                        conveyance… or a provident fund.
                    </p>
                    <div
                        v-for="(item, position) in form.items"
                        :key="position"
                        class="grid items-start gap-2 sm:grid-cols-[1fr_12rem_auto]"
                    >
                        <div>
                            <select
                                :value="item.pay_component_id ?? ''"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs dark:bg-input/30 [&>option]:bg-background"
                                :aria-label="`Item ${position + 1}`"
                                @change="onSelect(item, $event)"
                            >
                                <option value="">Choose…</option>
                                <optgroup
                                    v-for="kind in kinds"
                                    :key="kind"
                                    :label="kindLabels[kind]"
                                >
                                    <option
                                        v-for="component in choices(
                                            item.pay_component_id,
                                        ).filter((c) => c.kind === kind)"
                                        :key="component.id"
                                        :value="component.id"
                                    >
                                        {{ component.name
                                        }}{{
                                            component.tax_label &&
                                            component.tax_label !== 'Taxable'
                                                ? ` (${component.tax_label})`
                                                : ''
                                        }}
                                    </option>
                                </optgroup>
                            </select>
                            <p
                                v-if="
                                    form.errors[
                                        `items.${position}.pay_component_id`
                                    ]
                                "
                                class="mt-1 text-sm text-destructive"
                            >
                                {{
                                    form.errors[
                                        `items.${position}.pay_component_id`
                                    ]
                                }}
                            </p>
                        </div>
                        <div>
                            <div class="relative">
                                <Input
                                    v-model="item.value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :aria-label="
                                        isPercent(item)
                                            ? 'Percentage'
                                            : 'Amount'
                                    "
                                    :class="isPercent(item) ? 'pr-8' : ''"
                                />
                                <span
                                    v-if="isPercent(item)"
                                    class="absolute top-2 right-3 text-sm text-muted-foreground"
                                    >%</span
                                >
                            </div>
                            <p
                                v-if="form.errors[`items.${position}.value`]"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ form.errors[`items.${position}.value`] }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove"
                            @click="form.items.splice(position, 1)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addItem"
                        >
                            <Plus /> Add allowance or deduction
                        </Button>
                        <Link
                            :href="payrollSettings(slug).url"
                            class="text-xs text-muted-foreground hover:underline"
                        >
                            Manage the list in Company settings → Payroll
                        </Link>
                    </div>
                </div>

                <dl
                    class="ml-auto w-full max-w-sm space-y-1 rounded-md bg-muted/50 p-3 text-sm"
                >
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Gross salary a month
                        </dt>
                        <dd class="font-semibold">
                            {{ formatMoney(totals.gross, currency) }}
                        </dd>
                    </div>
                    <div v-if="totals.deductions" class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Deductions (before tax and EOBI)
                        </dt>
                        <dd>{{ formatMoney(totals.deductions, currency) }}</dd>
                    </div>
                    <div v-if="totals.employer" class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Paid by the employer
                        </dt>
                        <dd>{{ formatMoney(totals.employer, currency) }}</dd>
                    </div>
                </dl>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Income tax" :error="form.errors.tax_mode">
                        <SelectInput
                            v-model="form.tax_mode"
                            :options="taxModes"
                        />
                    </Field>
                    <Field
                        v-if="form.tax_mode === 'fixed'"
                        :label="`Income tax a month (${currency})`"
                        :error="form.errors.fixed_tax"
                        required
                    >
                        <Input
                            v-model="form.fixed_tax"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                    </Field>
                    <Field label="EOBI no." :error="form.errors.eobi_number">
                        <Input v-model="form.eobi_number" maxlength="30" />
                    </Field>
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        :model-value="form.eobi"
                        class="mt-0.5"
                        @update:model-value="form.eobi = Boolean($event)"
                    />
                    <span>
                        Pays EOBI contributions (the employee's share is
                        deducted, the employer's share is a cost on top)
                    </span>
                </label>

                <div
                    v-if="form.tax_mode === 'auto'"
                    class="space-y-3 rounded-md border p-3"
                >
                    <p class="text-sm font-medium">
                        Salary and tax before this payroll
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Started using this payroll during the tax year, or did
                        the employee come from another employer? Fill in the
                        taxable salary already paid and the income tax already
                        deducted in this tax year, so the tax of the remaining
                        months is right.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Tax year"
                            :error="form.errors.opening_tax_year"
                        >
                            <SelectInput
                                v-model="form.opening_tax_year"
                                :options="taxYears"
                            />
                        </Field>
                        <Field
                            :label="`Taxable salary paid (${currency})`"
                            :error="form.errors.opening_taxable"
                        >
                            <Input
                                v-model="form.opening_taxable"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </Field>
                        <Field
                            :label="`Income tax deducted (${currency})`"
                            :error="form.errors.opening_tax"
                        >
                            <Input
                                v-model="form.opening_tax"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </Field>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Payment</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field
                    label="Salary paid by"
                    :error="form.errors.payment_method"
                >
                    <SelectInput
                        v-model="form.payment_method"
                        :options="paymentMethods"
                    />
                </Field>
                <template v-if="form.payment_method !== 'cash'">
                    <Field label="Bank" :error="form.errors.bank_name">
                        <Input
                            v-model="form.bank_name"
                            maxlength="100"
                            placeholder="e.g. Meezan Bank"
                        />
                    </Field>
                    <Field
                        label="Account title"
                        :error="form.errors.bank_account_title"
                    >
                        <Input
                            v-model="form.bank_account_title"
                            maxlength="150"
                        />
                    </Field>
                    <Field
                        label="Account no. / IBAN"
                        :error="form.errors.bank_account_number"
                    >
                        <Input
                            v-model="form.bank_account_number"
                            maxlength="50"
                            placeholder="PK36MEZN…"
                        />
                    </Field>
                </template>
                <Field
                    label="Notes"
                    :error="form.errors.notes"
                    class="sm:col-span-2 lg:col-span-4"
                >
                    <Textarea v-model="form.notes" rows="2" />
                </Field>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ employee ? 'Save changes' : 'Add employee' }}
            </Button>
        </div>
    </form>
</template>
