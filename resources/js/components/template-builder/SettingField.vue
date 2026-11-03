<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import ImagePicker from './ImagePicker.vue';
import type { FieldDefinition, Registry, UploadedImage } from './types';

/**
 * One setting of an element, row, cell or the page, drawn from its definition in the registry.
 */
const props = defineProps<{
    field: FieldDefinition;
    modelValue: unknown;
    colors: Registry['colors'];
    images?: UploadedImage[];
    uploadUrl?: string;
    placeholders?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: unknown];
    uploaded: [image: UploadedImage];
}>();

const options = computed(() =>
    Object.entries(props.field.options ?? {}).map(([value, label]) => ({
        value,
        label,
    })),
);

// Colours: a token of the palette or an own #rrggbb colour.
const tokens = computed(() => props.colors[props.field.tokens ?? 'text'] ?? {});
const isOwnColour = computed(
    () =>
        typeof props.modelValue === 'string' &&
        props.modelValue.startsWith('#'),
);
const colourChoice = computed({
    get: () => (isOwnColour.value ? 'own' : String(props.modelValue ?? '')),
    set: (value: string) =>
        emit('update:modelValue', value === 'own' ? '#1f3a5f' : value),
});
const colourOptions = computed(() => [
    ...Object.entries(tokens.value).map(([value, label]) => ({ value, label })),
    { value: 'own', label: 'Own colour…' },
]);

const number = computed({
    get: () => Number(props.modelValue ?? 0),
    set: (value: number | string) => {
        const parsed = Number(value);

        if (!Number.isNaN(parsed)) {
            emit(
                'update:modelValue',
                Math.min(
                    props.field.max ?? parsed,
                    Math.max(props.field.min ?? parsed, parsed),
                ),
            );
        }
    },
});

const labels = computed(() =>
    Array.isArray(props.modelValue) ? (props.modelValue as string[]) : [],
);

const setLabel = (index: number, value: string) => {
    const next = [...labels.value];
    next[index] = value;
    emit('update:modelValue', next);
};
</script>

<template>
    <!-- On / off -->
    <label v-if="field.kind === 'bool'" class="flex items-start gap-2 text-sm">
        <Checkbox
            :model-value="Boolean(modelValue)"
            class="mt-0.5"
            @update:model-value="emit('update:modelValue', Boolean($event))"
        />
        <span>
            {{ field.label }}
            <span
                v-if="field.hint"
                class="block text-xs text-muted-foreground"
                >{{ field.hint }}</span
            >
        </span>
    </label>

    <div v-else class="grid gap-1.5">
        <span class="text-xs font-medium text-muted-foreground">{{
            field.label
        }}</span>

        <SelectInput
            v-if="field.kind === 'choice'"
            :model-value="String(modelValue ?? '')"
            :options="options"
            class="h-8"
            @update:model-value="emit('update:modelValue', $event)"
        />

        <div
            v-else-if="field.kind === 'number'"
            class="flex items-center gap-2"
        >
            <input
                v-model.number="number"
                type="range"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                class="h-1.5 min-w-0 flex-1 cursor-pointer accent-primary"
            />
            <Input
                v-model.number="number"
                type="number"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                class="h-8 w-20"
            />
        </div>

        <div v-else-if="field.kind === 'color'" class="grid gap-1.5">
            <SelectInput
                v-model="colourChoice"
                :options="colourOptions"
                class="h-8"
            />
            <div v-if="isOwnColour" class="flex items-center gap-2">
                <input
                    type="color"
                    :value="String(modelValue)"
                    class="size-8 cursor-pointer rounded border bg-transparent p-0.5"
                    @input="
                        emit(
                            'update:modelValue',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <Input
                    :model-value="String(modelValue)"
                    maxlength="7"
                    class="h-8 w-28 font-mono"
                    @update:model-value="
                        /^#[0-9a-fA-F]{6}$/.test(String($event)) &&
                        emit('update:modelValue', String($event).toLowerCase())
                    "
                />
            </div>
        </div>

        <Input
            v-else-if="field.kind === 'text'"
            :model-value="String(modelValue ?? '')"
            :maxlength="field.max"
            :placeholder="field.placeholder"
            class="h-8"
            @update:model-value="emit('update:modelValue', String($event))"
        />

        <template v-else-if="field.kind === 'textarea'">
            <Textarea
                :model-value="String(modelValue ?? '')"
                :maxlength="field.max"
                rows="4"
                @update:model-value="emit('update:modelValue', String($event))"
            />
            <details v-if="placeholders" class="text-xs text-muted-foreground">
                <summary class="cursor-pointer">
                    Placeholders you can use
                </summary>
                <ul class="mt-1 space-y-0.5">
                    <li
                        v-for="(meaning, placeholder) in placeholders"
                        :key="placeholder"
                    >
                        <code class="rounded bg-muted px-1">{{
                            placeholder
                        }}</code>
                        {{ meaning }}
                    </li>
                </ul>
            </details>
        </template>

        <div v-else-if="field.kind === 'labels'" class="grid gap-1.5">
            <div
                v-for="(label, index) in labels"
                :key="index"
                class="flex items-center gap-1"
            >
                <Input
                    :model-value="label"
                    :maxlength="field.length"
                    class="h-8"
                    @update:model-value="setLabel(index, String($event))"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    title="Remove"
                    @click="
                        emit(
                            'update:modelValue',
                            labels.filter((_, position) => position !== index),
                        )
                    "
                >
                    <X class="size-4" />
                </Button>
            </div>
            <Button
                v-if="labels.length < (field.max ?? 3)"
                type="button"
                variant="outline"
                size="sm"
                class="justify-self-start"
                @click="emit('update:modelValue', [...labels, 'Signature'])"
            >
                <Plus /> Add a line
            </Button>
        </div>

        <ImagePicker
            v-else-if="field.kind === 'image'"
            :model-value="String(modelValue ?? '')"
            :images="images ?? []"
            :upload-url="uploadUrl ?? ''"
            @update:model-value="emit('update:modelValue', $event)"
            @uploaded="emit('uploaded', $event)"
        />

        <span v-if="field.hint" class="text-xs text-muted-foreground">{{
            field.hint
        }}</span>
    </div>
</template>
