<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SimpleList from '@/components/settings/SimpleList.vue';
import { useTeam } from '@/composables/useTeam';
import { destroy, index, store, update } from '@/routes/settings/deal-sources';
import { edit as settingsHome } from '@/routes/settings/general';
import type { Team } from '@/types';

defineProps<{
    items: { id: number; name: string; sort: number }[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Deal sources', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
</script>

<template>
    <Head title="Deal sources" />

    <SimpleList
        title="Deal sources"
        description="Where your deals come from, so you can see which channels work best."
        item-label="source"
        :items="items"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
    />
</template>
