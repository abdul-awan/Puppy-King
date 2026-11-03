<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Field from '@/components/app/Field.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import Heading from '@/components/Heading.vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

type Value = string | number | boolean | null | string[];
type Row = Record<string, unknown> & { id: number };

export type ResourceField = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'select' | 'checkbox' | 'textarea' | 'lines';
    options?: { value: string | number; label: string }[];
    placeholder?: string;
    hint?: string;
    required?: boolean;
    step?: string;
    wide?: boolean;
    lockedOnEdit?: boolean;
    visible?: (data: Record<string, Value>) => boolean;
};

export type ResourceColumn = {
    key: string;
    label: string;
    class?: string;
};

const props = defineProps<{
    title: string;
    description?: string;
    itemLabel: string;
    items: Row[];
    columns: ResourceColumn[];
    fields: ResourceField[];
    defaults: Record<string, Value>;
    storeUrl: string;
    updateUrl: (item: Row) => string;
    destroyUrl: (item: Row) => string;
    canWrite: boolean;
    emptyText?: string;
}>();

const open = ref(false);
const editing = ref<Row | null>(null);
const form = useForm<Record<string, Value>>({ ...props.defaults });

const visibleFields = computed(() =>
    props.fields.filter(
        (field) => !field.visible || field.visible(form.data()),
    ),
);

const startCreate = () => {
    editing.value = null;
    form.clearErrors();
    form.defaults({ ...props.defaults });
    form.reset();
    open.value = true;
};

const startEdit = (item: Row) => {
    editing.value = item;
    form.clearErrors();

    const values: Record<string, Value> = { ...props.defaults };

    for (const field of props.fields) {
        const value = item[field.key];

        values[field.key] =
            field.type === 'lines' && Array.isArray(value)
                ? value.join('\n')
                : ((value ?? props.defaults[field.key] ?? null) as Value);
    }

    form.defaults(values);
    form.reset();
    open.value = true;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (editing.value) {
        form.put(props.updateUrl(editing.value), options);
    } else {
        form.post(props.storeUrl, options);
    }
};

const remove = (item: Row) => {
    if (!window.confirm(`Delete this ${props.itemLabel}?`)) {
        return;
    }

    router.delete(props.destroyUrl(item), { preserveScroll: true });
};

const cell = (item: Row, key: string): string => {
    const value = item[key];

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return value === null || value === undefined || value === ''
        ? '—'
        : String(value);
};
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="title"
                :description="description"
            />
            <Button v-if="canWrite" size="sm" @click="startCreate">
                <Plus /> Add {{ itemLabel }}
            </Button>
        </div>

        <slot name="intro" />

        <p
            v-if="items.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{ emptyText ?? `No ${itemLabel}s yet.` }}
        </p>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="column in columns"
                            :key="column.key"
                            :class="column.class"
                        >
                            {{ column.label }}
                        </TableHead>
                        <TableHead v-if="canWrite" class="w-24" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="item in items" :key="item.id">
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="column.class"
                        >
                            <slot :name="`cell-${column.key}`" :item="item">
                                {{ cell(item, column.key) }}
                            </slot>
                        </TableCell>
                        <TableCell v-if="canWrite" class="text-right">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Edit"
                                @click="startEdit(item)"
                            >
                                <Pencil />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                title="Delete"
                                @click="remove(item)"
                            >
                                <Trash2 />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? `Edit ${itemLabel}` : `New ${itemLabel}` }}
                    </DialogTitle>
                </DialogHeader>

                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <template v-for="field in visibleFields" :key="field.key">
                        <label
                            v-if="field.type === 'checkbox'"
                            class="flex items-center gap-2 text-sm sm:col-span-2"
                        >
                            <Checkbox
                                :model-value="Boolean(form[field.key])"
                                @update:model-value="
                                    form[field.key] = Boolean($event)
                                "
                            />
                            {{ field.label }}
                        </label>
                        <Field
                            v-else
                            :label="field.label"
                            :required="field.required"
                            :hint="field.hint"
                            :error="form.errors[field.key]"
                            :class="{
                                'sm:col-span-2':
                                    field.wide ||
                                    field.type === 'textarea' ||
                                    field.type === 'lines',
                            }"
                        >
                            <SelectInput
                                v-if="field.type === 'select'"
                                :model-value="
                                    form[field.key] as string | number | null
                                "
                                :options="field.options ?? []"
                                :placeholder="field.required ? undefined : '—'"
                                :disabled="
                                    Boolean(editing && field.lockedOnEdit)
                                "
                                @update:model-value="
                                    form[field.key] = $event ?? null
                                "
                            />
                            <Textarea
                                v-else-if="
                                    field.type === 'textarea' ||
                                    field.type === 'lines'
                                "
                                :model-value="
                                    (form[field.key] as string | null) ?? ''
                                "
                                rows="4"
                                :placeholder="field.placeholder"
                                @update:model-value="
                                    form[field.key] = String($event)
                                "
                            />
                            <Input
                                v-else
                                :model-value="
                                    (form[field.key] as
                                        string | number | null) ?? ''
                                "
                                :type="
                                    field.type === 'number' ? 'number' : 'text'
                                "
                                :step="field.step"
                                :placeholder="field.placeholder"
                                :required="field.required"
                                @update:model-value="form[field.key] = $event"
                            />
                        </Field>
                    </template>

                    <DialogFooter class="gap-2 sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="open = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
