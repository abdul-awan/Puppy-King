<script setup lang="ts">
import { Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';

defineProps<{
    placeholder?: string;
}>();

const model = defineModel<string | null | undefined>();
</script>

<template>
    <div class="relative w-full sm:w-72">
        <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            :model-value="model ?? ''"
            type="search"
            :placeholder="placeholder ?? 'Search…'"
            class="pl-8"
            @update:model-value="model = String($event)"
        />
    </div>
</template>
