<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CalendarDays,
    History,
    Mail,
    Pencil,
    Pin,
    PinOff,
    StickyNote,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { formatDate, formatDateTime, formatRelative } from '@/lib/format';
import {
    destroy as destroyNote,
    store as storeNote,
    update as updateNote,
} from '@/routes/notes';
import type { TimelineItem, TimelineNote } from '@/types';

const props = defineProps<{
    items: TimelineItem[];
    notableType: string;
    notableId: number;
    canWrite: boolean;
}>();

const { slug } = useTeam();

const body = ref('');
const saving = ref(false);
const editingId = ref<number | null>(null);
const editingBody = ref('');
const expandedEmails = ref<number[]>([]);

// Pinned notes first, then everything else newest first (as sent by the server).
const sortedItems = computed(() => [
    ...props.items.filter((item) => item.kind === 'note' && item.is_pinned),
    ...props.items.filter((item) => !(item.kind === 'note' && item.is_pinned)),
]);

const addNote = () => {
    if (body.value.trim() === '') {
        return;
    }

    saving.value = true;

    router.post(
        storeNote(slug.value).url,
        {
            notable_type: props.notableType,
            notable_id: props.notableId,
            body: body.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                body.value = '';
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
};

const startEditing = (note: TimelineNote) => {
    editingId.value = note.id;
    editingBody.value = note.body;
};

const saveEdit = (note: TimelineNote) => {
    router.patch(
        updateNote([slug.value, note.id]).url,
        { body: editingBody.value, is_pinned: note.is_pinned },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
};

const togglePin = (note: TimelineNote) => {
    router.patch(
        updateNote([slug.value, note.id]).url,
        { body: note.body, is_pinned: !note.is_pinned },
        { preserveScroll: true },
    );
};

const deleteNote = (note: TimelineNote) => {
    if (!window.confirm('Delete this note?')) {
        return;
    }

    router.delete(destroyNote([slug.value, note.id]).url, {
        preserveScroll: true,
    });
};

const toggleEmail = (id: number) => {
    expandedEmails.value = expandedEmails.value.includes(id)
        ? expandedEmails.value.filter((emailId) => emailId !== id)
        : [...expandedEmails.value, id];
};
</script>

<template>
    <div class="space-y-5">
        <div v-if="canWrite" class="space-y-2">
            <Textarea
                v-model="body"
                rows="3"
                placeholder="Write a note… (visible to your team only)"
                @keydown.ctrl.enter="addNote"
                @keydown.meta.enter="addNote"
            />
            <div class="flex justify-end">
                <Button
                    size="sm"
                    :disabled="saving || body.trim() === ''"
                    @click="addNote"
                >
                    <StickyNote /> Add note
                </Button>
            </div>
        </div>

        <p
            v-if="sortedItems.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
        >
            Nothing has happened here yet.
        </p>

        <ol v-else class="relative space-y-4 border-l pl-6">
            <li v-for="item in sortedItems" :key="item.key" class="relative">
                <span
                    class="absolute top-0.5 -left-[2.1rem] flex size-7 items-center justify-center rounded-full border bg-background"
                    :class="{
                        'border-amber-300 bg-amber-50 dark:bg-amber-950':
                            item.kind === 'note',
                        'border-blue-300 bg-blue-50 dark:bg-blue-950':
                            item.kind === 'email',
                        'border-green-300 bg-green-50 dark:bg-green-950':
                            item.kind === 'event',
                    }"
                >
                    <StickyNote
                        v-if="item.kind === 'note'"
                        class="size-3.5 text-amber-600"
                    />
                    <Mail
                        v-else-if="item.kind === 'email'"
                        class="size-3.5 text-blue-600"
                    />
                    <CalendarDays
                        v-else-if="item.kind === 'event'"
                        class="size-3.5 text-green-600"
                    />
                    <History v-else class="size-3.5 text-muted-foreground" />
                </span>

                <div class="flex flex-wrap items-baseline gap-x-2 text-xs">
                    <span class="font-medium text-foreground">
                        {{ item.user?.name ?? 'System' }}
                    </span>
                    <span
                        class="text-muted-foreground"
                        :title="formatDateTime(item.created_at)"
                    >
                        {{ formatRelative(item.created_at) }}
                    </span>
                    <Pin
                        v-if="item.kind === 'note' && item.is_pinned"
                        class="size-3 text-amber-600"
                    />
                </div>

                <template v-if="item.kind === 'note'">
                    <div v-if="editingId === item.id" class="mt-2 space-y-2">
                        <Textarea v-model="editingBody" rows="3" />
                        <div class="flex justify-end gap-2">
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="editingId = null"
                            >
                                Cancel
                            </Button>
                            <Button size="sm" @click="saveEdit(item)">
                                Save
                            </Button>
                        </div>
                    </div>
                    <div
                        v-else
                        class="group mt-1 rounded-md border border-amber-200 bg-amber-50/60 p-3 text-sm whitespace-pre-line dark:border-amber-900 dark:bg-amber-950/30"
                    >
                        {{ item.body }}
                        <div
                            v-if="canWrite && item.can_edit"
                            class="mt-2 flex gap-1 opacity-0 transition group-hover:opacity-100"
                        >
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                :title="item.is_pinned ? 'Unpin' : 'Pin'"
                                @click="togglePin(item)"
                            >
                                <PinOff v-if="item.is_pinned" />
                                <Pin v-else />
                            </Button>
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                title="Edit"
                                @click="startEditing(item)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                title="Delete"
                                @click="deleteNote(item)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                </template>

                <div v-else-if="item.kind === 'email'" class="mt-1 text-sm">
                    <button
                        type="button"
                        class="text-left hover:underline"
                        @click="toggleEmail(item.id)"
                    >
                        E-mail to <span class="font-medium">{{ item.to }}</span
                        >:
                        {{ item.subject }}
                    </button>
                    <span
                        v-if="item.status === 'failed'"
                        class="ml-2 text-xs text-destructive"
                    >
                        failed{{ item.error ? `: ${item.error}` : '' }}
                    </span>
                    <div
                        v-if="expandedEmails.includes(item.id)"
                        class="mt-2 rounded-md border bg-muted/40 p-3 text-sm whitespace-pre-line"
                    >
                        {{ item.body }}
                    </div>
                </div>

                <div v-else-if="item.kind === 'event'" class="mt-1 text-sm">
                    <p>
                        <span class="font-medium">{{ item.type_label }}:</span>
                        {{ item.title }}
                        <span
                            v-if="item.status !== 'planned'"
                            class="ml-1 text-xs"
                            :class="
                                item.status === 'done'
                                    ? 'text-green-700 dark:text-green-400'
                                    : 'text-destructive'
                            "
                        >
                            ({{
                                item.status === 'done' ? 'done' : 'cancelled'
                            }})
                        </span>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            item.all_day
                                ? formatDate(item.starts_at)
                                : formatDateTime(item.starts_at)
                        }}
                        <template v-if="item.location">
                            · {{ item.location }}
                        </template>
                    </p>
                    <p
                        v-if="item.outcome"
                        class="mt-1 rounded-md border bg-muted/40 p-2 whitespace-pre-line"
                    >
                        {{ item.outcome }}
                    </p>
                </div>

                <p v-else class="mt-0.5 text-sm text-muted-foreground">
                    {{ item.description }}
                </p>
            </li>
        </ol>
    </div>
</template>
