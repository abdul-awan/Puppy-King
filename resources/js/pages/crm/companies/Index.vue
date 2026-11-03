<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownUp, Building2, Download, Plus, Upload } from '@lucide/vue';
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
import { importMethod as crmImport } from '@/routes/crm';
import { create, exportMethod, index, show } from '@/routes/companies';
import type { Company, IdName, Option, Paginated, Tag, Team } from '@/types';

const props = defineProps<{
    companies: Paginated<Company>;
    filters: {
        search: string;
        relationship: string;
        tag: number | null;
        responsible: number | null;
        archived: boolean;
        sort: string;
        direction: string;
    };
    relationships: Option[];
    tags: Tag[];
    users: IdName[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Companies', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const { filters } = useFilters(
    {
        search: props.filters.search,
        relationship: props.filters.relationship || null,
        tag: props.filters.tag,
        responsible: props.filters.responsible,
        archived: props.filters.archived,
        sort: props.filters.sort,
        direction: props.filters.direction,
    },
    () => index(slug.value).url,
);

const sortBy = (column: string) => {
    if (filters.sort === column) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = column;
        filters.direction = column === 'created_at' ? 'desc' : 'asc';
    }
};
</script>

<template>
    <Head title="Companies" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Companies"
            description="Customers, prospects, suppliers and partners."
        >
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
                    <Link :href="create(slug).url"><Plus /> New company</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search name, e-mail, phone, NTN…"
            />
            <SelectInput
                v-model="filters.relationship"
                :options="relationships"
                placeholder="All types"
                class="lg:w-40"
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
            v-if="companies.data.length === 0"
            :icon="Building2"
            title="No companies found"
            :description="
                filters.search
                    ? 'Try a different search or clear the filters.'
                    : 'Add your first customer to get started.'
            "
        >
            <Button v-if="canEdit('crm') && !filters.search" size="sm" as-child>
                <Link :href="create(slug).url"><Plus /> New company</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 uppercase"
                                @click="sortBy('name')"
                            >
                                Name <ArrowDownUp class="size-3" />
                            </button>
                        </TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Contact</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Address</TableHead
                        >
                        <TableHead class="hidden sm:table-cell"
                            >People</TableHead
                        >
                        <TableHead class="hidden xl:table-cell">
                            Account manager
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="company in companies.data"
                        :key="company.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, company.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ company.name }}
                            </Link>
                            <div
                                v-if="company.tags?.length"
                                class="mt-1 flex flex-wrap gap-1"
                            >
                                <Pill
                                    v-for="tag in company.tags"
                                    :key="tag.id"
                                    :color="tag.color"
                                >
                                    {{ tag.name }}
                                </Pill>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Pill :color="company.relationship_color">
                                {{ company.relationship_label }}
                            </Pill>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">
                            <div>{{ company.email ?? '' }}</div>
                            <div class="text-muted-foreground">
                                {{ company.phone ?? company.mobile ?? '' }}
                            </div>
                        </TableCell>
                        <TableCell
                            class="hidden max-w-64 truncate text-sm text-muted-foreground lg:table-cell"
                        >
                            {{ company.primary_address ?? '' }}
                        </TableCell>
                        <TableCell class="hidden text-sm sm:table-cell">
                            {{ company.contacts_count ?? 0 }}
                        </TableCell>
                        <TableCell class="hidden text-sm xl:table-cell">
                            {{ company.responsible_user?.name ?? '' }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="companies.meta" :links="companies.links" />
    </div>
</template>
