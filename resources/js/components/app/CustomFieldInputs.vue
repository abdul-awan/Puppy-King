<script setup lang="ts">
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { CustomFieldDefinition, CustomFieldValues } from '@/types';

defineProps<{
    fields: CustomFieldDefinition[];
    errors?: Partial<Record<string, string>>;
}>();

const model = defineModel<CustomFieldValues>({ required: true });

const inputType = (type: string): string =>
    ({
        number: 'number',
        date: 'date',
        email: 'email',
        url: 'url',
        phone: 'tel',
    })[type] ?? 'text';

const setValue = (key: string, value: string | number | boolean | null) => {
    model.value = { ...model.value, [key]: value };
};

const stringValue = (key: string): string => {
    const value = model.value[key];

    return value === null || value === undefined ? '' : String(value);
};
</script>

<template>
    <div v-if="fields.length > 0" class="grid gap-4 sm:grid-cols-2">
        <Field
            v-for="field in fields"
            :key="field.id"
            :label="field.type === 'checkbox' ? undefined : field.label"
            :required="field.is_required"
            :error="errors?.[`custom_fields.${field.key}`]"
            :class="{ 'sm:col-span-2': field.type === 'textarea' }"
        >
            <Textarea
                v-if="field.type === 'textarea'"
                :model-value="stringValue(field.key)"
                rows="3"
                @update:model-value="setValue(field.key, String($event))"
            />
            <SelectInput
                v-else-if="field.type === 'select'"
                :model-value="model[field.key] as string | null"
                :options="
                    field.options.map((option) => ({
                        value: option,
                        label: option,
                    }))
                "
                placeholder="—"
                @update:model-value="setValue(field.key, $event ?? null)"
            />
            <label
                v-else-if="field.type === 'checkbox'"
                class="flex items-center gap-2 pt-6 text-sm"
            >
                <Checkbox
                    :model-value="Boolean(model[field.key])"
                    @update:model-value="setValue(field.key, Boolean($event))"
                />
                {{ field.label }}
            </label>
            <Input
                v-else
                :type="inputType(field.type)"
                :model-value="stringValue(field.key)"
                @update:model-value="setValue(field.key, String($event))"
            />
        </Field>
    </div>
</template>
