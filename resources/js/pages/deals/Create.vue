<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import DealForm from '@/components/deals/DealForm.vue';
import { create, index } from '@/routes/deals';
import type {
    CustomFieldDefinition,
    IdName,
    Option,
    PipelineOption,
    Tag,
    Team,
} from '@/types';

defineProps<{
    prefill: {
        company: IdName | null;
        contact: IdName | null;
        pipeline_id: number | null;
    };
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
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Deals', href: index(props.currentTeam.slug) },
            { title: 'New deal', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New deal" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader title="New deal" />
        <DealForm v-bind="$props" />
    </div>
</template>
