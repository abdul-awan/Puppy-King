<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import DealForm from '@/components/deals/DealForm.vue';
import { edit, index, show } from '@/routes/deals';
import type {
    CustomFieldDefinition,
    Deal,
    IdName,
    Option,
    PipelineOption,
    Tag,
    Team,
} from '@/types';

defineProps<{
    deal: Deal;
    pipelines: PipelineOption[];
    sources: IdName[];
    users: IdName[];
    departments: IdName[];
    currencies: Option[];
    defaultCurrency: string;
    tags: Tag[];
    customFields: CustomFieldDefinition[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; deal: Deal }) => ({
        breadcrumbs: [
            { title: 'Deals', href: index(props.currentTeam.slug) },
            {
                title: props.deal.title,
                href: show([props.currentTeam.slug, props.deal.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.deal.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${deal.title}`" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit ${deal.title}`" />
        <DealForm v-bind="$props" />
    </div>
</template>
