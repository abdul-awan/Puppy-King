<script setup lang="ts">
import Field from '@/components/app/Field.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

/**
 * The "Terms & notes" card of the document forms.
 */
withDefaults(
    defineProps<{
        errors: Partial<Record<string, string>>;
        showTerms?: boolean;
        termsPlaceholder?: string;
    }>(),
    {
        showTerms: true,
        termsPlaceholder: '',
    },
);

const terms = defineModel<string | null>('terms', { default: '' });
const notes = defineModel<string | null>('notes', { default: '' });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ showTerms ? 'Terms & notes' : 'Notes' }}</CardTitle>
        </CardHeader>
        <CardContent
            class="grid gap-4"
            :class="{ 'sm:grid-cols-2': showTerms }"
        >
            <Field
                v-if="showTerms"
                label="Terms & conditions"
                hint="Printed at the bottom"
                :error="errors.terms"
            >
                <Textarea
                    v-model="terms"
                    rows="4"
                    :placeholder="termsPlaceholder"
                />
            </Field>
            <Field
                label="Internal notes"
                hint="Only visible to your team"
                :error="errors.notes"
            >
                <Textarea v-model="notes" rows="4" />
            </Field>
        </CardContent>
    </Card>
</template>
