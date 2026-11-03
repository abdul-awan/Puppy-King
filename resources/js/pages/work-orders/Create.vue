<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import WorkOrderForm from '@/components/work-orders/WorkOrderForm.vue';
import type { WorkOrderPrefill } from '@/components/work-orders/WorkOrderForm.vue';
import { create, index } from '@/routes/work-orders';
import type { IdName, Team } from '@/types';

defineProps<{
    prefill: WorkOrderPrefill;
    users: IdName[];
    departments: IdName[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Work orders', href: index(props.currentTeam.slug) },
            { title: 'New work order', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New work order" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="New work order"
            description="Write down the job; add hours, materials and the customer's signature on the next page."
        />
        <WorkOrderForm v-bind="$props" />
    </div>
</template>
