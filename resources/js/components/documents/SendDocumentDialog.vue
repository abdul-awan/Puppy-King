<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Paperclip, Send } from '@lucide/vue';
import { watch } from 'vue';
import Field from '@/components/app/Field.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { EmailDraft } from '@/types';

const props = defineProps<{
    title: string;
    draft: EmailDraft;
    url: string;
    attachmentName?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    to: props.draft.to,
    cc: props.draft.cc,
    subject: props.draft.subject,
    body: props.draft.body,
    attach_pdf: true,
});

watch(open, (value) => {
    if (value) {
        form.defaults({ ...props.draft, attach_pdf: true });
        form.reset();
        form.clearErrors();
    }
});

const submit = () => {
    form.post(props.url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    The e-mail is sent from your company's address. Separate
                    several addresses with commas.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <Field label="To" :error="form.errors.to" required>
                    <Input
                        v-model="form.to"
                        placeholder="customer@example.com"
                        required
                    />
                </Field>
                <Field label="Cc" :error="form.errors.cc">
                    <Input v-model="form.cc" />
                </Field>
                <Field label="Subject" :error="form.errors.subject" required>
                    <Input v-model="form.subject" required />
                </Field>
                <Field label="Message" :error="form.errors.body" required>
                    <Textarea v-model="form.body" rows="10" required />
                </Field>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.attach_pdf"
                        @update:model-value="form.attach_pdf = Boolean($event)"
                    />
                    <Paperclip class="size-4" /> Attach
                    {{ attachmentName ?? 'the PDF' }}
                </label>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        <Send /> {{ form.processing ? 'Sending…' : 'Send' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
