<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, FileSpreadsheet, Upload } from '@lucide/vue';
import Field from '@/components/app/Field.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTeam } from '@/composables/useTeam';
import { index as companiesIndex } from '@/routes/companies';
import { index as contactsIndex } from '@/routes/contacts';
import { importMethod } from '@/routes/crm';
import { store } from '@/routes/crm/import';
import type { Team } from '@/types';

defineProps<{
    result: {
        type: 'companies' | 'contacts';
        total: number;
        created: number;
        skipped: number;
        messages: string[];
    } | null;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Import', href: importMethod(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();

const form = useForm<{ type: 'companies' | 'contacts'; file: File | null }>({
    type: 'companies',
    file: null,
});

const submit = () => {
    form.post(store(slug.value).url, {
        forceFormData: true,
        onSuccess: () => form.reset('file'),
    });
};

const columns = {
    companies:
        'name, type, email, phone, mobile, website, ntn, strn, cnic, industry, address, city, province, postal_code, tags',
    contacts:
        'first_name, last_name (or name), email, phone, mobile, cnic, company, position, address, city, province, tags',
};
</script>

<template>
    <Head title="Import" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="Import companies or contacts"
            description="Upload a CSV file (e.g. saved from Excel). The first row must contain the column names."
        />

        <Card v-if="result">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CheckCircle2 class="size-5 text-green-600" /> Import
                    finished
                </CardTitle>
                <CardDescription>
                    {{ result.created }} of {{ result.total }}
                    {{ result.type }} imported, {{ result.skipped }} skipped.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <ul
                    v-if="result.messages.length"
                    class="max-h-64 space-y-1 overflow-y-auto rounded-md border bg-muted/40 p-3 text-sm"
                >
                    <li v-for="message in result.messages" :key="message">
                        {{ message }}
                    </li>
                </ul>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            result.type === 'companies'
                                ? companiesIndex(slug).url
                                : contactsIndex(slug).url
                        "
                    >
                        View {{ result.type }}
                    </Link>
                </Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Upload a file</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <Field
                        label="What are you importing?"
                        :error="form.errors.type"
                    >
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.type"
                                    type="radio"
                                    value="companies"
                                />
                                Companies
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.type"
                                    type="radio"
                                    value="contacts"
                                />
                                Contacts
                            </label>
                        </div>
                    </Field>

                    <div class="rounded-md border bg-muted/40 p-3 text-sm">
                        <p class="flex items-center gap-2 font-medium">
                            <FileSpreadsheet class="size-4" /> Recognised
                            columns
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{ columns[form.type] }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            Rows whose name (or e-mail for contacts) already
                            exists are skipped.
                        </p>
                    </div>

                    <Field label="CSV file" :error="form.errors.file" required>
                        <input
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                            @change="
                                form.file =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                    </Field>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.file"
                        >
                            <Upload />
                            {{ form.processing ? 'Importing…' : 'Import' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
