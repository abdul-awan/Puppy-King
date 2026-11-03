<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    FileDown,
    ThumbsDown,
    ThumbsUp,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Field from '@/components/app/Field.vue';
import DocumentView from '@/components/documents/DocumentView.vue';
import type { DocumentSeller } from '@/components/documents/DocumentView.vue';
import SignaturePad from '@/components/documents/SignaturePad.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Toaster } from '@/components/ui/sonner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type { DocumentPresentation, Quotation } from '@/types';

const props = defineProps<{
    quotation: Quotation;
    seller: DocumentSeller;
    amountInWords: string | null;
    presentation: DocumentPresentation;
    canAnswer: boolean;
    urls: { pdf: string; accept: string; decline: string };
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const mode = ref<'none' | 'accept' | 'decline'>('none');

const acceptForm = useForm({ name: '', signature: '', agree: false });
const declineForm = useForm({ reason: '' });

const meta = computed(() => [
    { label: 'Quotation no.', value: props.quotation.number },
    { label: 'Date', value: formatDate(props.quotation.date) },
    {
        label: 'Valid until',
        value: props.quotation.valid_until
            ? formatDate(props.quotation.valid_until)
            : null,
    },
]);

const submitAccept = () => {
    acceptForm.post(props.urls.accept, {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = 'none';
        },
    });
};

const submitDecline = () => {
    declineForm.post(props.urls.decline, {
        preserveScroll: true,
        onSuccess: () => {
            mode.value = 'none';
        },
    });
};
</script>

<template>
    <Head :title="`Quotation ${quotation.number} — ${seller.name ?? ''}`" />

    <div class="min-h-screen bg-muted/40 py-6 sm:py-10">
        <div class="mx-auto w-full max-w-4xl space-y-6 px-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">Quotation from</p>
                    <p class="text-lg font-semibold">{{ seller.name }}</p>
                </div>
                <Button variant="outline" as-child>
                    <a :href="urls.pdf" target="_blank"
                        ><FileDown /> Download PDF</a
                    >
                </Button>
            </div>

            <div
                v-if="quotation.status === 'accepted'"
                class="flex items-center gap-3 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:bg-green-950/40 dark:text-green-200"
            >
                <CheckCircle2 class="size-5 shrink-0" />
                <p>
                    Accepted on {{ formatDateTime(quotation.accepted_at)
                    }}{{
                        quotation.accepted_by_name
                            ? ` by ${quotation.accepted_by_name}`
                            : ''
                    }}. Thank you for your order!
                </p>
            </div>
            <div
                v-else-if="quotation.status === 'declined'"
                class="flex items-center gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900 dark:bg-red-950/40 dark:text-red-200"
            >
                <XCircle class="size-5 shrink-0" />
                <p>
                    This quotation was declined on
                    {{ formatDateTime(quotation.declined_at) }}.
                </p>
            </div>
            <div
                v-else-if="quotation.is_expired"
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                This quotation expired on
                {{ formatDate(quotation.valid_until) }}. Please contact
                {{ seller.name }} for an updated offer.
            </div>

            <DocumentView
                title="Quotation"
                :meta="meta"
                :customer="quotation.customer"
                :lines="quotation.lines ?? []"
                :taxes="quotation.taxes ?? []"
                :currency="quotation.currency"
                :subtotal="quotation.subtotal"
                :discount-percent="quotation.discount_percent"
                :discount-amount="quotation.discount_amount"
                :further-tax-rate="quotation.further_tax_rate"
                :further-tax-amount="quotation.further_tax_amount"
                :total="quotation.total"
                :introduction="quotation.introduction"
                :terms="quotation.terms"
                :heading="quotation.title"
                :amount-in-words="amountInWords"
                :seller="seller"
                :presentation="presentation"
            />

            <InputError :message="errors.quotation" />

            <Card v-if="canAnswer">
                <CardHeader>
                    <CardTitle>Your answer</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="mode === 'none'" class="flex flex-wrap gap-2">
                        <Button
                            class="bg-green-600 text-white hover:bg-green-700"
                            @click="mode = 'accept'"
                        >
                            <ThumbsUp /> Accept quotation
                        </Button>
                        <Button variant="outline" @click="mode = 'decline'">
                            <ThumbsDown /> Decline
                        </Button>
                    </div>

                    <form
                        v-else-if="mode === 'accept'"
                        class="space-y-4"
                        @submit.prevent="submitAccept"
                    >
                        <Field
                            label="Your full name"
                            :error="acceptForm.errors.name"
                            required
                        >
                            <Input
                                v-model="acceptForm.name"
                                required
                                autocomplete="name"
                            />
                        </Field>
                        <Field
                            label="Signature"
                            :error="acceptForm.errors.signature"
                            required
                        >
                            <SignaturePad v-model="acceptForm.signature" />
                        </Field>
                        <label class="flex items-start gap-2 text-sm">
                            <Checkbox
                                :model-value="acceptForm.agree"
                                class="mt-0.5"
                                @update:model-value="
                                    acceptForm.agree = Boolean($event)
                                "
                            />
                            I accept quotation {{ quotation.number }} of
                            {{
                                formatMoney(quotation.total, quotation.currency)
                            }}
                            and its terms.
                        </label>
                        <InputError :message="acceptForm.errors.agree" />
                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                class="bg-green-600 text-white hover:bg-green-700"
                                :disabled="acceptForm.processing"
                            >
                                Confirm acceptance
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                @click="mode = 'none'"
                                >Back</Button
                            >
                        </div>
                    </form>

                    <form
                        v-else
                        class="space-y-4"
                        @submit.prevent="submitDecline"
                    >
                        <Field
                            label="Would you tell us why? (optional)"
                            :error="declineForm.errors.reason"
                        >
                            <Textarea v-model="declineForm.reason" rows="3" />
                        </Field>
                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="declineForm.processing"
                                >Decline quotation</Button
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                @click="mode = 'none'"
                                >Back</Button
                            >
                        </div>
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
