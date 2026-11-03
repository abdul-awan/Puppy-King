<script setup lang="ts">
import {
    AlignLeft,
    ArrowDown,
    ArrowUp,
    Heading,
    Plus,
    Text,
    Trash2,
} from '@lucide/vue';
import EntitySelect from '@/components/app/EntitySelect.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatMoney } from '@/lib/format';
import { emptyLine } from '@/lib/documents';
import type { EditableLine } from '@/lib/documents';
import type { LookupResult, TaxRateOption } from '@/types';

const props = defineProps<{
    taxRates: TaxRateOption[];
    currency: string;
    lineTotals: number[];
    errors?: Partial<Record<string, string>>;
}>();

const lines = defineModel<EditableLine[]>({ required: true });

const defaultTaxRateId = () =>
    props.taxRates.find((rate) => rate.is_default)?.id ?? null;

const taxOptions = () =>
    props.taxRates.map((rate) => ({ value: rate.id, label: rate.name }));

const add = (type: EditableLine['type']) => {
    lines.value = [...lines.value, emptyLine(type, defaultTaxRateId())];
};

const remove = (index: number) => {
    lines.value = lines.value.filter((_, position) => position !== index);
};

const move = (index: number, direction: -1 | 1) => {
    const target = index + direction;

    if (target < 0 || target >= lines.value.length) {
        return;
    }

    const copy = [...lines.value];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    lines.value = copy;
};

const pickProduct = (line: EditableLine, item: LookupResult | null) => {
    if (!item?.data) {
        return;
    }

    const data = item.data as {
        name: string;
        description: string | null;
        unit: string;
        sale_price: string;
        tax_rate_id: number | null;
        hs_code: string | null;
    };

    line.product_label = item.label;
    line.description = data.name;
    line.details = data.description ?? '';
    line.show_details = Boolean(data.description);
    line.unit = data.unit;
    line.unit_price = Number(data.sale_price);
    line.tax_rate_id = data.tax_rate_id;
    line.hs_code = data.hs_code ?? '';
};

const error = (index: number, field: string) =>
    props.errors?.[`lines.${index}.${field}`];
</script>

<template>
    <div class="space-y-2">
        <div
            class="hidden grid-cols-[minmax(0,2fr)_minmax(0,3fr)_5rem_4.5rem_7rem_4.5rem_8rem_7rem_4.5rem] gap-2 px-1 text-xs font-medium text-muted-foreground uppercase lg:grid"
        >
            <span>Product</span>
            <span>Description</span>
            <span class="text-right">Qty</span>
            <span>Unit</span>
            <span class="text-right">Price</span>
            <span class="text-right">Disc %</span>
            <span>Tax</span>
            <span class="text-right">Amount</span>
            <span />
        </div>

        <div
            v-for="(line, index) in lines"
            :key="line.key"
            class="rounded-md border bg-background p-2"
            :class="{ 'bg-muted/40': line.type === 'section' }"
        >
            <div
                v-if="line.type === 'item'"
                class="grid grid-cols-2 gap-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_5rem_4.5rem_7rem_4.5rem_8rem_7rem_4.5rem] lg:items-start"
            >
                <div class="col-span-2 lg:col-span-1">
                    <EntitySelect
                        v-model="line.product_id"
                        type="products"
                        :initial-label="line.product_label"
                        placeholder="Product (optional)"
                        clearable
                        @select="pickProduct(line, $event)"
                    />
                </div>
                <div class="col-span-2 space-y-1 lg:col-span-1">
                    <Input
                        v-model="line.description"
                        placeholder="Description"
                        :aria-invalid="
                            Boolean(error(index, 'description')) || undefined
                        "
                    />
                    <Textarea
                        v-if="line.show_details"
                        v-model="line.details"
                        rows="2"
                        placeholder="Extra details printed under the line"
                        class="text-sm"
                    />
                    <p
                        v-if="error(index, 'description')"
                        class="text-xs text-destructive"
                    >
                        {{ error(index, 'description') }}
                    </p>
                </div>
                <Input
                    v-model="line.quantity"
                    type="number"
                    step="any"
                    class="text-right"
                    aria-label="Quantity"
                    :aria-invalid="
                        Boolean(error(index, 'quantity')) || undefined
                    "
                />
                <Input
                    v-model="line.unit"
                    placeholder="pcs"
                    aria-label="Unit"
                />
                <Input
                    v-model="line.unit_price"
                    type="number"
                    step="any"
                    class="text-right"
                    placeholder="0.00"
                    aria-label="Unit price"
                    :aria-invalid="
                        Boolean(error(index, 'unit_price')) || undefined
                    "
                />
                <Input
                    v-model="line.discount_percent"
                    type="number"
                    step="any"
                    min="0"
                    max="100"
                    class="text-right"
                    placeholder="0"
                    aria-label="Discount %"
                />
                <SelectInput
                    v-model="line.tax_rate_id"
                    :options="taxOptions()"
                    placeholder="No tax"
                    aria-label="Tax"
                />
                <div
                    class="flex h-9 items-center justify-end text-sm font-medium"
                >
                    {{ formatMoney(lineTotals[index] ?? 0, currency) }}
                </div>
                <div class="col-span-2 flex justify-end gap-0.5 lg:col-span-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        title="Extra details"
                        @click="line.show_details = !line.show_details"
                    >
                        <AlignLeft />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        title="Remove"
                        @click="remove(index)"
                    >
                        <Trash2 />
                    </Button>
                </div>
                <div class="col-span-2 flex items-center gap-2 lg:col-span-9">
                    <Input
                        v-model="line.hs_code"
                        placeholder="HS code"
                        class="h-7 w-32 text-xs"
                        aria-label="HS code"
                    />
                    <div class="ml-auto flex gap-0.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            title="Move up"
                            :disabled="index === 0"
                            @click="move(index, -1)"
                        >
                            <ArrowUp />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            title="Move down"
                            :disabled="index === lines.length - 1"
                            @click="move(index, 1)"
                        >
                            <ArrowDown />
                        </Button>
                    </div>
                </div>
            </div>

            <div v-else class="flex items-start gap-2">
                <Heading
                    v-if="line.type === 'section'"
                    class="mt-2.5 size-4 shrink-0 text-muted-foreground"
                />
                <Text
                    v-else
                    class="mt-2.5 size-4 shrink-0 text-muted-foreground"
                />
                <Input
                    v-if="line.type === 'section'"
                    v-model="line.description"
                    placeholder="Section heading, e.g. Hardware"
                    class="font-medium"
                />
                <Textarea
                    v-else
                    v-model="line.description"
                    rows="2"
                    placeholder="Text printed on the document"
                />
                <div class="flex shrink-0 gap-0.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :disabled="index === 0"
                        @click="move(index, -1)"
                    >
                        <ArrowUp />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :disabled="index === lines.length - 1"
                        @click="move(index, 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        title="Remove"
                        @click="remove(index)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
        </div>

        <p v-if="errors?.lines" class="text-sm text-destructive">
            {{ errors.lines }}
        </p>

        <div class="flex flex-wrap gap-2 pt-1">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="add('item')"
            >
                <Plus /> Add line
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="add('section')"
            >
                <Heading /> Add section
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="add('text')"
            >
                <Text /> Add text
            </Button>
        </div>
    </div>
</template>
