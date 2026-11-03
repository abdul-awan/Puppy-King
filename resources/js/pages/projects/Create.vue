<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ProjectForm from '@/components/projects/ProjectForm.vue';
import type { ProjectPrefill } from '@/components/projects/ProjectForm.vue';
import { create, index } from '@/routes/projects';
import type { IdName, Option, Team } from '@/types';

defineProps<{
    prefill: ProjectPrefill;
    users: IdName[];
    statuses: Option[];
    billingMethods: Option[];
    currencies: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index(props.currentTeam.slug) },
            { title: 'New project', href: create(props.currentTeam.slug) },
        ],
    }),
});
</script>

<template>
    <Head title="New project" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
        <PageHeader title="New project" />
        <ProjectForm v-bind="$props" />
    </div>
</template>
