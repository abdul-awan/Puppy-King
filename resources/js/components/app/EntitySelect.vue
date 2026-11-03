<script setup lang="ts">
import { onClickOutside, useDebounceFn } from '@vueuse/core';
import { Check, ChevronsUpDown, Loader2, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import { cn } from '@/lib/utils';
import { lookup } from '@/routes';
import type { LookupResult } from '@/types';

const props = defineProps<{
    type: string;
    initialLabel?: string | null;
    params?: Record<string, string | number | null | undefined>;
    placeholder?: string;
    disabled?: boolean;
    clearable?: boolean;
    invalid?: boolean;
}>();

const model = defineModel<number | null | undefined>();

const emit = defineEmits<{
    select: [item: LookupResult | null];
}>();

const { slug } = useTeam();

const root = ref<HTMLElement | null>(null);
const open = ref(false);
const query = ref('');
const results = ref<LookupResult[]>([]);
const loading = ref(false);
const highlighted = ref(0);
const label = ref(props.initialLabel ?? '');

watch(
    () => props.initialLabel,
    (value) => {
        label.value = value ?? '';
    },
);

watch(model, (value) => {
    if (value === null || value === undefined) {
        label.value = '';
    }
});

onClickOutside(root, () => {
    open.value = false;
});

const search = useDebounceFn(async () => {
    loading.value = true;

    try {
        const extra: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(props.params ?? {})) {
            if (value !== null && value !== undefined && value !== '') {
                extra[key] = value;
            }
        }

        const url = lookup([slug.value, props.type], {
            query: { q: query.value, ...extra },
        }).url;

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        results.value = response.ok ? await response.json() : [];
        highlighted.value = 0;
    } finally {
        loading.value = false;
    }
}, 200);

const openMenu = () => {
    if (props.disabled) {
        return;
    }

    open.value = !open.value;

    if (open.value) {
        query.value = '';
        search();
    }
};

const choose = (item: LookupResult) => {
    model.value = item.id;
    label.value = item.label;
    open.value = false;
    emit('select', item);
};

const clear = () => {
    model.value = null;
    label.value = '';
    emit('select', null);
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlighted.value = Math.min(
            highlighted.value + 1,
            results.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlighted.value = Math.max(highlighted.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const item = results.value[highlighted.value];

        if (item) {
            choose(item);
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
};
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            :disabled="disabled"
            :aria-invalid="invalid || undefined"
            :class="
                cn(
                    'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30',
                )
            "
            @click="openMenu"
        >
            <span :class="['truncate', label ? '' : 'text-muted-foreground']">
                {{ label || placeholder || 'Select…' }}
            </span>
            <span class="flex shrink-0 items-center gap-1">
                <X
                    v-if="clearable && model"
                    class="size-4 opacity-60 hover:opacity-100"
                    @click.stop="clear"
                />
                <ChevronsUpDown class="size-4 opacity-50" />
            </span>
        </button>

        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full min-w-64 rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
        >
            <Input
                v-model="query"
                v-focus
                placeholder="Type to search…"
                class="mb-1 h-8"
                @update:model-value="search"
                @keydown="onKeydown"
            />
            <div class="max-h-64 overflow-y-auto">
                <div
                    v-if="loading && results.length === 0"
                    class="flex items-center justify-center gap-2 py-3 text-sm text-muted-foreground"
                >
                    <Loader2 class="size-4 animate-spin" /> Searching…
                </div>
                <p
                    v-else-if="results.length === 0"
                    class="py-3 text-center text-sm text-muted-foreground"
                >
                    No results
                </p>
                <button
                    v-for="(item, index) in results"
                    :key="item.id"
                    type="button"
                    class="flex w-full flex-col items-start rounded-sm px-2 py-1.5 text-left text-sm"
                    :class="{ 'bg-accent': index === highlighted }"
                    @mouseenter="highlighted = index"
                    @click="choose(item)"
                >
                    <span class="flex w-full items-center gap-2">
                        <Check
                            :class="[
                                'size-3.5 shrink-0',
                                item.id === model ? 'opacity-100' : 'opacity-0',
                            ]"
                        />
                        <span class="truncate">{{ item.label }}</span>
                    </span>
                    <span
                        v-if="item.description"
                        class="truncate pl-5.5 text-xs text-muted-foreground"
                    >
                        {{ item.description }}
                    </span>
                </button>
            </div>
            <slot name="footer" />
        </div>
    </div>
</template>
