<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    Bell,
    CalendarDays,
    CheckCheck,
    ClipboardPen,
    FileText,
    LifeBuoy,
    ListChecks,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTeam } from '@/composables/useTeam';
import { formatRelative } from '@/lib/format';
import { read, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

/**
 * The bell in the header: what happened that concerns you (assigned tickets and tasks,
 * customer replies, signed work orders, answered quotations, event reminders).
 */
const page = usePage();
const { slug } = useTeam();

const feed = computed(() => page.props.notifications);
const unread = computed(() => feed.value?.unread ?? 0);

const icons: Record<string, LucideIcon> = {
    ticket: LifeBuoy,
    task: ListChecks,
    work_order: ClipboardPen,
    quotation: FileText,
    event: CalendarDays,
};

const icon = (notification: AppNotification) =>
    icons[notification.kind.split('.')[0] ?? ''] ?? Bell;

const open = (notification: AppNotification) => {
    router.post(read([slug.value, notification.id]).url);
};

const markAllRead = () => {
    router.post(readAll(slug.value).url, {}, { preserveScroll: true });
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="
                    unread
                        ? `Notifications (${unread} unread)`
                        : 'Notifications'
                "
            >
                <Bell />
                <span
                    v-if="unread"
                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-semibold text-white"
                    >{{ unread > 9 ? '9+' : unread }}</span
                >
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <DropdownMenuLabel class="flex items-center justify-between gap-2">
                <span>Notifications</span>
                <Button
                    v-if="unread"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    @click.stop="markAllRead"
                >
                    <CheckCheck class="size-3.5" /> Mark all as read
                </Button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <p
                v-if="!feed?.items.length"
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                Nothing new. You will see here when something needs you.
            </p>
            <DropdownMenuItem
                v-for="notification in feed?.items ?? []"
                :key="notification.id"
                class="flex cursor-pointer items-start gap-3 py-2"
                :class="{ 'bg-primary/5': !notification.read }"
                @select="open(notification)"
            >
                <component
                    :is="icon(notification)"
                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="block text-sm"
                        :class="{ 'font-medium': !notification.read }"
                        >{{ notification.title }}</span
                    >
                    <span
                        v-if="notification.body"
                        class="block truncate text-xs text-muted-foreground"
                        >{{ notification.body }}</span
                    >
                    <span class="block text-xs text-muted-foreground">{{
                        formatRelative(notification.created_at)
                    }}</span>
                </span>
                <span
                    v-if="!notification.read"
                    class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                    aria-label="Unread"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
