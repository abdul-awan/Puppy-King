<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ContactRound, Download, Plus, Upload } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import Pill from '@/components/app/Pill.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { idOptions } from '@/lib/options';
import { show as showCompany } from '@/routes/companies';
import { create, exportMethod, index, show } from '@/routes/contacts';
import { importMethod as crmImport } from '@/routes/crm';
import type { Contact, IdName, Paginated, Tag, Team } from '@/types';

const props = defineProps<{
    contacts: Paginated<Contact>;
    filters: {
        search: string;
        company: number | null;
        tag: number | null;
        responsible: number | null;
        archived: boolean;
        sort: string;
        direction: string;
    };
    tags: Tag[];
    users: IdName[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Contacts', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const { filters } = useFilters(
    {
        search: props.filters.search,
        company: props.filters.company,
        tag: props.filters.tag,
        responsible: props.filters.responsible,
        archived: props.filters.archived,
    },
    () => index(slug.value).url,
);
</script>

<template>
    <Head title="Contacts" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader title="Contacts" description="The people you work with.">
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="
                            exportMethod(slug, {
                                query: { search: filters.search || undefined },
                            }).url
                        "
                    >
                        <Download /> Export
                    </a>
                </Button>
                <Button
                    v-if="canEdit('crm')"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="crmImport(slug).url"><Upload /> Import</Link>
                </Button>
                <Button v-if="canEdit('crm')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New contact</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search name, e-mail, phone…"
            />
            <SelectInput
                v-model="filters.tag"
                :options="idOptions(tags)"
                placeholder="All tags"
                class="lg:w-40"
            />
            <SelectInput
                v-model="filters.responsible"
                :options="idOptions(users)"
                placeholder="All account managers"
                class="lg:w-52"
            />
            <label class="flex items-center gap-2 text-sm whitespace-nowrap">
                <Checkbox
                    :model-value="Boolean(filters.archived)"
                    @update:model-value="filters.archived = Boolean($event)"
                />
                Archived
            </label>
        </div>

        <EmptyState
            v-if="contacts.data.length === 0"
            :icon="ContactRound"
            title="No contacts found"
            :description="
                filters.search
                    ? 'Try a different search.'
                    : 'Add the people you work with.'
            "
        >
            <Button v-if="canEdit('crm') && !filters.search" size="sm" as-child>
                <Link :href="create(slug).url"><Plus /> New contact</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Company</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >E-mail</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Phone</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="contact in contacts.data"
                        :key="contact.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, contact.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ contact.full_name }}
                            </Link>
                            <div
                                v-if="contact.tags?.length"
                                class="mt-1 flex flex-wrap gap-1"
                            >
                                <Pill
                                    v-for="tag in contact.tags"
                                    :key="tag.id"
                                    :color="tag.color"
                                >
                                    {{ tag.name }}
                                </Pill>
                            </div>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">
                            <template
                                v-for="(
                                    company, position
                                ) in contact.companies ?? []"
                                :key="company.id"
                            >
                                <span v-if="position > 0">, </span>
                                <Link
                                    :href="showCompany([slug, company.id]).url"
                                    class="hover:underline"
                                >
                                    {{ company.name }}
                                </Link>
                            </template>
                        </TableCell>
                        <TableCell class="hidden text-sm sm:table-cell">{{
                            contact.email ?? ''
                        }}</TableCell>
                        <TableCell class="hidden text-sm lg:table-cell">
                            {{ contact.mobile ?? contact.phone ?? '' }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="contacts.meta" :links="contacts.links" />
    </div>
</template>
