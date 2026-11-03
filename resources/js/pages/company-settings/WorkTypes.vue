<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Pill from '@/components/app/Pill.vue';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatMoney } from '@/lib/format';
import { edit as settingsHome } from '@/routes/settings/general';
import { destroy, index, store, update } from '@/routes/settings/work-types';
import type { Team } from '@/types';

defineProps<{
    workTypes: {
        id: number;
        name: string;
        hourly_rate: string | null;
        is_billable: boolean;
        is_active: boolean;
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
            { title: 'Work types', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const fields: ResourceField[] = [
    {
        key: 'name',
        label: 'Name',
        type: 'text',
        required: true,
        wide: true,
        placeholder: 'e.g. Consulting',
    },
    {
        key: 'hourly_rate',
        label: 'Hourly rate',
        type: 'number',
        step: '0.01',
        hint: 'Used when billing tracked time',
    },
    { key: 'sort', label: 'Order', type: 'number' },
    { key: 'is_billable', label: 'Billable to customers', type: 'checkbox' },
    { key: 'is_active', label: 'Active', type: 'checkbox' },
];
</script>

<template>
    <Head title="Work types" />

    <ResourceList
        title="Work types"
        description="Kinds of work you track time for, each with an optional hourly rate."
        item-label="work type"
        :items="workTypes"
        :columns="[
            { key: 'name', label: 'Name' },
            { key: 'hourly_rate', label: 'Hourly rate' },
            { key: 'status', label: '' },
        ]"
        :fields="fields"
        :defaults="{
            name: '',
            hourly_rate: null,
            is_billable: true,
            is_active: true,
            sort: null,
        }"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
        :can-write="canEdit('settings')"
    >
        <template #cell-hourly_rate="{ item }">
            {{
                item.hourly_rate
                    ? formatMoney(item.hourly_rate as string, currency)
                    : '—'
            }}
        </template>
        <template #cell-status="{ item }">
            <div class="flex justify-end gap-1">
                <Pill v-if="!item.is_billable" color="gray">Not billable</Pill>
                <Pill v-if="!item.is_active" color="gray">Inactive</Pill>
            </div>
        </template>
    </ResourceList>
</template>
