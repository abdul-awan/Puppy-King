<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Field from '@/components/app/Field.vue';
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
import { today } from '@/lib/format';

/**
 * Record that salaries were paid (all of a payroll, or one payslip).
 */
const props = defineProps<{
    title: string;
    description: string;
    url: string;
    date?: string | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({ date: today(), reference: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.defaults({ date: props.date ?? today(), reference: '' });
        form.reset();
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
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <form
                id="pay-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <Field label="Paid on" :error="form.errors.date" required>
                    <Input v-model="form.date" type="date" required />
                </Field>
                <Field label="Reference" :error="form.errors.reference">
                    <Input
                        v-model="form.reference"
                        maxlength="100"
                        placeholder="Bank batch, cheque no.…"
                    />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="pay-form"
                    :disabled="form.processing"
                >
                    Mark as paid
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
