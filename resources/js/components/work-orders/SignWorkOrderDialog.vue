<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Field from '@/components/app/Field.vue';
import SignaturePad from '@/components/documents/SignaturePad.vue';
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
import { sign } from '@/routes/work-orders';
import type { WorkOrder } from '@/types';

/**
 * The customer signs the work order on the spot (on the technician's phone or tablet).
 */
const props = defineProps<{
    workOrder: WorkOrder;
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    name: '',
    signature: '',
    remarks: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.reset();
    form.clearErrors();
    form.name = props.workOrder.contact?.name ?? '';
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remarks: data.remarks || null,
    })).post(sign([slug.value, props.workOrder.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle
                    >Sign work order {{ workOrder.number }}</DialogTitle
                >
                <DialogDescription>
                    Hand the device to the customer. By signing, the customer
                    confirms the work was carried out as described; the work
                    order is then completed and no longer changes.
                </DialogDescription>
            </DialogHeader>

            <form
                id="sign-work-order-form"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <Field
                    label="Name of the customer"
                    :error="form.errors.name"
                    required
                >
                    <Input v-model="form.name" required autocomplete="off" />
                </Field>
                <Field
                    label="Signature"
                    :error="form.errors.signature"
                    required
                >
                    <SignaturePad v-model="form.signature" />
                </Field>
                <Field
                    label="Remarks of the customer"
                    hint="Optional"
                    :error="form.errors.remarks"
                >
                    <Textarea v-model="form.remarks" rows="2" />
                </Field>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="sign-work-order-form"
                    :disabled="form.processing || !form.signature"
                >
                    Sign
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
