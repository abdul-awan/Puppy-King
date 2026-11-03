<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

const props = defineProps<{
    meta: Paginated<unknown>['meta'];
    links: Paginated<unknown>['links'];
}>();

// The first and last entries are the "previous" / "next" links.
const pages = computed(() => props.meta.links.slice(1, -1));
</script>

<template>
    <div
        v-if="meta.total > 0"
        class="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row"
    >
        <p class="text-muted-foreground">
            Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of
            {{ meta.total }}
        </p>

        <nav v-if="meta.last_page > 1" class="flex items-center gap-1">
            <Button v-if="links.prev" variant="outline" size="icon-sm" as-child>
                <Link
                    :href="links.prev"
                    preserve-scroll
                    preserve-state
                    aria-label="Previous page"
                >
                    <ChevronLeft />
                </Link>
            </Button>
            <Button v-else variant="outline" size="icon-sm" disabled>
                <ChevronLeft />
            </Button>

            <template v-for="page in pages" :key="page.label">
                <span
                    v-if="!page.url"
                    class="px-2 text-muted-foreground select-none"
                    >…</span
                >
                <Button
                    v-else
                    :variant="page.active ? 'default' : 'ghost'"
                    size="icon-sm"
                    as-child
                >
                    <Link :href="page.url" preserve-scroll preserve-state>
                        {{ page.label }}
                    </Link>
                </Button>
            </template>

            <Button v-if="links.next" variant="outline" size="icon-sm" as-child>
                <Link
                    :href="links.next"
                    preserve-scroll
                    preserve-state
                    aria-label="Next page"
                >
                    <ChevronRight />
                </Link>
            </Button>
            <Button v-else variant="outline" size="icon-sm" disabled>
                <ChevronRight />
            </Button>
        </nav>
    </div>
</template>
