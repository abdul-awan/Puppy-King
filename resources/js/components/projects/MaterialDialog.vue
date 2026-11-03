<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTeam } from '@/composables/useTeam';
import { today } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { update } from '@/routes/materials';
import { store } from '@/routes/projects/materials';
import {
    store as storeForWorkOrder,
    update as updateForWorkOrder,
} from '@/routes/work-orders/materials';
import type { LookupResult, ProjectMaterial, TaxRateOption } from '@/types';

/**
 * Add or edit material used on a project, or on a work order (when workOrderId is given).
 */
const props = defineProps<{
    projectId?: number | null;
    workOrderId?: number | null;
    material?: ProjectMaterial | null;
    taxRates: TaxRateOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const { slug } = useTeam();

const form = useForm({
    product_id: null as number | null,
    date: today(),
    description: '',
    quantity: '1' as string | number,
    unit: '',
    unit_price: '' as string | number,
    unit_cost: '' as string | number,
    tax_rate_id: null as number | null,
    is_billable: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.product_id = props.material?.product_id ?? null;
    form.date = props.material?.date ?? today();
    form.description = props.material?.description ?? '';
    form.quantity = props.material ? Number(props.material.quantity) : '1';
    form.unit = props.material?.unit ?? '';
    form.unit_price = props.material ? Number(props.material.unit_price) : '';
    form.unit_cost = props.material?.unit_cost
        ? Number(props.material.unit_cost)
        : '';
    form.tax_rate_id =
        props.material?.tax_rate_id ??
        props.taxRates.find((rate) => rate.is_default)?.id ??
        null;
    form.is_billable = props.material?.is_billable ?? true;
});

const onProduct = (item: LookupResult | null) => {
    const data = item?.data as
        | {
              name?: string;
              unit?: string;
              sale_price?: string;
              purchase_price?: string | null;
              tax_rate_id?: number | null;
          }
        | undefined;

    if (!data) {
        return;
    }

    form.description = data.name ?? form.description;
    form.unit = data.unit ?? form.unit;
    form.unit_price = data.sale_price
        ? Number(data.sale_price)
        : form.unit_price;
    form.unit_cost = data.purchase_price ? Number(data.purchase_price) : '';
    form.tax_rate_id = data.tax_rate_id ?? form.tax_rate_id;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        unit: data.unit || null,
        unit_cost: data.unit_cost === '' ? null : data.unit_cost,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    const workOrderId = props.workOrderId;

    if (props.material) {
        form.put(
            workOrderId
                ? updateForWorkOrder([
                      slug.value,
                      workOrderId,
                      props.material.id,
                  ]).url
                : update([slug.value, props.material.id]).url,
            options,
        );
    } else if (workOrderId) {
        form.post(storeForWorkOrder([slug.value, workOrderId]).url, options);
    } else if (props.projectId) {
        form.post(store([slug.value, props.projectId]).url, options);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    material ? 'Edit material' : 'Add material'
                }}</DialogTitle>
            </DialogHeader>

            <form id="material-form" class="space-y-4" @submit.prevent="submit">
                <Field label="Product" :error="form.errors.product_id">
                    <EntitySelect
                        v-model="form.product_id"
                        type="products"
                        placeholder="Choose from your products…"
                        clearable
                        @select="onProduct"
                    />
                </Field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Description"
                        :error="form.errors.description"
                        class="sm:col-span-2"
                        required
                    >
                        <Input v-model="form.description" required />
                    </Field>
                    <Field label="Date" :error="form.errors.date" required>
                        <Input v-model="form.date" type="date" />
                    </Field>
                    <Field
                        label="Quantity"
                        :error="form.errors.quantity"
                        required
                    >
                        <div class="flex gap-2">
                            <Input
                                v-model="form.quantity"
                                type="number"
                                step="any"
                                min="0"
                            />
                            <Input
                                v-model="form.unit"
                                placeholder="unit"
                                class="w-24"
                                aria-label="Unit"
                            />
                        </div>
                    </Field>
                    <Field
                        label="Sale price per unit"
                        :error="form.errors.unit_price"
                        required
                    >
                        <Input
                            v-model="form.unit_price"
                            type="number"
                            step="any"
                            min="0"
                        />
                    </Field>
                    <Field
                        label="Cost per unit"
                        hint="For your margin"
                        :error="form.errors.unit_cost"
                    >
                        <Input
                            v-model="form.unit_cost"
                            type="number"
                            step="any"
                            min="0"
                        />
                    </Field>
                    <Field label="Tax" :error="form.errors.tax_rate_id">
                        <SelectInput
                            v-model="form.tax_rate_id"
                            :options="idOptions(taxRates)"
                            placeholder="No tax"
                        />
                    </Field>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.is_billable"
                        @update:model-value="form.is_billable = Boolean($event)"
                    />
                    Invoice to the customer
                </label>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button
                    type="submit"
                    form="material-form"
                    :disabled="form.processing"
                >
                    {{ material ? 'Save' : 'Add' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
