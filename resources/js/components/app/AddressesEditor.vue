<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Address, AddressType, Option } from '@/types';

defineProps<{
    provinces: Option[];
    addressTypes: Option<AddressType>[];
    errors?: Partial<Record<string, string>>;
}>();

const model = defineModel<Address[]>({ required: true });

const add = () => {
    const used = model.value.map((address) => address.type);

    model.value = [
        ...model.value,
        {
            type: used.includes('primary') ? 'billing' : 'primary',
            line_1: '',
            line_2: '',
            city: '',
            province: null,
            postal_code: '',
            country: 'Pakistan',
        },
    ];
};

const remove = (index: number) => {
    model.value = model.value.filter((_, position) => position !== index);
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(address, index) in model"
            :key="index"
            class="space-y-3 rounded-lg border p-4"
        >
            <div class="flex items-center justify-between gap-2">
                <SelectInput
                    v-model="address.type"
                    :options="addressTypes"
                    class="w-48"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    title="Remove address"
                    @click="remove(index)"
                >
                    <Trash2 />
                </Button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <Field
                    label="Address line 1"
                    :error="errors?.[`addresses.${index}.line_1`]"
                    class="sm:col-span-2"
                >
                    <Input
                        v-model="address.line_1"
                        placeholder="Street, building, office"
                    />
                </Field>
                <Field
                    label="Address line 2"
                    :error="errors?.[`addresses.${index}.line_2`]"
                    class="sm:col-span-2"
                >
                    <Input
                        v-model="address.line_2"
                        placeholder="Area, block, sector"
                    />
                </Field>
                <Field
                    label="City"
                    :error="errors?.[`addresses.${index}.city`]"
                >
                    <Input v-model="address.city" placeholder="e.g. Lahore" />
                </Field>
                <Field
                    label="Province"
                    :error="errors?.[`addresses.${index}.province`]"
                >
                    <SelectInput
                        v-model="address.province"
                        :options="provinces"
                        placeholder="—"
                    />
                </Field>
                <Field
                    label="Postal code"
                    :error="errors?.[`addresses.${index}.postal_code`]"
                >
                    <Input v-model="address.postal_code" />
                </Field>
                <Field
                    label="Country"
                    :error="errors?.[`addresses.${index}.country`]"
                >
                    <Input v-model="address.country" />
                </Field>
            </div>
        </div>

        <Button type="button" variant="outline" size="sm" @click="add">
            <Plus /> Add address
        </Button>
    </div>
</template>
