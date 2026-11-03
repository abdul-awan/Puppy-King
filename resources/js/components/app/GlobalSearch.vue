<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LoaderCircle, Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import { search } from '@/routes';

type Result = { title: string; subtitle: string | null; url: string };
type Group = { label: string; items: Result[] };

/**
 * Search everything (customers, deals, documents, projects, tickets…) from the header.
 * Opens with Ctrl+K (⌘K on a Mac); use the arrow keys and Enter to open a result.
 */
const { slug } = useTeam();

const open = ref(false);
const query = ref('');
const groups = ref<Group[]>([]);
const loading = ref(false);
const active = ref(0);
let request: AbortController | null = null;

// Every result with its position in the whole list (for the keyboard).
const numbered = computed(() => {
    let position = 0;

    return groups.value.map((group) => ({
        label: group.label,
        items: group.items.map((item) => ({ ...item, position: position++ })),
    }));
});

const results = computed(() => groups.value.flatMap((group) => group.items));

watchDebounced(
    query,
    async (value) => {
        const term = value.trim();
        request?.abort();

        if (term.length < 2) {
            groups.value = [];
            loading.value = false;

            return;
        }

        request = new AbortController();
        loading.value = true;

        try {
            const response = await fetch(
                search(slug.value, { query: { q: term } }).url,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: request.signal,
                },
            );

            if (response.ok) {
                groups.value = (
                    (await response.json()) as { groups: Group[] }
                ).groups;
                active.value = 0;
            }
        } catch {
            // Aborted by a newer search, or offline: keep the previous results.
        } finally {
            loading.value = false;
        }
    },
    { debounce: 200 },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        request?.abort();
        query.value = '';
        groups.value = [];
    }
});

const go = (result: Result) => {
    open.value = false;
    router.visit(result.url);
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        active.value = Math.min(active.value + 1, results.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (event.key === 'Enter') {
        const result = results.value[active.value];

        if (result) {
            event.preventDefault();
            go(result);
        }
    }
};

const onShortcut = (event: KeyboardEvent) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = true;
    }
};

onMounted(() => window.addEventListener('keydown', onShortcut));
onBeforeUnmount(() => window.removeEventListener('keydown', onShortcut));
</script>

<template>
    <Button
        variant="outline"
        size="sm"
        class="gap-2 text-muted-foreground"
        aria-label="Search"
        @click="open = true"
    >
        <Search class="size-4" />
        <span class="hidden lg:inline">Search…</span>
        <kbd
            class="hidden rounded border bg-muted px-1.5 font-mono text-[10px] lg:inline"
            >Ctrl K</kbd
        >
    </Button>

    <Dialog v-model:open="open">
        <DialogContent class="top-[15%] translate-y-0 gap-0 p-0 sm:max-w-xl">
            <DialogHeader class="sr-only">
                <DialogTitle>Search</DialogTitle>
                <DialogDescription>
                    Find customers, deals, documents, projects and tickets.
                </DialogDescription>
            </DialogHeader>
            <div class="flex items-center gap-2 border-b px-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <Input
                    v-model="query"
                    v-focus
                    placeholder="Search customers, invoices, projects, tickets…"
                    class="h-12 border-0 shadow-none focus-visible:ring-0"
                    @keydown="onKeydown"
                />
                <LoaderCircle
                    v-if="loading"
                    class="size-4 shrink-0 animate-spin text-muted-foreground"
                />
            </div>
            <div class="max-h-[60vh] overflow-y-auto p-2">
                <p
                    v-if="query.trim().length < 2"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    Type at least two letters, e.g. a customer, a number or a
                    subject.
                </p>
                <p
                    v-else-if="!loading && !groups.length"
                    class="px-2 py-6 text-center text-sm text-muted-foreground"
                >
                    Nothing found for “{{ query.trim() }}”.
                </p>
                <div
                    v-for="group in numbered"
                    :key="group.label"
                    class="mb-2 last:mb-0"
                >
                    <p
                        class="px-2 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.label }}
                    </p>
                    <button
                        v-for="item in group.items"
                        :key="item.url"
                        type="button"
                        class="flex w-full flex-col items-start rounded-md px-2 py-1.5 text-left text-sm"
                        :class="
                            item.position === active
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-muted'
                        "
                        @mouseenter="active = item.position"
                        @click="go(item)"
                    >
                        <span class="font-medium">{{ item.title }}</span>
                        <span
                            v-if="item.subtitle"
                            class="text-xs text-muted-foreground"
                            >{{ item.subtitle }}</span
                        >
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
