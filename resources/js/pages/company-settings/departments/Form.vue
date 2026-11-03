<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building } from '@lucide/vue';
import { ref } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { index, store, update } from '@/routes/settings/departments';
import type { Option, Team } from '@/types';

type Department = {
    id: number;
    name: string;
    legal_name: string | null;
    ntn: string | null;
    strn: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    country: string | null;
    bank_name: string | null;
    bank_account_title: string | null;
    bank_account_number: string | null;
    iban: string | null;
    document_footer: string | null;
    is_default: boolean;
    logo_url: string | null;
};

const props = defineProps<{
    department: Department | null;
    provinces: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; department: Department | null }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Company profile', href: index(props.currentTeam.slug) },
            {
                title: props.department ? props.department.name : 'New profile',
                href: index(props.currentTeam.slug),
            },
        ],
    }),
});

const { slug } = useTeam();
const logoPreview = ref<string | null>(props.department?.logo_url ?? null);

const form = useForm({
    name: props.department?.name ?? '',
    legal_name: props.department?.legal_name ?? '',
    ntn: props.department?.ntn ?? '',
    strn: props.department?.strn ?? '',
    email: props.department?.email ?? '',
    phone: props.department?.phone ?? '',
    website: props.department?.website ?? '',
    address_line_1: props.department?.address_line_1 ?? '',
    address_line_2: props.department?.address_line_2 ?? '',
    city: props.department?.city ?? '',
    province: props.department?.province ?? null,
    postal_code: props.department?.postal_code ?? '',
    country: props.department?.country ?? 'Pakistan',
    bank_name: props.department?.bank_name ?? '',
    bank_account_title: props.department?.bank_account_title ?? '',
    bank_account_number: props.department?.bank_account_number ?? '',
    iban: props.department?.iban ?? '',
    document_footer: props.department?.document_footer ?? '',
    is_default: props.department?.is_default ?? false,
    logo: null as File | null,
    remove_logo: false,
});

const pickLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    form.logo = file;
    form.remove_logo = false;
    logoPreview.value = file ? URL.createObjectURL(file) : logoPreview.value;
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
};

const submit = () => {
    if (props.department) {
        // Files require a multipart POST; Laravel reads the real method from _method.
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            update([slug.value, props.department.id]).url,
            { forceFormData: true, preserveScroll: true },
        );
    } else {
        form.post(store(slug.value).url, {
            forceFormData: true,
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head
        :title="department ? `Edit ${department.name}` : 'New company profile'"
    />

    <form class="space-y-10" @submit.prevent="submit">
        <div class="space-y-6">
            <Heading
                variant="small"
                :title="
                    department
                        ? `Edit ${department.name}`
                        : 'New company profile'
                "
                description="These details are printed on your quotations, invoices and credit notes."
            />

            <div class="flex items-center gap-4">
                <div
                    class="flex size-20 items-center justify-center overflow-hidden rounded-md border bg-muted"
                >
                    <img
                        v-if="logoPreview"
                        :src="logoPreview"
                        alt="Logo"
                        class="max-h-full max-w-full object-contain"
                    />
                    <Building v-else class="size-8 text-muted-foreground" />
                </div>
                <div class="space-y-2">
                    <input
                        type="file"
                        accept="image/png,image/jpeg"
                        class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                        @change="pickLogo"
                    />
                    <div
                        class="flex items-center gap-3 text-xs text-muted-foreground"
                    >
                        PNG or JPG, max 2 MB.
                        <button
                            v-if="logoPreview"
                            type="button"
                            class="text-destructive hover:underline"
                            @click="removeLogo"
                        >
                            Remove logo
                        </button>
                    </div>
                    <p v-if="form.errors.logo" class="text-sm text-destructive">
                        {{ form.errors.logo }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    required
                    :error="form.errors.name"
                    hint="Short name, e.g. Head office"
                >
                    <Input v-model="form.name" required />
                </Field>
                <Field
                    label="Legal name"
                    :error="form.errors.legal_name"
                    hint="As registered, e.g. Acme Traders (Pvt) Ltd"
                >
                    <Input v-model="form.legal_name" />
                </Field>
                <Field label="NTN" :error="form.errors.ntn">
                    <Input v-model="form.ntn" placeholder="1234567-8" />
                </Field>
                <Field label="STRN" :error="form.errors.strn">
                    <Input
                        v-model="form.strn"
                        placeholder="Sales tax registration number"
                    />
                </Field>
                <Field label="E-mail" :error="form.errors.email">
                    <Input v-model="form.email" type="email" />
                </Field>
                <Field label="Phone" :error="form.errors.phone">
                    <Input v-model="form.phone" />
                </Field>
                <Field
                    label="Website"
                    :error="form.errors.website"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.website" />
                </Field>
            </div>
        </div>

        <div class="space-y-6">
            <Heading variant="small" title="Address" />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Address line 1"
                    :error="form.errors.address_line_1"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.address_line_1" />
                </Field>
                <Field
                    label="Address line 2"
                    :error="form.errors.address_line_2"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.address_line_2" />
                </Field>
                <Field label="City" :error="form.errors.city">
                    <Input v-model="form.city" />
                </Field>
                <Field label="Province" :error="form.errors.province">
                    <SelectInput
                        v-model="form.province"
                        :options="provinces"
                        placeholder="—"
                    />
                </Field>
                <Field label="Postal code" :error="form.errors.postal_code">
                    <Input v-model="form.postal_code" />
                </Field>
                <Field label="Country" :error="form.errors.country">
                    <Input v-model="form.country" />
                </Field>
            </div>
        </div>

        <div class="space-y-6">
            <Heading
                variant="small"
                title="Bank details"
                description="Shown on invoices so customers know where to pay."
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="Bank" :error="form.errors.bank_name">
                    <Input
                        v-model="form.bank_name"
                        placeholder="e.g. Meezan Bank"
                    />
                </Field>
                <Field
                    label="Account title"
                    :error="form.errors.bank_account_title"
                >
                    <Input v-model="form.bank_account_title" />
                </Field>
                <Field
                    label="Account number"
                    :error="form.errors.bank_account_number"
                >
                    <Input v-model="form.bank_account_number" />
                </Field>
                <Field label="IBAN" :error="form.errors.iban">
                    <Input
                        v-model="form.iban"
                        placeholder="PK36MEZN0000001123456702"
                    />
                </Field>
            </div>
        </div>

        <div class="space-y-6">
            <Heading variant="small" title="Document footer" />
            <Field :error="form.errors.document_footer">
                <Textarea
                    v-model="form.document_footer"
                    rows="3"
                    placeholder="e.g. Thank you for your business. Goods once sold are not returnable."
                />
            </Field>
            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    :model-value="form.is_default"
                    @update:model-value="form.is_default = Boolean($event)"
                />
                Use this profile by default on new documents
            </label>
        </div>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">Save</Button>
            <Button variant="ghost" as-child>
                <Link :href="index(slug).url">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
