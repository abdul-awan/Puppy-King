<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import Field from '@/components/app/Field.vue';
import Pill from '@/components/app/Pill.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Heading from '@/components/Heading.vue';
import TemplateMiniature from '@/components/template-builder/TemplateMiniature.vue';
import type { Sample } from '@/components/template-builder/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as generalSettings } from '@/routes/settings/general';
import { edit, preview, update } from '@/routes/settings/invoice-settings';
import {
    create as createTemplate,
    destroy as destroyTemplate,
    edit as editTemplate,
} from '@/routes/settings/invoice-templates';
import type {
    DocumentKind,
    DocumentOptions,
    DocumentTemplateChoice,
    Option,
    Team,
} from '@/types';

const props = defineProps<{
    templates: DocumentTemplateChoice[];
    chosen: Record<DocumentKind, string>;
    options: DocumentOptions;
    choices: {
        types: Option<DocumentKind>[];
        tax_columns: Option<DocumentOptions['tax_columns']>[];
        tax_summaries: Option<DocumentOptions['tax_summary']>[];
        signatures: Option<DocumentOptions['signature']>[];
        date_formats: Option[];
        number_formats: Option<DocumentOptions['number_format']>[];
        currency_displays: Option<DocumentOptions['currency_display']>[];
        papers: Option<DocumentOptions['paper']>[];
    };
    salesTaxRegistered: boolean;
    sample: Sample;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: generalSettings(props.currentTeam.slug),
            },
            { title: 'Invoice settings', href: edit(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const canWrite = computed(() => canEdit('settings'));

const form = useForm({
    templates: { ...props.chosen },
    options: { ...props.options },
});

const builtIn = computed(() => props.templates.filter((t) => t.built_in));
const own = computed(() => props.templates.filter((t) => !t.built_in));

// What the options on this page switch off (also before saving), for the pictures of the templates.
const gates = computed(() => ({
    qr: form.options.qr_code,
    words: form.options.amount_in_words,
    payment: form.options.payment_details,
    terms: form.options.terms,
    stamp: form.options.status_stamp,
    signature: form.options.signature !== 'none',
    closing: Boolean(form.options.closing_note),
    footer_text: form.options.company_footer,
    footer: form.options.footer_bar,
}));

const usedFor = (key: string) =>
    props.choices.types.filter((type) => form.templates[type.value] === key);

const useForAll = (key: string) => {
    for (const type of props.choices.types) {
        form.templates[type.value] = key;
    }
};

// Open an example document as PDF with the settings on this page (saved or not).
const previewType = ref<DocumentKind>('invoice');

const previewUrl = (template: string, type: DocumentKind) => {
    const params = new URLSearchParams({ type, template });

    for (const [key, value] of Object.entries(form.options)) {
        params.append(
            `options[${key}]`,
            typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? ''),
        );
    }

    return `${preview(slug.value).url}?${params.toString()}`;
};

const openPreview = (template: string, type: DocumentKind) => {
    window.open(previewUrl(template, type), '_blank', 'noopener');
};

const submit = () => {
    form.put(update(slug.value).url, { preserveScroll: true });
};

// Deleting an own template.
const deleting = ref<DocumentTemplateChoice | null>(null);
const deleteOpen = ref(false);

const confirmDelete = (template: DocumentTemplateChoice) => {
    deleting.value = template;
    deleteOpen.value = true;
};

const destroy = () => {
    if (deleting.value?.id) {
        router.delete(destroyTemplate([slug.value, deleting.value.id]).url, {
            preserveScroll: true,
        });
    }
};

const lineColumns: {
    key: keyof DocumentOptions;
    label: string;
    hint?: string;
}[] = [
    { key: 'item_numbers', label: 'Line numbers (#)' },
    { key: 'quantity_price', label: 'Quantity and unit price' },
    { key: 'units', label: 'Units after the quantity (pcs, hours, …)' },
    { key: 'hs_codes', label: 'HS codes', hint: 'Only when a line has one' },
    {
        key: 'discount_column',
        label: 'Discount per line',
        hint: 'Only when a line has a discount',
    },
    { key: 'product_codes', label: 'Product codes before the description' },
];

const blocks: { key: keyof DocumentOptions; label: string; hint?: string }[] = [
    { key: 'company_contact', label: 'Your phone, e-mail and website' },
    { key: 'company_tax_numbers', label: 'Your NTN and STRN' },
    {
        key: 'customer_tax_numbers',
        label: 'The NTN, STRN and CNIC of the customer',
    },
    { key: 'amount_in_words', label: 'The total in words' },
    {
        key: 'payment_details',
        label: 'Bank details on invoices',
        hint: 'From the company profile',
    },
    {
        key: 'payment_summary',
        label: 'Paid, credited and balance due on invoices',
    },
    {
        key: 'status_stamp',
        label: 'Status stamp (Draft, Paid, Accepted, …)',
    },
    { key: 'terms', label: 'Terms & conditions of the document' },
    {
        key: 'qr_code',
        label: 'QR code to the online document',
        hint: 'Customers scan it to open the document on their phone',
    },
    {
        key: 'page_numbers',
        label: 'Page numbers',
        hint: 'On documents with more than one page',
    },
    {
        key: 'footer_bar',
        label: 'Footer line with your name and the document number',
    },
    {
        key: 'company_footer',
        label: 'The footer text of your company profile',
    },
];

const setOption = (key: keyof DocumentOptions, value: boolean) => {
    (form.options as Record<string, unknown>)[key] = value;
};

const errors = computed(() => form.errors as Record<string, string>);
</script>

<template>
    <Head title="Invoice settings" />

    <form class="space-y-10" @submit.prevent="submit">
        <!-- Templates -->
        <div class="space-y-6">
            <Heading
                variant="small"
                title="Templates"
                description="Choose how your quotations, invoices and credit notes look. Your logo, address and bank details come from the company profile."
            />

            <div class="grid gap-4 sm:grid-cols-3">
                <Field
                    v-for="type in choices.types"
                    :key="type.value"
                    :label="type.label"
                    :error="errors[`templates.${type.value}`]"
                >
                    <select
                        v-model="form.templates[type.value]"
                        :disabled="!canWrite"
                        class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30 [&>optgroup]:bg-background [&>option]:bg-background"
                    >
                        <optgroup label="Ready-made templates">
                            <option
                                v-for="template in builtIn"
                                :key="template.key"
                                :value="template.key"
                            >
                                {{ template.name }}
                            </option>
                        </optgroup>
                        <optgroup v-if="own.length" label="Your templates">
                            <option
                                v-for="template in own"
                                :key="template.key"
                                :value="template.key"
                            >
                                {{ template.name }}
                            </option>
                        </optgroup>
                    </select>
                </Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="template in templates"
                    :key="template.key"
                    class="flex flex-col gap-3 rounded-lg border p-3"
                    :class="{
                        'border-primary ring-1 ring-primary':
                            usedFor(template.key).length > 0,
                    }"
                >
                    <button
                        type="button"
                        class="mx-auto block cursor-zoom-in"
                        :title="`Preview ${template.name} as PDF`"
                        @click="openPreview(template.key, previewType)"
                    >
                        <TemplateMiniature
                            :definition="template.definition"
                            :sample="sample"
                            :gates="gates"
                        />
                    </button>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ template.name }}</p>
                            <Pill v-if="!template.built_in" color="indigo"
                                >Yours · from {{ template.layout_label }}</Pill
                            >
                        </div>
                        <p
                            v-if="template.description"
                            class="text-xs text-muted-foreground"
                        >
                            {{ template.description }}
                        </p>
                        <div
                            v-if="usedFor(template.key).length"
                            class="flex flex-wrap items-center gap-1 pt-1"
                        >
                            <Check class="size-3.5 text-primary" />
                            <span class="text-xs font-medium text-primary">
                                Used for
                                {{
                                    usedFor(template.key)
                                        .map((type) => type.label.toLowerCase())
                                        .join(', ')
                                }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openPreview(template.key, previewType)"
                        >
                            <Eye /> Preview
                        </Button>
                        <Button
                            v-if="canWrite"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="useForAll(template.key)"
                        >
                            Use for all
                        </Button>
                        <template v-if="canWrite">
                            <Button
                                v-if="template.built_in"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        createTemplate(slug, {
                                            query: { from: template.key },
                                        }).url
                                    "
                                    ><Copy /> Customise</Link
                                >
                            </Button>
                            <template v-else-if="template.id">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="
                                            editTemplate([slug, template.id])
                                                .url
                                        "
                                        ><Pencil /> Edit</Link
                                    >
                                </Button>
                                <Button variant="ghost" size="sm" as-child>
                                    <Link
                                        :href="
                                            createTemplate(slug, {
                                                query: { from: template.key },
                                            }).url
                                        "
                                        ><Copy /> Copy</Link
                                    >
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    @click="confirmDelete(template)"
                                >
                                    <Trash2 /> Delete
                                </Button>
                            </template>
                        </template>
                    </div>
                </div>

                <Link
                    v-if="canWrite"
                    :href="
                        createTemplate(slug, { query: { from: 'blank' } }).url
                    "
                    class="flex min-h-48 flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
                >
                    <Plus class="size-6" />
                    <span class="font-medium">Create your own template</span>
                    <span class="text-xs"
                        >Drag and drop elements onto the page and give each one
                        its own settings and style. Or press Customise on any
                        template above to start from it.</span
                    >
                </Link>
            </div>

            <div
                class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
            >
                <span>Previews show an example</span>
                <SelectInput
                    v-model="previewType"
                    :options="
                        choices.types.map((type) => ({
                            value: type.value,
                            label: type.label.replace(/s$/, '').toLowerCase(),
                        }))
                    "
                    class="h-8 w-auto"
                />
                <span
                    >with your company details and the options below, also
                    before you save them.</span
                >
            </div>
        </div>

        <!-- Tax -->
        <div class="space-y-4">
            <Heading
                variant="small"
                title="Tax"
                description="How sales tax appears on quotations, invoices and credit notes."
            />
            <label class="flex items-start gap-2 text-sm">
                <Checkbox
                    :model-value="form.options.show_tax"
                    :disabled="!canWrite"
                    class="mt-0.5"
                    @update:model-value="
                        form.options.show_tax = Boolean($event)
                    "
                />
                <span>
                    <span class="font-medium">Show tax on documents</span>
                    <span class="block text-muted-foreground">
                        When this is off, prices and totals include tax and no
                        tax lines are shown.
                    </span>
                </span>
            </label>

            <div v-if="form.options.show_tax" class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Tax on the lines"
                    :error="errors['options.tax_columns']"
                >
                    <SelectInput
                        v-model="form.options.tax_columns"
                        :options="choices.tax_columns"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Tax in the totals"
                    :error="errors['options.tax_summary']"
                >
                    <SelectInput
                        v-model="form.options.tax_summary"
                        :options="choices.tax_summaries"
                        :disabled="!canWrite"
                    />
                </Field>
            </div>
            <div
                v-else-if="salesTaxRegistered"
                class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                Your company profile has an STRN. A sales tax invoice has to
                show the sales tax (Sales Tax Act 1990, section 23). Without
                tax, your invoices are titled “Invoice” instead of “Sales Tax
                Invoice”. Switch tax off only if your customers do not need a
                sales tax invoice.
            </div>
        </div>

        <!-- Lines -->
        <div class="space-y-4">
            <Heading
                variant="small"
                title="Lines"
                description="The columns of the table with the items."
            />
            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    v-for="item in lineColumns"
                    :key="item.key"
                    class="flex items-start gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="Boolean(form.options[item.key])"
                        :disabled="!canWrite"
                        class="mt-0.5"
                        @update:model-value="
                            setOption(item.key, Boolean($event))
                        "
                    />
                    <span>
                        {{ item.label }}
                        <span
                            v-if="item.hint"
                            class="block text-xs text-muted-foreground"
                            >{{ item.hint }}</span
                        >
                    </span>
                </label>
            </div>
        </div>

        <!-- Blocks -->
        <div class="space-y-4">
            <Heading
                variant="small"
                title="Details and blocks"
                description="What else appears on the documents."
            />
            <div class="grid gap-3 sm:grid-cols-2">
                <label
                    v-for="item in blocks"
                    :key="item.key"
                    class="flex items-start gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="Boolean(form.options[item.key])"
                        :disabled="!canWrite"
                        class="mt-0.5"
                        @update:model-value="
                            setOption(item.key, Boolean($event))
                        "
                    />
                    <span>
                        {{ item.label }}
                        <span
                            v-if="item.hint"
                            class="block text-xs text-muted-foreground"
                            >{{ item.hint }}</span
                        >
                    </span>
                </label>
            </div>
            <Field
                label="Signature lines at the bottom"
                :error="errors['options.signature']"
                class="sm:max-w-sm"
            >
                <SelectInput
                    v-model="form.options.signature"
                    :options="choices.signatures"
                    :disabled="!canWrite"
                />
            </Field>
        </div>

        <!-- Texts -->
        <div class="space-y-4">
            <Heading
                variant="small"
                title="Titles and texts"
                description="Leave a title empty to use the automatic one."
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Title of invoices"
                    hint="Automatic: “Sales Tax Invoice” when you have an STRN, otherwise “Invoice”"
                    :error="errors['options.invoice_title']"
                >
                    <Input
                        v-model="form.options.invoice_title"
                        maxlength="40"
                        placeholder="e.g. Tax Invoice"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Title of quotations"
                    :error="errors['options.quotation_title']"
                >
                    <Input
                        v-model="form.options.quotation_title"
                        maxlength="40"
                        placeholder="Quotation"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Title of credit notes"
                    :error="errors['options.credit_note_title']"
                >
                    <Input
                        v-model="form.options.credit_note_title"
                        maxlength="40"
                        placeholder="Credit Note"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Heading above the customer"
                    hint="Automatic: “Bill to”, “Prepared for” or “Customer”"
                    :error="errors['options.customer_heading']"
                >
                    <Input
                        v-model="form.options.customer_heading"
                        maxlength="40"
                        placeholder="e.g. Invoice to"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Closing note"
                    hint="Printed at the end of every document"
                    :error="errors['options.closing_note']"
                    class="sm:col-span-2"
                >
                    <Textarea
                        v-model="form.options.closing_note"
                        rows="2"
                        maxlength="300"
                        placeholder="e.g. Thank you for your business!"
                        :disabled="!canWrite"
                    />
                </Field>
            </div>
        </div>

        <!-- Formats -->
        <div class="space-y-4">
            <Heading
                variant="small"
                title="Formats"
                description="How dates and amounts are written, and the paper size of the PDF."
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Dates" :error="errors['options.date_format']">
                    <SelectInput
                        v-model="form.options.date_format"
                        :options="choices.date_formats"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Amounts"
                    hint="Lakh and crore also write the total in words with lakh and crore"
                    :error="errors['options.number_format']"
                >
                    <SelectInput
                        v-model="form.options.number_format"
                        :options="choices.number_formats"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field
                    label="Currency"
                    :error="errors['options.currency_display']"
                >
                    <SelectInput
                        v-model="form.options.currency_display"
                        :options="choices.currency_displays"
                        :disabled="!canWrite"
                    />
                </Field>
                <Field label="Paper size" :error="errors['options.paper']">
                    <SelectInput
                        v-model="form.options.paper"
                        :options="choices.papers"
                        :disabled="!canWrite"
                    />
                </Field>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button v-if="canWrite" type="submit" :disabled="form.processing"
                >Save settings</Button
            >
            <Button
                type="button"
                variant="outline"
                @click="openPreview(form.templates[previewType], previewType)"
            >
                <Eye /> Preview {{ previewType.replace('_', ' ') }}
            </Button>
            <span v-if="form.isDirty" class="text-sm text-muted-foreground"
                >You have changes that are not saved yet.</span
            >
        </div>
    </form>

    <ConfirmDialog
        v-model:open="deleteOpen"
        :title="`Delete the template “${deleting?.name ?? ''}”?`"
        description="Documents that use it get the ready-made template of the same layout. Documents you already sent are not changed."
        confirm-label="Delete template"
        destructive
        @confirm="destroy"
    />
</template>
