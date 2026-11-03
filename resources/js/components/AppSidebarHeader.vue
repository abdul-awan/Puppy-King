<script setup lang="ts">
import GlobalSearch from '@/components/app/GlobalSearch.vue';
import NotificationBell from '@/components/app/NotificationBell.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import TimerWidget from '@/components/time/TimerWidget.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div
            v-if="$page.props.currentTeam"
            class="ml-auto flex items-center gap-2"
        >
            <TimerWidget />
            <GlobalSearch />
            <NotificationBell />
        </div>
    </header>
</template>
