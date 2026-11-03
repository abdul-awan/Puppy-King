<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ContactForm from '@/components/crm/ContactForm.vue';
import { create, index } from '@/routes/contacts';
import type {
    AddressType,
    CustomFieldDefinition,
    IdName,
    Option,
    Tag,
    Team,
} from '@/types';

defineProps<{
    company: IdName | null;
    provinces: Option[];
    addressTypes: Option<AddressType>[];
    users: IdName[];
    tags: Tag[];
    customFields: CustomFieldDefinition[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Contacts', href: index(props.currentTeam.slug) },
            { title: 'New contact', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New contact" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader title="New contact" />
        <ContactForm v-bind="$props" />
    </div>
</template>
