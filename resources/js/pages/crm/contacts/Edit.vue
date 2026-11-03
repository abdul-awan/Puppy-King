<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ContactForm from '@/components/crm/ContactForm.vue';
import { edit, index, show } from '@/routes/contacts';
import type {
    AddressType,
    Contact,
    CustomFieldDefinition,
    IdName,
    Option,
    Tag,
    Team,
} from '@/types';

defineProps<{
    contact: Contact;
    provinces: Option[];
    addressTypes: Option<AddressType>[];
    users: IdName[];
    tags: Tag[];
    customFields: CustomFieldDefinition[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; contact: Contact }) => ({
        breadcrumbs: [
            { title: 'Contacts', href: index(props.currentTeam.slug) },
            {
                title: props.contact.full_name,
                href: show([props.currentTeam.slug, props.contact.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.contact.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${contact.full_name}`" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit ${contact.full_name}`" />
        <ContactForm v-bind="$props" />
    </div>
</template>
