<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, FileText, Paperclip, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTeam } from '@/composables/useTeam';
import { formatDateTime, formatFileSize } from '@/lib/format';
import {
    destroy as destroyAttachment,
    show as showAttachment,
    store as storeAttachment,
} from '@/routes/attachments';
import type { Attachment } from '@/types';

const props = defineProps<{
    attachments: Attachment[];
    attachableType: string;
    attachableId: number;
    canWrite: boolean;
}>();

const { slug } = useTeam();
const input = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const error = ref<string | null>(null);

const upload = (event: Event) => {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);

    if (files.length === 0) {
        return;
    }

    uploading.value = true;
    error.value = null;

    router.post(
        storeAttachment(slug.value).url,
        {
            attachable_type: props.attachableType,
            attachable_id: props.attachableId,
            files,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                error.value = Object.values(errors)[0] ?? 'Upload failed.';
            },
            onFinish: () => {
                uploading.value = false;

                if (input.value) {
                    input.value.value = '';
                }
            },
        },
    );
};

const remove = (attachment: Attachment) => {
    if (!window.confirm(`Delete ${attachment.name}?`)) {
        return;
    }

    router.delete(destroyAttachment([slug.value, attachment.id]).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-4">
        <div v-if="canWrite" class="flex items-center gap-3">
            <input
                ref="input"
                type="file"
                multiple
                class="hidden"
                @change="upload"
            />
            <Button
                variant="outline"
                size="sm"
                :disabled="uploading"
                @click="input?.click()"
            >
                <Upload /> {{ uploading ? 'Uploading…' : 'Upload files' }}
            </Button>
            <span class="text-xs text-muted-foreground"
                >Max 20 MB per file</span
            >
        </div>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

        <p
            v-if="attachments.length === 0"
            class="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground"
        >
            <Paperclip class="size-4" /> No files yet.
        </p>

        <ul v-else class="divide-y rounded-md border">
            <li
                v-for="attachment in attachments"
                :key="attachment.id"
                class="flex items-center justify-between gap-3 px-3 py-2"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <FileText class="size-4 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <a
                            :href="showAttachment([slug, attachment.id]).url"
                            class="block truncate text-sm font-medium hover:underline"
                        >
                            {{ attachment.name }}
                        </a>
                        <p class="text-xs text-muted-foreground">
                            {{ formatFileSize(attachment.size) }} ·
                            {{ attachment.user?.name ?? 'Unknown' }} ·
                            {{ formatDateTime(attachment.created_at) }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Button size="icon-sm" variant="ghost" as-child>
                        <a
                            :href="showAttachment([slug, attachment.id]).url"
                            title="Download"
                        >
                            <Download />
                        </a>
                    </Button>
                    <Button
                        v-if="canWrite && attachment.can_delete"
                        size="icon-sm"
                        variant="ghost"
                        title="Delete"
                        @click="remove(attachment)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
