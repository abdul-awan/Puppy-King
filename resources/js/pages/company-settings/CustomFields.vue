<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Pill from '@/components/app/Pill.vue';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { destroy, index, store, update } from '@/routes/settings/custom-fields';
import type { CustomFieldDefinition, Option, Team } from '@/types';

const props = defineProps<{
    customFields: CustomFieldDefinition[];
    entities: Option[];
    types: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Custom fields', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const fields: ResourceField[] = [
    {
        key: 'entity',
        label: 'Add the field to',
        type: 'select',
        required: true,
        options: props.entities,
        lockedOnEdit: true,
    },
    {
        key: 'type',
        label: 'Field type',
        type: 'select',
        required: true,
        options: props.types,
    },
    {
        key: 'label',
        label: 'Label',
        type: 'text',
        required: true,
        wide: true,
        placeholder: 'e.g. Credit limit',
    },
    {
        key: 'options',
        label: 'Dropdown options',
        type: 'lines',
        hint: 'One option per line',
        visible: (data) => data.type === 'select',
    },
    { key: 'sort', label: 'Order', type: 'number' },
    { key: 'is_required', label: 'Required', type: 'checkbox' },
];
</script>

<template>
    <Head title="Custom fields" />

    <ResourceList
        title="Custom fields"
        description="Extra information you want to keep on companies, contacts, deals, products, projects or tickets."
        item-label="custom field"
        :items="customFields"
        :columns="[
            { key: 'label', label: 'Label' },
            { key: 'entity_label', label: 'On' },
            { key: 'type_label', label: 'Type', class: 'hidden sm:table-cell' },
            { key: 'required', label: '' },
        ]"
        :fields="fields"
        :defaults="{
            entity: 'company',
            type: 'text',
            label: '',
            options: '',
            sort: null,
            is_required: false,
        }"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
        :can-write="canEdit('settings')"
    >
        <template #cell-required="{ item }">
            <div class="flex justify-end">
                <Pill v-if="item.is_required" color="amber">Required</Pill>
            </div>
        </template>
    </ResourceList>
</template>
