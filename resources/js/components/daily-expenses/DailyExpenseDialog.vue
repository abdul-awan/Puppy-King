<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
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
import { useTeam } from '@/composables/useTeam';
import { today } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { store, update } from '@/routes/daily-expenses';
import type { DailyExpense, IdName, Option } from '@/types';

/**
 * Enter a daily expense quickly (with a photo of the receipt), or change one.
 */
const props = defineProps<{
    expense?: DailyExpense | null;
    categories: IdName[];
    paymentMethods: Option[];
    people: string[];
    payees: string[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();
const page = usePage();
const fileInput = ref(0);
const descriptionField = ref<HTMLElement | null>(null);

const blank = () => ({
    date: today(),
    category_id: null as number | null,
    description: '',
    amount: '' as string | number,
    payment_method: 'cash',
    paid_by: page.props.auth.user.name,
    paid_to: '',
    reference: '',
    notes: '',
    files: [] as File[],
});

const form = useForm(blank());

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    fileInput.value++;

    const expense = props.expense;

    form.defaults(
        expense
            ? {
                  date: expense.date,
                  category_id: expense.category_id,
                  description: expense.description,
                  amount: Number(expense.amount),
                  payment_method: expense.payment_method,
                  paid_by: expense.paid_by ?? '',
                  paid_to: expense.paid_to ?? '',
                  reference: expense.reference ?? '',
                  notes: expense.notes ?? '',
                  files: [],
              }
            : blank(),
    );
    form.reset();
});

const onFiles = (event: Event) => {
    form.files = Array.from((event.target as HTMLInputElement).files ?? []);
};

const submit = (another = false) => {
    form.transform((data) => ({
        ...data,
        ...(props.expense ? { _method: 'put' } : {}),
    }));

    // Files are sent as multipart form data, which only works with POST (the update spoofs PUT).
    form.post(
        props.expense
            ? update([slug.value, props.expense.id]).url
            : store(slug.value).url,
        {
            preserveScroll: true,
            forceFormData: form.files.length > 0,
            onSuccess: () => {
                if (!another) {
                    open.value = false;

                    return;
                }

                // Keep the day, category, way of paying and person for the next expense.
                form.description = '';
                form.amount = '';
                form.paid_to = '';
                form.reference = '';
                form.notes = '';
                form.files = [];
                fileInput.value++;
                void nextTick(() =>
                    descriptionField.value
                        ?.querySelector<HTMLInputElement>('input')
                        ?.focus(),
                );
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        expense ? `Edit ${expense.number}` : 'Add daily expense'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Day-to-day spending such as tea, fuel, stationery or a
                    courier.
                </DialogDescription>
            </DialogHeader>

            <form
                id="daily-expense-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit(false)"
            >
                <Field label="Date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field label="Category" :error="form.errors.category_id">
                    <SelectInput
                        v-model="form.category_id"
                        :options="idOptions(categories)"
                        placeholder="Choose…"
                    />
                </Field>
                <div ref="descriptionField" class="sm:col-span-2">
                    <Field
                        label="What was it for?"
                        :error="form.errors.description"
                        required
                    >
                        <Input
                            v-model="form.description"
                            required
                            maxlength="255"
                            placeholder="e.g. Tea and biscuits for the meeting"
                        />
                    </Field>
                </div>
                <Field label="Amount" :error="form.errors.amount" required>
                    <Input
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                    />
                </Field>
                <Field label="Paid with" :error="form.errors.payment_method">
                    <SelectInput
                        v-model="form.payment_method"
                        :options="paymentMethods"
                    />
                </Field>
                <Field
                    label="Paid by"
                    hint="Who paid or spent it"
                    :error="form.errors.paid_by"
                >
                    <Input
                        v-model="form.paid_by"
                        list="daily-expense-people"
                        maxlength="100"
                    />
                    <datalist id="daily-expense-people">
                        <option
                            v-for="name in people"
                            :key="name"
                            :value="name"
                        />
                    </datalist>
                </Field>
                <Field
                    label="Paid to"
                    hint="Shop, person or company"
                    :error="form.errors.paid_to"
                >
                    <Input
                        v-model="form.paid_to"
                        list="daily-expense-payees"
                        maxlength="150"
                    />
                    <datalist id="daily-expense-payees">
                        <option
                            v-for="name in payees"
                            :key="name"
                            :value="name"
                        />
                    </datalist>
                </Field>
                <Field label="Receipt no." :error="form.errors.reference">
                    <Input v-model="form.reference" maxlength="100" />
                </Field>
                <Field
                    label="Receipt (photo or PDF)"
                    :error="form.errors.files ?? form.errors['files.0']"
                >
                    <Input
                        :key="fileInput"
                        type="file"
                        multiple
                        accept=".pdf,image/*"
                        @change="onFiles"
                    />
                </Field>
                <Field
                    label="Notes"
                    :error="form.errors.notes"
                    class="sm:col-span-2"
                >
                    <Textarea v-model="form.notes" rows="2" />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    v-if="!expense"
                    variant="secondary"
                    :disabled="form.processing"
                    @click="submit(true)"
                >
                    Save and add another
                </Button>
                <Button
                    type="submit"
                    form="daily-expense-form"
                    :disabled="form.processing"
                >
                    {{ expense ? 'Save changes' : 'Save' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
