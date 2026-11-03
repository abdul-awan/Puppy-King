<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Pill from '@/components/app/Pill.vue';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { destroy, index, store, update } from '@/routes/settings/payment-terms';
import type { Team } from '@/types';

defineProps<{
    paymentTerms: {
        id: number;
        name: string;
        days: number;
        end_of_month: boolean;
        is_default: boolean;
        sort: number;
    }[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Payment terms', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const fields: ResourceField[] = [
    {
        key: 'name',
        label: 'Name',
        type: 'text',
        required: true,
        wide: true,
        placeholder: 'e.g. 30 days',
    },
    { key: 'days', label: 'Days', type: 'number', required: true },
    { key: 'sort', label: 'Order', type: 'number' },
    {
        key: 'end_of_month',
        label: 'Count the days from the end of the invoice month',
        type: 'checkbox',
    },
    { key: 'is_default', label: 'Default for new invoices', type: 'checkbox' },
];

const explain = (item: Record<string, unknown>): string => {
    const days = Number(item.days);

    if (item.end_of_month) {
        return days === 0
            ? 'End of the invoice month'
            : `${days} days after the end of the invoice month`;
    }

    return days === 0
        ? 'On the invoice date'
        : `${days} days after the invoice date`;
};
</script>

<template>
    <Head title="Payment terms" />

    <ResourceList
        title="Payment terms"
        description="When invoices are due. A company can have its own default term."
        item-label="payment term"
        :items="paymentTerms"
        :columns="[
            { key: 'name', label: 'Name' },
            { key: 'due', label: 'Due' },
            { key: 'status', label: '' },
        ]"
        :fields="fields"
        :defaults="{
            name: '',
            days: 30,
            end_of_month: false,
            is_default: false,
            sort: null,
        }"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
        :can-write="canEdit('settings')"
    >
        <template #cell-due="{ item }">
            <span class="text-muted-foreground">{{ explain(item) }}</span>
        </template>
        <template #cell-status="{ item }">
            <div class="flex justify-end">
                <Pill v-if="item.is_default" color="green">Default</Pill>
            </div>
        </template>
    </ResourceList>
</template>
