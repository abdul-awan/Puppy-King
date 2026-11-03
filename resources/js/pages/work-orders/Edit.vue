<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import WorkOrderForm from '@/components/work-orders/WorkOrderForm.vue';
import { edit, index, show } from '@/routes/work-orders';
import type { IdName, Team, WorkOrder } from '@/types';

defineProps<{
    workOrder: WorkOrder;
    users: IdName[];
    departments: IdName[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; workOrder: WorkOrder }) => ({
        breadcrumbs: [
            { title: 'Work orders', href: index(props.currentTeam.slug) },
            {
                title: props.workOrder.number ?? 'Work order',
                href: show([props.currentTeam.slug, props.workOrder.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.workOrder.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${workOrder.number}`" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit work order ${workOrder.number}`" />
        <WorkOrderForm v-bind="$props" />
    </div>
</template>
