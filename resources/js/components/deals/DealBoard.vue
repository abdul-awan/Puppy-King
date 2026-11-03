<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useTeam } from '@/composables/useTeam';
import { useInitials } from '@/composables/useInitials';
import { formatDate, formatMoney, today } from '@/lib/format';
import { move, show } from '@/routes/deals';
import type { BoardPhase, Deal } from '@/types';

const props = defineProps<{
    phases: BoardPhase[];
    canWrite: boolean;
}>();

const { slug, currency } = useTeam();
const { getInitials } = useInitials();

// Local copy so cards move instantly; the server response refreshes it.
// (Reactive proxies cannot be structuredClone()d, so copy the plain data.)
const copy = (phases: BoardPhase[]): BoardPhase[] =>
    JSON.parse(JSON.stringify(phases)) as BoardPhase[];

const columns = ref<BoardPhase[]>(copy(props.phases));

watch(
    () => props.phases,
    (phases) => {
        columns.value = copy(phases);
    },
    { deep: true },
);

const dragging = ref<{ deal: Deal; fromPhaseId: number } | null>(null);
const dropTarget = ref<{ phaseId: number; index: number } | null>(null);

const onDragStart = (event: DragEvent, deal: Deal, phaseId: number) => {
    if (!props.canWrite) {
        return;
    }

    dragging.value = { deal, fromPhaseId: phaseId };
    event.dataTransfer?.setData('text/plain', String(deal.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const onDragOverCard = (event: DragEvent, phaseId: number, index: number) => {
    if (!dragging.value) {
        return;
    }

    const card = event.currentTarget as HTMLElement;
    const rect = card.getBoundingClientRect();
    const after = event.clientY > rect.top + rect.height / 2;

    dropTarget.value = { phaseId, index: after ? index + 1 : index };
};

const onDragOverColumn = (phase: BoardPhase) => {
    if (!dragging.value) {
        return;
    }

    if (!dropTarget.value || dropTarget.value.phaseId !== phase.id) {
        dropTarget.value = { phaseId: phase.id, index: phase.deals.length };
    }
};

const onDrop = () => {
    if (!dragging.value || !dropTarget.value) {
        return;
    }

    const { deal, fromPhaseId } = dragging.value;
    const { phaseId, index } = dropTarget.value;
    const from = columns.value.find((column) => column.id === fromPhaseId);
    const to = columns.value.find((column) => column.id === phaseId);

    dragging.value = null;
    dropTarget.value = null;

    if (!from || !to) {
        return;
    }

    const oldIndex = from.deals.findIndex((item) => item.id === deal.id);
    let position = index;

    if (from.id === to.id && oldIndex < index) {
        position -= 1;
    }

    if (from.id === to.id && oldIndex === position) {
        return;
    }

    from.deals.splice(oldIndex, 1);
    to.deals.splice(position, 0, { ...deal, phase_id: to.id });

    router.patch(
        move([slug.value, deal.id]).url,
        { phase_id: to.id, position },
        { preserveScroll: true, preserveState: true, only: ['board'] },
    );
};

const onDragEnd = () => {
    dragging.value = null;
    dropTarget.value = null;
};

const customer = (deal: Deal) => deal.company?.name ?? deal.contact?.name ?? '';

const isOverdue = (deal: Deal) =>
    deal.expected_close_date !== null && deal.expected_close_date < today();
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-4">
        <section
            v-for="phase in columns"
            :key="phase.id"
            class="flex w-72 shrink-0 flex-col rounded-lg bg-muted/50"
            @dragover.prevent="onDragOverColumn(phase)"
            @drop.prevent="onDrop"
        >
            <header class="space-y-0.5 border-b px-3 py-2.5">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="truncate text-sm font-semibold">
                        {{ phase.name }}
                    </h3>
                    <span class="text-xs text-muted-foreground">{{
                        phase.deals.length
                    }}</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ formatMoney(phase.total, currency) }} ·
                    {{ phase.probability }}%
                </p>
            </header>

            <div class="flex min-h-24 flex-1 flex-col gap-2 p-2">
                <template v-for="(deal, index) in phase.deals" :key="deal.id">
                    <div
                        v-if="
                            dropTarget?.phaseId === phase.id &&
                            dropTarget.index === index
                        "
                        class="h-1 rounded bg-primary"
                    />
                    <article
                        :draggable="canWrite"
                        class="group rounded-md border bg-background p-3 shadow-xs transition"
                        :class="{
                            'cursor-grab active:cursor-grabbing': canWrite,
                            'opacity-40': dragging?.deal.id === deal.id,
                        }"
                        @dragstart="onDragStart($event, deal, phase.id)"
                        @dragover.prevent.stop="
                            onDragOverCard($event, phase.id, index)
                        "
                        @drop.prevent.stop="onDrop"
                        @dragend="onDragEnd"
                    >
                        <Link
                            :href="show([slug, deal.id]).url"
                            class="line-clamp-2 text-sm font-medium hover:underline"
                        >
                            {{ deal.title }}
                        </Link>
                        <p
                            v-if="customer(deal)"
                            class="mt-0.5 truncate text-xs text-muted-foreground"
                        >
                            {{ customer(deal) }}
                        </p>
                        <div
                            class="mt-2 flex items-center justify-between gap-2"
                        >
                            <span class="text-sm font-semibold">
                                {{ formatMoney(deal.value, deal.currency) }}
                            </span>
                            <span
                                v-if="deal.responsible_user"
                                class="flex size-6 items-center justify-center rounded-full bg-muted text-[10px] font-medium"
                                :title="deal.responsible_user.name"
                            >
                                {{ getInitials(deal.responsible_user.name) }}
                            </span>
                        </div>
                        <p
                            v-if="deal.expected_close_date"
                            class="mt-1.5 flex items-center gap-1 text-xs"
                            :class="
                                isOverdue(deal)
                                    ? 'text-destructive'
                                    : 'text-muted-foreground'
                            "
                        >
                            <CalendarClock class="size-3" />
                            {{ formatDate(deal.expected_close_date) }}
                        </p>
                    </article>
                </template>
                <div
                    v-if="
                        dropTarget?.phaseId === phase.id &&
                        dropTarget.index === phase.deals.length
                    "
                    class="h-1 rounded bg-primary"
                />
                <p
                    v-if="
                        phase.deals.length === 0 &&
                        dropTarget?.phaseId !== phase.id
                    "
                    class="py-6 text-center text-xs text-muted-foreground"
                >
                    No deals
                </p>
            </div>
        </section>
    </div>
</template>
