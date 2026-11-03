<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Field from '@/components/app/Field.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    company: string;
    submitUrl: string;
}>();

const form = useForm({
    name: '',
    email: '',
    company: '',
    subject: '',
    message: '',
    // Left empty by people; bots fill it in.
    website: '',
});

const submit = () => {
    form.post(props.submitUrl);
};
</script>

<template>
    <Head :title="`Support — ${company}`" />

    <div class="min-h-screen bg-muted/40 py-6 sm:py-10">
        <div class="mx-auto w-full max-w-2xl px-4">
            <Card>
                <CardHeader>
                    <CardTitle>{{ company }} support</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Tell us what you need. You get a link to follow your
                        question and to reply.
                    </p>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Your name"
                                :error="form.errors.name"
                                required
                            >
                                <Input
                                    v-model="form.name"
                                    required
                                    autocomplete="name"
                                />
                            </Field>
                            <Field
                                label="E-mail"
                                :error="form.errors.email"
                                required
                            >
                                <Input
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                />
                            </Field>
                        </div>
                        <Field label="Company" :error="form.errors.company">
                            <Input
                                v-model="form.company"
                                autocomplete="organization"
                            />
                        </Field>
                        <Field
                            label="Subject"
                            :error="form.errors.subject"
                            required
                        >
                            <Input v-model="form.subject" required />
                        </Field>
                        <Field
                            label="Your question"
                            :error="form.errors.message"
                            required
                        >
                            <Textarea
                                v-model="form.message"
                                rows="6"
                                required
                            />
                        </Field>
                        <div class="hidden" aria-hidden="true">
                            <label>
                                Website
                                <input
                                    v-model="form.website"
                                    tabindex="-1"
                                    autocomplete="off"
                                />
                            </label>
                        </div>
                        <Button type="submit" :disabled="form.processing"
                            >Send</Button
                        >
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
