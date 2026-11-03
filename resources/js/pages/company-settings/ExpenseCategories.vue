<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SimpleList from '@/components/settings/SimpleList.vue';
import { useTeam } from '@/composables/useTeam';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/settings/expense-categories';
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
            {
                title: 'Expense categories',
                href: index(props.currentTeam.slug),
            },
        ],
    }),
});

const { slug } = useTeam();
</script>

<template>
    <Head title="Expense categories" />

    <SimpleList
        title="Expense categories"
        description="Group your bills (rent, utilities, fuel…) to see where the money goes."
        item-label="category"
        :items="items"
        :store-url="store(slug).url"
        :update-url="(item) => update([slug, item.id]).url"
        :destroy-url="(item) => destroy([slug, item.id]).url"
    />
</template>
