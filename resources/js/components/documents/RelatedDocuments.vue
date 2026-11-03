<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import Pill from '@/components/app/Pill.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney } from '@/lib/format';
import type {
    RelatedDocumentGroup,
    RelatedDocumentGroups,
    RelatedDocumentKey,
} from '@/types';

const props = defineProps<{
    related: RelatedDocumentGroups;
    /** Links for the "New …" buttons, per group. */
    createLinks?: Partial<Record<RelatedDocumentKey, string>>;
    /** Links to the full, filtered list, per group. */
    listLinks?: Partial<Record<RelatedDocumentKey, string>>;
}>();

const titles: Record<RelatedDocumentKey, { title: string; empty: string }> = {
    tickets: { title: 'Tickets', empty: 'No tickets.' },
    work_orders: { title: 'Work orders', empty: 'No work orders yet.' },
    projects: { title: 'Projects', empty: 'No projects yet.' },
    quotations: { title: 'Quotations', empty: 'No quotations yet.' },
    invoices: { title: 'Invoices', empty: 'No invoices yet.' },
    credit_notes: { title: 'Credit notes', empty: 'No credit notes.' },
    expenses: {
        title: 'Expenses (bills)',
        empty: 'No bills from this supplier.',
    },
};

const groups = computed(() =>
    (Object.keys(props.related) as RelatedDocumentKey[])
        .map((key) => ({ key, group: props.related[key] }))
        .filter(
            (
                entry,
            ): entry is {
                key: RelatedDocumentKey;
                group: Exclude<RelatedDocumentGroup, null>;
            } => entry.group !== null && entry.group !== undefined,
        ),
);
</script>

<template>
    <div class="space-y-6">
        <p v-if="groups.length === 0" class="text-sm text-muted-foreground">
            You don't have access to any document modules.
        </p>
        <section v-for="{ key, group } in groups" :key="key" class="space-y-2">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold">
                    {{ titles[key].title }}
                    <span class="font-normal text-muted-foreground"
                        >({{ group.total }})</span
                    >
                </h3>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="
                            listLinks?.[key] && group.total > group.items.length
                        "
                        :href="listLinks[key]"
                        class="text-xs text-muted-foreground hover:underline"
                    >
                        View all
                    </Link>
                    <Button
                        v-if="createLinks?.[key]"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link :href="createLinks[key]"><Plus /> New</Link>
                    </Button>
                </div>
            </div>
            <p
                v-if="group.items.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ titles[key].empty }}
            </p>
            <ul v-else class="divide-y rounded-md border">
                <li
                    v-for="document in group.items"
                    :key="document.id"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm"
                >
                    <Link
                        :href="document.url"
                        class="font-medium hover:underline"
                    >
                        {{ document.number ?? 'Draft' }}
                    </Link>
                    <span
                        v-if="document.title"
                        class="min-w-0 flex-1 truncate text-muted-foreground"
                    >
                        {{ document.title }}
                    </span>
                    <span v-else class="flex-1" />
                    <span class="text-xs text-muted-foreground">{{
                        formatDate(document.date)
                    }}</span>
                    <Pill :color="document.status_color">{{
                        document.status_label
                    }}</Pill>
                    <span class="w-32 text-right font-medium whitespace-nowrap">
                        {{ formatMoney(document.total, document.currency) }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
