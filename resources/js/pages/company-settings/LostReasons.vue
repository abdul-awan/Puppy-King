<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SimpleList from '@/components/settings/SimpleList.vue';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { destroy, index, store, update } from '@/routes/settings/lost-reasons';
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
            { title: 'Lost reasons', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
</script>

<template>
    <Head title="Lost reasons" />

    <SimpleList
        title="Lost reasons"
        description="Reasons you can choose when a deal is lost."
        item-label="reason"
        :items="items"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
    />
</template>
