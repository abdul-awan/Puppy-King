<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CustomFieldInputs from '@/components/app/CustomFieldInputs.vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useTeam } from '@/composables/useTeam';
import { formatMoney } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { index, show, store, update } from '@/routes/products';
import type {
    CustomFieldDefinition,
    IdName,
    Option,
    Product,
    TaxRateOption,
} from '@/types';

const props = defineProps<{
    product?: Product | null;
    types: Option[];
    categories: IdName[];
    taxRates: TaxRateOption[];
    units: string[];
    customFields: CustomFieldDefinition[];
}>();

const { slug, currency } = useTeam();

const defaultTaxRate =
    props.taxRates.find((rate) => rate.is_default)?.id ?? null;

const form = useForm({
    type: props.product?.type ?? 'product',
    name: props.product?.name ?? '',
    code: props.product?.code ?? '',
    category_id: props.product?.category_id ?? null,
    description: props.product?.description ?? '',
    unit: props.product?.unit ?? 'pcs',
    sale_price: props.product?.sale_price ?? '',
    purchase_price: props.product?.purchase_price ?? '',
    tax_rate_id: props.product ? props.product.tax_rate_id : defaultTaxRate,
    hs_code: props.product?.hs_code ?? '',
    track_stock: props.product?.track_stock ?? false,
    stock_quantity: props.product?.stock_quantity ?? '0',
    is_active: props.product?.is_active ?? true,
    custom_fields: { ...props.product?.custom_fields },
});

const taxRateOptions = computed(() =>
    props.taxRates.map((rate) => ({ value: rate.id, label: rate.name })),
);

const margin = computed(() => {
    const sale = Number(form.sale_price);
    const purchase = Number(form.purchase_price);

    if (!form.purchase_price || !sale) {
        return null;
    }

    return ((sale - purchase) / sale) * 100;
});

const priceWithTax = computed(() => {
    const rate = props.taxRates.find((tax) => tax.id === form.tax_rate_id);

    return Number(form.sale_price || 0) * (1 + Number(rate?.rate ?? 0) / 100);
});

const submit = () => {
    if (props.product) {
        form.put(update([slug.value, props.product.id]).url, {
            preserveScroll: true,
        });
    } else {
        form.post(store(slug.value).url, { preserveScroll: true });
    }
};

const cancelUrl = props.product
    ? show([slug.value, props.product.id]).url
    : index(slug.value).url;
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Product</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    required
                    :error="form.errors.name"
                    class="sm:col-span-2"
                >
                    <Input v-model="form.name" v-focus required />
                </Field>
                <Field label="Type" :error="form.errors.type">
                    <SelectInput v-model="form.type" :options="types" />
                </Field>
                <Field label="Code / SKU" :error="form.errors.code">
                    <Input v-model="form.code" placeholder="e.g. LAP-001" />
                </Field>
                <Field label="Category" :error="form.errors.category_id">
                    <SelectInput
                        v-model="form.category_id"
                        :options="idOptions(categories)"
                        placeholder="—"
                    />
                </Field>
                <Field label="Unit" :error="form.errors.unit">
                    <Input v-model="form.unit" list="product-units" />
                    <datalist id="product-units">
                        <option
                            v-for="unit in units"
                            :key="unit"
                            :value="unit"
                        />
                    </datalist>
                </Field>
                <Field
                    label="Description"
                    :error="form.errors.description"
                    hint="Copied onto quotation and invoice lines"
                    class="sm:col-span-2"
                >
                    <Textarea v-model="form.description" rows="3" />
                </Field>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Price & tax</CardTitle>
                <CardDescription>Prices exclude sales tax.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Sale price"
                    required
                    :error="form.errors.sale_price"
                >
                    <Input
                        v-model="form.sale_price"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                    />
                </Field>
                <Field
                    label="Purchase price"
                    :error="form.errors.purchase_price"
                    :hint="
                        margin !== null
                            ? `Margin ${margin.toFixed(1)}%`
                            : 'Optional, to see your margin'
                    "
                >
                    <Input
                        v-model="form.purchase_price"
                        type="number"
                        step="0.01"
                        min="0"
                    />
                </Field>
                <Field
                    label="Default tax rate"
                    :error="form.errors.tax_rate_id"
                >
                    <SelectInput
                        v-model="form.tax_rate_id"
                        :options="taxRateOptions"
                        placeholder="No tax"
                    />
                </Field>
                <Field
                    label="HS code"
                    :error="form.errors.hs_code"
                    hint="Harmonised System code, required on sales tax invoices for goods"
                >
                    <Input
                        v-model="form.hs_code"
                        placeholder="e.g. 8471.3010"
                    />
                </Field>
                <div
                    class="text-sm text-muted-foreground sm:col-span-2 sm:pt-7"
                >
                    Price including tax:
                    <span class="font-medium text-foreground">
                        {{ formatMoney(priceWithTax, currency) }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Stock</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <Checkbox
                        :model-value="form.track_stock"
                        @update:model-value="form.track_stock = Boolean($event)"
                    />
                    Track stock for this product (invoices reduce it, credit
                    notes add it back)
                </label>
                <Field
                    v-if="form.track_stock && !product"
                    label="Opening stock"
                    :error="form.errors.stock_quantity"
                >
                    <Input
                        v-model="form.stock_quantity"
                        type="number"
                        step="0.001"
                    />
                </Field>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <Checkbox
                        :model-value="form.is_active"
                        @update:model-value="form.is_active = Boolean($event)"
                    />
                    Active (can be chosen on new documents)
                </label>
            </CardContent>
        </Card>

        <Card v-if="customFields.length > 0">
            <CardHeader>
                <CardTitle>Additional information</CardTitle>
            </CardHeader>
            <CardContent>
                <CustomFieldInputs
                    v-model="form.custom_fields"
                    :fields="customFields"
                    :errors="form.errors"
                />
            </CardContent>
        </Card>

        <div class="flex items-center justify-end gap-2">
            <Button variant="ghost" as-child>
                <Link :href="cancelUrl">Cancel</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ product ? 'Save changes' : 'Create product' }}
            </Button>
        </div>
    </form>
</template>
