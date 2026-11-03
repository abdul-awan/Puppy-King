<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Field from '@/components/app/Field.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Toaster } from '@/components/ui/sonner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';

const props = defineProps<{
    ticket: {
        number: string | null;
        subject: string;
        status: string;
        status_label: string;
        customer_label: string;
        created_at: string;
        messages: {
            id: number;
            body: string;
            from_customer: boolean;
            author: string;
            created_at: string;
        }[];
    };
    company: string;
    replyUrl: string;
}>();

const form = useForm({
    name:
        props.ticket.customer_label === '—' ? '' : props.ticket.customer_label,
    body: '',
});

const send = () => {
    form.post(props.replyUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};
</script>

<template>
    <Head :title="`${ticket.number} ${ticket.subject} — ${company}`" />

    <div class="min-h-screen bg-muted/40 py-6 sm:py-10">
        <div class="mx-auto w-full max-w-3xl space-y-6 px-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ company }} · support
                </p>
                <h1 class="text-xl font-semibold">{{ ticket.subject }}</h1>
                <p class="text-sm text-muted-foreground">
                    Ticket {{ ticket.number }} · {{ ticket.status_label }}
                </p>
            </div>

            <div
                v-for="message in ticket.messages"
                :key="message.id"
                class="rounded-lg border bg-background p-4"
                :class="{ 'ml-6 border-primary/30': !message.from_customer }"
            >
                <p class="mb-2 text-xs">
                    <span class="font-medium">{{
                        message.from_customer
                            ? message.author
                            : `${message.author} (${company})`
                    }}</span>
                    <span class="text-muted-foreground">
                        · {{ formatDateTime(message.created_at) }}</span
                    >
                </p>
                <p class="text-sm whitespace-pre-line">{{ message.body }}</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{
                        ticket.status === 'closed'
                            ? 'Reopen with a new message'
                            : 'Reply'
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="send">
                        <Field label="Your name" :error="form.errors.name">
                            <Input v-model="form.name" autocomplete="name" />
                        </Field>
                        <Field
                            label="Message"
                            :error="form.errors.body"
                            required
                        >
                            <Textarea v-model="form.body" rows="5" required />
                        </Field>
                        <Button type="submit" :disabled="form.processing"
                            >Send</Button
                        >
                    </form>
                </CardContent>
            </Card>
        </div>
        <Toaster />
    </div>
</template>
