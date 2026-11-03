<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import AddressesEditor from '@/components/app/AddressesEditor.vue';
import CustomFieldInputs from '@/components/app/CustomFieldInputs.vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import TagPicker from '@/components/app/TagPicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/contacts';
import type {
    Address,
    AddressType,
    Contact,
    CustomFieldDefinition,
    IdName,
    Option,
    Tag,
} from '@/types';

type CompanyLink = {
    company_id: number | null;
    company_name: string;
    position: string;
    is_primary: boolean;
    is_decision_maker: boolean;
};

const props = defineProps<{
    contact?: Contact | null;
    company?: IdName | null;
    provinces: Option[];
    addressTypes: Option<AddressType>[];
    users: IdName[];
    tags: Tag[];
    customFields: CustomFieldDefinition[];
}>();

const { slug } = useTeam();

const salutations: Option[] = ['Mr', 'Mrs', 'Ms', 'Dr', 'Prof', 'Engr'].map(
    (value) => ({ value, label: value }),
);

const initialCompanies = (): CompanyLink[] => {
    if (props.contact?.companies) {
        return props.contact.companies.map((company) => ({
            company_id: company.id,
            company_name: company.name,
            position: company.position ?? '',
            is_primary: company.is_primary,
            is_decision_maker: company.is_decision_maker,
        }));
    }

    return props.company
        ? [
              {
                  company_id: props.company.id,
                  company_name: props.company.name,
                  position: '',
                  is_primary: true,
                  is_decision_maker: false,
              },
          ]
        : [];
};

const form = useForm({
    salutation: props.contact?.salutation ?? null,
    first_name: props.contact?.first_name ?? '',
    last_name: props.contact?.last_name ?? '',
    email: props.contact?.email ?? '',
    phone: props.contact?.phone ?? '',
    mobile: props.contact?.mobile ?? '',
    cnic: props.contact?.cnic ?? '',
    birthdate: props.contact?.birthdate ?? '',
    language: props.contact?.language ?? 'en',
    responsible_user_id: props.contact?.responsible_user_id ?? null,
    marketing_consent: props.contact?.marketing_consent ?? false,
    remarks: props.contact?.remarks ?? '',
    tag_ids: props.contact?.tags?.map((tag) => tag.id) ?? [],
    companies: initialCompanies(),
    addresses: (props.contact?.addresses?.map((address) => ({
        type: address.type,
        line_1: address.line_1 ?? '',
        line_2: address.line_2 ?? '',
        city: address.city ?? '',
        province: address.province,
        postal_code: address.postal_code ?? '',
        country: address.country ?? 'Pakistan',
    })) ?? []) as Address[],
    custom_fields: { ...props.contact?.custom_fields },
});

const addCompany = () => {
    form.companies.push({
        company_id: null,
        company_name: '',
        position: '',
        is_primary: form.companies.length === 0,
        is_decision_maker: false,
    });
};

const removeCompany = (position: number) => {
    form.companies.splice(position, 1);
};

const submit = () => {
    const options = { preserveScroll: true };

    form.transform((data) => ({
        ...data,
        companies: data.companies
            .filter((link) => link.company_id !== null)
            .map(({ company_name: _name, ...link }) => link),
    }));

    if (props.contact) {
        form.put(update([slug.value, props.contact.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};

const cancelUrl = props.contact
    ? show([slug.value, props.contact.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Person</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-6">
                <Field
                    label="Title"
                    :error="form.errors.salutation"
                    class="sm:col-span-1"
                >
                    <SelectInput
                        v-model="form.salutation"
                        :options="salutations"
                        placeholder="—"
                    />
                </Field>
                <Field
                    label="First name"
                    :error="form.errors.first_name"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.first_name" v-focus />
                </Field>
                <Field
                    label="Last name"
                    required
                    :error="form.errors.last_name"
                    class="sm:col-span-3"
                >
                    <Input v-model="form.last_name" required />
                </Field>
                <Field
                    label="E-mail"
                    :error="form.errors.email"
                    class="sm:col-span-3"
                >
                    <Input v-model="form.email" type="email" />
                </Field>
                <Field
                    label="Mobile"
                    :error="form.errors.mobile"
                    class="sm:col-span-3"
                >
                    <Input
                        v-model="form.mobile"
                        type="tel"
                        placeholder="03xx-xxxxxxx"
                    />
                </Field>
                <Field
                    label="Phone"
                    :error="form.errors.phone"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.phone" type="tel" />
                </Field>
                <Field
                    label="CNIC"
                    :error="form.errors.cnic"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.cnic" placeholder="12345-1234567-1" />
                </Field>
                <Field
                    label="Date of birth"
                    :error="form.errors.birthdate"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.birthdate" type="date" />
                </Field>
                <Field
                    label="Account manager"
                    :error="form.errors.responsible_user_id"
                    class="sm:col-span-3"
                >
                    <SelectInput
                        v-model="form.responsible_user_id"
                        :options="idOptions(users)"
                        placeholder="—"
                    />
                </Field>
                <label
                    class="flex items-center gap-2 text-sm sm:col-span-3 sm:pt-6"
                >
                    <Checkbox
                        :model-value="form.marketing_consent"
                        @update:model-value="
                            form.marketing_consent = Boolean($event)
                        "
                    />
                    Agreed to receive marketing messages
                </label>
                <Field label="Tags" class="sm:col-span-6">
                    <TagPicker v-model="form.tag_ids" :options="tags" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Works at</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="(link, position) in form.companies"
                    :key="position"
                    class="grid items-start gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_12rem_auto]"
                >
                    <Field
                        :error="form.errors[`companies.${position}.company_id`]"
                    >
                        <EntitySelect
                            v-model="link.company_id"
                            type="companies"
                            :initial-label="link.company_name"
                            placeholder="Choose a company…"
                            @select="link.company_name = $event?.label ?? ''"
                        />
                    </Field>
                    <Input
                        v-model="link.position"
                        placeholder="Position, e.g. Accounts manager"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Remove"
                        @click="removeCompany(position)"
                    >
                        <Trash2 />
                    </Button>
                    <div class="flex flex-wrap gap-4 text-sm sm:col-span-3">
                        <label class="flex items-center gap-2">
                            <Checkbox
                                :model-value="link.is_primary"
                                @update:model-value="
                                    link.is_primary = Boolean($event)
                                "
                            />
                            Main contact for this company
                        </label>
                        <label class="flex items-center gap-2">
                            <Checkbox
                                :model-value="link.is_decision_maker"
                                @update:model-value="
                                    link.is_decision_maker = Boolean($event)
                                "
                            />
                            Decision maker
                        </label>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addCompany"
                >
                    <Plus /> Link to a company
                </Button>
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
                    <Textarea v-model="form.remarks" rows="4" />
                </Field>
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ contact ? 'Save changes' : 'Create contact' }}
            </Button>
        </div>
    </form>
</template>
