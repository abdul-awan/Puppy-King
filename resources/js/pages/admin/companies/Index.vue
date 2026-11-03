<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pagination from '@/components/app/Pagination.vue';
import Pill from '@/components/app/Pill.vue';
import SearchInput from '@/components/app/SearchInput.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/composables/useFilters';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { index, show } from '@/routes/admin/companies';
import { show as showUser } from '@/routes/admin/users';
import type { AdminCompany, Paginated } from '@/types';

const props = defineProps<{
    companies: Paginated<AdminCompany>;
    filters: { search: string; status: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Platform admin', href: dashboard() },
            { title: 'Companies', href: index() },
        ],
    }),
});

const { filters } = useFilters({ ...props.filters }, () => index().url);

const statusOptions = [
    { value: 'all', label: 'All companies' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];
</script>

<template>
    <Head title="Companies · Platform admin" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Companies"
            description="Every company workspace on the platform."
        />

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search company, owner or e-mail…"
            />
            <SelectInput
                v-model="filters.status"
                :options="statusOptions"
                class="lg:w-48"
            />
        </div>

        <EmptyState
            v-if="companies.data.length === 0"
            :icon="Building2"
            title="No companies found"
            description="Try another search or status."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Company</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Owner</TableHead
                        >
                        <TableHead class="hidden text-right sm:table-cell"
                            >Members</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Since</TableHead
                        >
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="company in companies.data"
                        :key="company.slug"
                    >
                        <TableCell>
                            <Link
                                :href="show(company.slug).url"
                                class="font-medium hover:underline"
                                >{{ company.name }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                /{{ company.slug }}
                            </p>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">
                            <template v-if="company.owner">
                                <Link
                                    :href="showUser(company.owner.id).url"
                                    class="hover:underline"
                                    >{{ company.owner.name }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ company.owner.email }}
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell
                            class="hidden text-right tabular-nums sm:table-cell"
                            >{{ company.members_count }}</TableCell
                        >
                        <TableCell
                            class="hidden text-sm text-muted-foreground lg:table-cell"
                            >{{ formatDate(company.created_at) }}</TableCell
                        >
                        <TableCell class="space-x-1">
                            <Pill v-if="company.suspended_at" color="red"
                                >Suspended</Pill
                            >
                            <Pill v-else color="green">Active</Pill>
                            <Pill
                                v-if="company.members_count === 0"
                                color="gray"
                                title="Nobody can open this company any more; you can delete it"
                                >No people</Pill
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="companies.meta" :links="companies.links" />
    </div>
</template>
