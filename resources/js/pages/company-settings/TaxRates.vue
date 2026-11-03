<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Info } from '@lucide/vue';
import Pill from '@/components/app/Pill.vue';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatPercent } from '@/lib/format';
import { edit as settingsHome } from '@/routes/settings/general';
import { destroy, index, store, update } from '@/routes/settings/tax-rates';
import type { Option, Team } from '@/types';

const props = defineProps<{
    taxRates: {
        id: number;
        name: string;
        rate: string;
        kind: string;
        kind_label: string;
        authority: string | null;
        authority_label: string | null;
        description: string | null;
        is_default: boolean;
        is_active: boolean;
        sort: number;
    }[];
    kinds: Option[];
    authorities: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Tax rates', href: index(props.currentTeam.slug) },
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
        placeholder: 'e.g. Sales tax 18%',
    },
    {
        key: 'rate',
        label: 'Rate (%)',
        type: 'number',
        required: true,
        step: '0.001',
    },
    {
        key: 'kind',
        label: 'Kind',
        type: 'select',
        required: true,
        options: props.kinds,
    },
    {
        key: 'authority',
        label: 'Collected by',
        type: 'select',
        options: props.authorities,
    },
    { key: 'description', label: 'Description', type: 'text', wide: true },
    { key: 'sort', label: 'Order', type: 'number' },
    {
        key: 'is_default',
        label: 'Default rate for new lines',
        type: 'checkbox',
    },
    {
        key: 'is_active',
        label: 'Active (can be chosen on documents)',
        type: 'checkbox',
    },
];
</script>

<template>
    <Head title="Tax rates" />

    <ResourceList
        title="Tax rates"
        description="The sales tax rates you can choose on quotation and invoice lines."
        item-label="tax rate"
        :items="taxRates"
        :columns="[
            { key: 'name', label: 'Name' },
            { key: 'rate', label: 'Rate' },
            {
                key: 'authority_label',
                label: 'Collected by',
                class: 'hidden md:table-cell',
            },
            { key: 'status', label: '' },
        ]"
        :fields="fields"
        :defaults="{
            name: '',
            rate: '',
            kind: 'standard',
            authority: 'fbr',
            description: '',
            sort: null,
            is_default: false,
            is_active: true,
        }"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
        :can-write="canEdit('settings')"
    >
        <template #intro>
            <div
                class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <Info class="mt-0.5 size-4 shrink-0" />
                <p>
                    Pre-filled with commonly used Pakistani rates (federal sales
                    tax on goods and the provincial sales tax on services).
                    Rates change with each federal and provincial budget —
                    please check them with your tax adviser. Documents keep the
                    rate they were created with, so editing a rate never changes
                    existing invoices.
                </p>
            </div>
        </template>
        <template #cell-rate="{ item }">{{
            formatPercent(item.rate as string)
        }}</template>
        <template #cell-status="{ item }">
            <div class="flex justify-end gap-1">
                <Pill v-if="item.is_default" color="green">Default</Pill>
                <Pill v-if="!item.is_active" color="gray">Inactive</Pill>
            </div>
        </template>
    </ResourceList>
</template>
