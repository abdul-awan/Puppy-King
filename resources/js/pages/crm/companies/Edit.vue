<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import CompanyForm from '@/components/crm/CompanyForm.vue';
import { edit, index, show } from '@/routes/companies';
import type {
    AddressType,
    Company,
    CustomFieldDefinition,
    IdName,
    Option,
    PaymentTermOption,
    Tag,
    Team,
} from '@/types';

defineProps<{
    company: Company;
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
    layout: (props: { currentTeam: Team; company: Company }) => ({
        breadcrumbs: [
            { title: 'Companies', href: index(props.currentTeam.slug) },
            {
                title: props.company.name,
                href: show([props.currentTeam.slug, props.company.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.company.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${company.name}`" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit ${company.name}`" />
        <CompanyForm v-bind="$props" />
    </div>
</template>
