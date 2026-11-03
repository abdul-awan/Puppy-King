<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, FileDown, PenLine } from '@lucide/vue';
import { computed } from 'vue';
import Field from '@/components/app/Field.vue';
import type { DocumentSeller } from '@/components/documents/DocumentView.vue';
import SignaturePad from '@/components/documents/SignaturePad.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Toaster } from '@/components/ui/sonner';
import { Textarea } from '@/components/ui/textarea';
import {
    formatDate,
    formatDateTime,
    formatDuration,
    formatMoney,
} from '@/lib/format';

const props = defineProps<{
    workOrder: {
        number: string | null;
        title: string;
        date: string;
        status_label: string;
        location: string | null;
        description: string | null;
        technician: string | null;
        customer: {
            name: string;
            contact: string | null;
            phone: string | null;
            email: string | null;
        };
        show_prices: boolean;
        is_signed: boolean;
        signed_by_name: string | null;
        signed_at: string | null;
        signature: string | null;
        customer_remarks: string | null;
    };
    entries: {
        id: number;
        date: string;
        user: string | null;
        work: string;
        description: string | null;
        minutes: number;
        is_billable: boolean;
        rate: string | null;
        value: string | null;
    }[];
    materials: {
        id: number;
        description: string;
        quantity: string;
        unit: string | null;
        is_billable: boolean;
        unit_price: string | null;
        value: string | null;
    }[];
    totals: {
        minutes: number;
        time_value: string | null;
        materials_value: string | null;
        total_value: string | null;
    };
    currency: string;
    seller: DocumentSeller;
    canSign: boolean;
    urls: { pdf: string; sign: string };
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const form = useForm({
    name: props.workOrder.customer.contact ?? '',
    signature: '',
    remarks: '',
    agree: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remarks: data.remarks || null,
    })).post(props.urls.sign, { preserveScroll: true });
};

const money = (value: string | null) => formatMoney(value, props.currency);
</script>

<template>
    <Head :title="`Work order ${workOrder.number} — ${seller.name ?? ''}`" />

    <div class="min-h-screen bg-muted/40 py-6 sm:py-10">
        <div class="mx-auto w-full max-w-3xl space-y-6 px-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">Work order from</p>
                    <p class="text-lg font-semibold">{{ seller.name }}</p>
                </div>
                <Button variant="outline" as-child>
                    <a :href="urls.pdf" target="_blank"
                        ><FileDown /> Download PDF</a
                    >
                </Button>
            </div>

            <div
                v-if="workOrder.is_signed"
                class="flex items-center gap-3 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:bg-green-950/40 dark:text-green-200"
            >
                <CheckCircle2 class="size-5 shrink-0" />
                <p>
                    Signed on {{ formatDateTime(workOrder.signed_at) }} by
                    {{ workOrder.signed_by_name }}. Thank you!
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ workOrder.title }}</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Work order {{ workOrder.number }} ·
                        {{ formatDate(workOrder.date) }}
                        <template v-if="workOrder.technician">
                            · {{ workOrder.technician }}</template
                        >
                    </p>
                </CardHeader>
                <CardContent class="space-y-5 text-sm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Customer
                            </p>
                            <p class="font-medium">
                                {{ workOrder.customer.name }}
                            </p>
                            <p v-if="workOrder.customer.contact">
                                Attn. {{ workOrder.customer.contact }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Location
                            </p>
                            <p class="whitespace-pre-line">
                                {{ workOrder.location ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="workOrder.description">
                        <p class="mb-1 font-medium">Work carried out</p>
                        <p class="whitespace-pre-line">
                            {{ workOrder.description }}
                        </p>
                    </div>

                    <div v-if="entries.length">
                        <p class="mb-1 font-medium">
                            Hours ({{ formatDuration(totals.minutes) }})
                        </p>
                        <ul class="divide-y rounded-md border">
                            <li
                                v-for="entry in entries"
                                :key="entry.id"
                                class="flex items-start justify-between gap-3 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p>
                                        {{ formatDate(entry.date) }} ·
                                        {{ entry.user ?? '—' }} ·
                                        {{ entry.work }}
                                    </p>
                                    <p
                                        v-if="entry.description"
                                        class="text-muted-foreground"
                                    >
                                        {{ entry.description }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right tabular-nums">
                                    <p>{{ formatDuration(entry.minutes) }}</p>
                                    <p
                                        v-if="workOrder.show_prices"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            entry.is_billable
                                                ? money(entry.value)
                                                : 'No charge'
                                        }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="materials.length">
                        <p class="mb-1 font-medium">Materials used</p>
                        <ul class="divide-y rounded-md border">
                            <li
                                v-for="material in materials"
                                :key="material.id"
                                class="flex items-start justify-between gap-3 px-3 py-2"
                            >
                                <span>{{ material.description }}</span>
                                <div class="shrink-0 text-right tabular-nums">
                                    <p>
                                        {{ Number(material.quantity) }}
                                        {{ material.unit ?? '' }}
                                    </p>
                                    <p
                                        v-if="workOrder.show_prices"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            material.is_billable
                                                ? money(material.value)
                                                : 'No charge'
                                        }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <dl
                        v-if="
                            workOrder.show_prices &&
                            (entries.length || materials.length)
                        "
                        class="ml-auto max-w-xs space-y-1"
                    >
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Hours</dt>
                            <dd class="tabular-nums">
                                {{ money(totals.time_value) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-muted-foreground">Materials</dt>
                            <dd class="tabular-nums">
                                {{ money(totals.materials_value) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between gap-2 border-t pt-1 font-medium"
                        >
                            <dt>Total excl. tax</dt>
                            <dd class="tabular-nums">
                                {{ money(totals.total_value) }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="workOrder.is_signed && workOrder.signature">
                        <p class="mb-1 font-medium">Signature</p>
                        <img
                            :src="workOrder.signature"
                            alt="Signature"
                            class="h-24 rounded-md border bg-white p-2"
                        />
                        <p
                            v-if="workOrder.customer_remarks"
                            class="mt-2 whitespace-pre-line text-muted-foreground"
                        >
                            “{{ workOrder.customer_remarks }}”
                        </p>
                    </div>
                </CardContent>
            </Card>

            <InputError :message="errors.work_order" />

            <Card v-if="canSign">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><PenLine class="size-4" /> Sign for approval</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <Field
                            label="Your full name"
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
                            label="Signature"
                            :error="form.errors.signature"
                            required
                        >
                            <SignaturePad v-model="form.signature" />
                        </Field>
                        <Field
                            label="Remarks"
                            hint="Optional"
                            :error="form.errors.remarks"
                        >
                            <Textarea v-model="form.remarks" rows="2" />
                        </Field>
                        <label class="flex items-start gap-2 text-sm">
                            <Checkbox
                                :model-value="form.agree"
                                class="mt-0.5"
                                @update:model-value="
                                    form.agree = Boolean($event)
                                "
                            />
                            I confirm that the work of work order
                            {{ workOrder.number }} was carried out as described.
                        </label>
                        <InputError :message="form.errors.agree" />
                        <Button type="submit" :disabled="form.processing">
                            Sign work order
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <p class="text-center text-xs text-muted-foreground">
                Questions? Contact {{ seller.name
                }}{{ seller.email ? ` at ${seller.email}` : ''
                }}{{ seller.phone ? ` or ${seller.phone}` : '' }}.
            </p>
        </div>
        <Toaster />
    </div>
</template>
