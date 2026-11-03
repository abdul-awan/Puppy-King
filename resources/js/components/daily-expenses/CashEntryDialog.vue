<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
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
import { destroy, store, update } from '@/routes/cash-book';
import type { Option, PettyCashEntry } from '@/types';

/**
 * Record cash put into the cash box (or taken out of it other than for an expense).
 */
const props = defineProps<{
    entry?: PettyCashEntry | null;
    directions: Option[];
    people: string[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    date: today(),
    direction: 'in',
    amount: '' as string | number,
    description: '',
    person: '',
    notes: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        date: props.entry?.date ?? today(),
        direction: props.entry?.direction ?? 'in',
        amount: props.entry ? Number(props.entry.amount) : '',
        description: props.entry?.description ?? '',
        person: props.entry?.person ?? '',
        notes: props.entry?.notes ?? '',
    });
    form.reset();
});

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.entry) {
        form.put(update([slug.value, props.entry.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};

const remove = () => {
    if (!props.entry || !window.confirm('Delete this cash book entry?')) {
        return;
    }

    router.delete(destroy([slug.value, props.entry.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        entry ? 'Edit cash book entry' : 'Add or take out cash'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Cash put into the cash box, e.g. withdrawn from the bank or
                    the cash you start with. Expenses are added under Daily
                    expenses.
                </DialogDescription>
            </DialogHeader>

            <form
                id="cash-entry-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <Field label="Date" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field label="Type" :error="form.errors.direction">
                    <SelectInput
                        v-model="form.direction"
                        :options="directions"
                    />
                </Field>
                <Field label="Amount" :error="form.errors.amount" required>
                    <Input
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                    />
                </Field>
                <Field
                    label="Received / handed over by"
                    :error="form.errors.person"
                >
                    <Input
                        v-model="form.person"
                        list="cash-book-people"
                        maxlength="100"
                    />
                    <datalist id="cash-book-people">
                        <option
                            v-for="name in people"
                            :key="name"
                            :value="name"
                        />
                    </datalist>
                </Field>
                <Field
                    label="Description"
                    :error="form.errors.description"
                    class="sm:col-span-2"
                    required
                >
                    <Input
                        v-model="form.description"
                        required
                        maxlength="255"
                        :placeholder="
                            form.direction === 'in'
                                ? 'e.g. Cash withdrawn from the bank'
                                : 'e.g. Cash returned to the bank'
                        "
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
                <Button
                    v-if="entry"
                    variant="ghost"
                    class="mr-auto text-destructive"
                    @click="remove"
                >
                    Delete
                </Button>
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="cash-entry-form"
                    :disabled="form.processing"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
