<script setup lang="ts">
import { ImagePlus, LoaderCircle } from '@lucide/vue';
import { ref } from 'vue';
import { postForm } from './http';
import type { UploadedImage } from './types';

/**
 * Choose one of the company's uploaded images, or upload a new one (PNG, JPG or GIF up to 2 MB).
 */
const props = defineProps<{
    modelValue: string;
    images: UploadedImage[];
    uploadUrl: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    uploaded: [image: UploadedImage];
}>();

const input = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const error = ref<string | null>(null);

const upload = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    uploading.value = true;
    error.value = null;

    try {
        const body = new FormData();
        body.append('image', file);
        const response = await postForm(props.uploadUrl, body);

        if (!response.ok) {
            const data = response.status === 422 ? await response.json() : null;
            error.value =
                data?.errors?.image?.[0] ??
                'The image could not be uploaded. Use a PNG, JPG or GIF of at most 2 MB.';

            return;
        }

        const image = (await response.json()) as UploadedImage;
        emit('uploaded', image);
        emit('update:modelValue', image.name);
    } finally {
        uploading.value = false;

        if (input.value) {
            input.value.value = '';
        }
    }
};
</script>

<template>
    <div class="grid gap-2">
        <div v-if="images.length" class="grid grid-cols-3 gap-2">
            <button
                v-for="image in images"
                :key="image.name"
                type="button"
                class="flex h-16 items-center justify-center rounded-md border bg-white p-1"
                :class="{ 'ring-2 ring-primary': image.name === modelValue }"
                :title="image.name"
                @click="emit('update:modelValue', image.name)"
            >
                <img
                    :src="image.url"
                    alt=""
                    class="max-h-full max-w-full object-contain"
                />
            </button>
        </div>
        <button
            type="button"
            class="flex items-center justify-center gap-2 rounded-md border border-dashed p-3 text-sm text-muted-foreground transition hover:border-primary hover:text-foreground"
            :disabled="uploading"
            @click="input?.click()"
        >
            <LoaderCircle v-if="uploading" class="size-4 animate-spin" />
            <ImagePlus v-else class="size-4" />
            {{ uploading ? 'Uploading…' : 'Upload an image' }}
        </button>
        <input
            ref="input"
            type="file"
            accept="image/png,image/jpeg,image/gif"
            class="hidden"
            @change="upload"
        />
        <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
        <p class="text-xs text-muted-foreground">
            PNG, JPG or GIF, at most 2 MB. A stamp or seal with a transparent
            background looks best.
        </p>
    </div>
</template>
