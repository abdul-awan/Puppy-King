<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AddressesEditor from '@/components/app/AddressesEditor.vue';
import CustomFieldInputs from '@/components/app/CustomFieldInputs.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import TagPicker from '@/components/app/TagPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/companies';
import type {
    Address,
    AddressType,
    Company,
    CustomFieldDefinition,
    IdName,
    Option,
    PaymentTermOption,
    Tag,
} from '@/types';

const props = defineProps<{
    company?: Company | null;
    relationships: Option[];
    businessTypes: Option[];
    provinces: Option[];
    addressTypes: Option<AddressType>[];
    currencies: Option[];
    users: IdName[];
    paymentTerms: PaymentTermOption[];
    tags: Tag[];
    customFields: CustomFieldDefinition[];
}>();

const { slug } = useTeam();

const emptyAddress = (): Address => ({
    type: 'primary',
    line_1: '',
    line_2: '',
    city: '',
    province: null,
    postal_code: '',
    country: 'Pakistan',
});

const form = useForm({
    name: props.company?.name ?? '',
    relationship: props.company?.relationship ?? 'customer',
    business_type: props.company?.business_type ?? null,
    ntn: props.company?.ntn ?? '',
    strn: props.company?.strn ?? '',
    cnic: props.company?.cnic ?? '',
    sales_tax_registered: props.company?.sales_tax_registered ?? false,
    email: props.company?.email ?? '',
    phone: props.company?.phone ?? '',
    mobile: props.company?.mobile ?? '',
    website: props.company?.website ?? '',
    industry: props.company?.industry ?? '',
    responsible_user_id: props.company?.responsible_user_id ?? null,
    payment_term_id: props.company?.payment_term_id ?? null,
    currency: props.company?.currency ?? null,
    language: props.company?.language ?? 'en',
    iban: props.company?.iban ?? '',
    remarks: props.company?.remarks ?? '',
    tag_ids: props.company?.tags?.map((tag) => tag.id) ?? [],
    addresses: (props.company?.addresses?.map((address) => ({
        type: address.type,
        line_1: address.line_1 ?? '',
        line_2: address.line_2 ?? '',
        city: address.city ?? '',
        province: address.province,
        postal_code: address.postal_code ?? '',
        country: address.country ?? 'Pakistan',
    })) ?? [emptyAddress()]) as Address[],
    custom_fields: { ...props.company?.custom_fields },
});

// Entering an STRN usually means the buyer is registered for sales tax.
watch(
    () => form.strn,
    (value, previous) => {
        if (value && !previous) {
            form.sales_tax_registered = true;
        }
    },
);

const submit = () => {
    if (props.company) {
        form.put(update([slug.value, props.company.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.company
    ? show([slug.value, props.company.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>General</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Company name"
                    id="name"
                    required
                    :error="form.errors.name"
                    class="sm:col-span-2"
                >
                    <Input id="name" v-model="form.name" v-focus required />
                </Field>
                <Field label="Type" :error="form.errors.relationship">
                    <SelectInput
                        v-model="form.relationship"
                        :options="relationships"
                    />
                </Field>
                <Field label="Business type" :error="form.errors.business_type">
                    <SelectInput
                        v-model="form.business_type"
                        :options="businessTypes"
                        placeholder="—"
                    />
                </Field>
                <Field label="Industry" :error="form.errors.industry">
                    <Input
                        v-model="form.industry"
                        placeholder="e.g. Textiles"
                    />
                </Field>
                <Field
                    label="Account manager"
                    :error="form.errors.responsible_user_id"
                >
                    <SelectInput
                        v-model="form.responsible_user_id"
                        :options="idOptions(users)"
                        placeholder="—"
                    />
                </Field>
                <Field label="Tags" class="sm:col-span-2">
                    <TagPicker v-model="form.tag_ids" :options="tags" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Contact details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <Field label="E-mail" :error="form.errors.email">
                    <Input v-model="form.email" type="email" />
                </Field>
                <Field label="Website" :error="form.errors.website">
                    <Input
                        v-model="form.website"
                        placeholder="www.example.pk"
                    />
                </Field>
                <Field label="Phone" :error="form.errors.phone">
                    <Input
                        v-model="form.phone"
                        type="tel"
                        placeholder="042-35xxxxxx"
                    />
                </Field>
                <Field label="Mobile" :error="form.errors.mobile">
                    <Input
                        v-model="form.mobile"
                        type="tel"
                        placeholder="03xx-xxxxxxx"
                    />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Tax registration</CardTitle>
                <CardDescription>
                    Printed on quotations and invoices. Sales to buyers who are
                    not registered for sales tax attract further tax.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <Field
                    label="NTN"
                    :error="form.errors.ntn"
                    hint="National Tax Number"
                >
                    <Input v-model="form.ntn" placeholder="1234567-8" />
                </Field>
                <Field
                    label="STRN"
                    :error="form.errors.strn"
                    hint="Sales Tax Registration No."
                >
                    <Input v-model="form.strn" placeholder="13 digits" />
                </Field>
                <Field
                    label="CNIC"
                    :error="form.errors.cnic"
                    hint="For sole proprietors"
                >
                    <Input v-model="form.cnic" placeholder="12345-1234567-1" />
                </Field>
                <label class="flex items-center gap-2 text-sm sm:col-span-3">
                    <Checkbox
                        :model-value="form.sales_tax_registered"
                        @update:model-value="
                            form.sales_tax_registered = Boolean($event)
                        "
                    />
                    Registered for sales tax (active taxpayer)
                </label>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Addresses</CardTitle>
            </CardHeader>
            <CardContent>
                <AddressesEditor
                    v-model="form.addresses"
                    :provinces="provinces"
                    :address-types="addressTypes"
                    :errors="form.errors"
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Invoicing</CardTitle>
                <CardDescription>
                    Defaults used when you create quotations and invoices for
                    this company.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Payment term"
                    :error="form.errors.payment_term_id"
                >
                    <SelectInput
                        v-model="form.payment_term_id"
                        :options="idOptions(paymentTerms)"
                        placeholder="Team default"
                    />
                </Field>
                <Field label="Currency" :error="form.errors.currency">
                    <SelectInput
                        v-model="form.currency"
                        :options="currencies"
                        placeholder="Team default"
                    />
                </Field>
                <Field label="IBAN" :error="form.errors.iban">
                    <Input
                        v-model="form.iban"
                        placeholder="PK36SCBL0000001123456702"
                    />
                </Field>
            </CardContent>
        </Card>

        <Card v-if="customFields.length > 0">
            <CardHeader>
                <CardTitle>Additional information</CardTitle>
            </CardHeader>
            <CardContent>
                <CustomFieldInputs
                    v-model="form.custom_fields"
                    :fields="customFields"
                    :errors="form.errors"
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Remarks</CardTitle>
            </CardHeader>
            <CardContent>
                <Field :error="form.errors.remarks">
                    <Textarea
                        v-model="form.remarks"
                        rows="4"
                        placeholder="Internal remarks about this company"
                    />
                </Field>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ company ? 'Save changes' : 'Create company' }}
            </Button>
        </div>
    </form>
</template>
