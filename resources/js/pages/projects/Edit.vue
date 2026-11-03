<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import ProjectForm from '@/components/projects/ProjectForm.vue';
import { edit, index, show } from '@/routes/projects';
import type { IdName, Option, Project, Team } from '@/types';

defineProps<{
    project: Project;
    users: IdName[];
    statuses: Option[];
    billingMethods: Option[];
    currencies: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; project: Project }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index(props.currentTeam.slug) },
            {
                title: props.project.title,
                href: show([props.currentTeam.slug, props.project.id]),
            },
            {
                title: 'Edit',
                href: edit([props.currentTeam.slug, props.project.id]),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${project.title}`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
        <PageHeader :title="`Edit ${project.title}`" />
        <ProjectForm v-bind="$props" />
    </div>
</template>
