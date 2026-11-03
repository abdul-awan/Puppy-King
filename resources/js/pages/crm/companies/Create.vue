<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import CompanyForm from '@/components/crm/CompanyForm.vue';
import { create, index } from '@/routes/companies';
import type {
    AddressType,
    CustomFieldDefinition,
    IdName,
    Option,
    PaymentTermOption,
    Tag,
    Team,
} from '@/types';

defineProps<{
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

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Companies', href: index(props.currentTeam.slug) },
            { title: 'New company', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New company" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader title="New company" />
        <CompanyForm v-bind="$props" />
    </div>
</template>
