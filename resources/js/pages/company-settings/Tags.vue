<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Pill from '@/components/app/Pill.vue';
import ResourceList from '@/components/settings/ResourceList.vue';
import type { ResourceField } from '@/components/settings/ResourceList.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { destroy, index, store, update } from '@/routes/settings/tags';
import type { Option, Team } from '@/types';

const props = defineProps<{
    tags: { id: number; name: string; color: string; usage: number }[];
    colors: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Tags', href: index(props.currentTeam.slug) },
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
        placeholder: 'e.g. VIP',
    },
    {
        key: 'color',
        label: 'Colour',
        type: 'select',
        required: true,
        options: props.colors,
    },
];
</script>

<template>
    <Head title="Tags" />

    <ResourceList
        title="Tags"
        description="Labels to group companies and contacts, e.g. VIP, Wholesale or Lahore region."
        item-label="tag"
        :items="tags"
        :columns="[
            { key: 'name', label: 'Tag' },
            { key: 'usage', label: 'Used on' },
        ]"
        :fields="fields"
        :defaults="{ name: '', color: 'blue' }"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
        :can-write="canEdit('settings')"
    >
        <template #cell-name="{ item }">
            <Pill :color="item.color as string">{{ item.name }}</Pill>
        </template>
        <template #cell-usage="{ item }">
            <span class="text-muted-foreground">{{ item.usage }} records</span>
        </template>
    </ResourceList>
</template>
