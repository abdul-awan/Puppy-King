<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
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
import { index, show } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types';

const props = defineProps<{
    users: Paginated<AdminUser>;
    filters: { search: string; filter: string };
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Platform admin', href: dashboard() },
            { title: 'Users', href: index() },
        ],
    }),
});

const { filters } = useFilters({ ...props.filters }, () => index().url);

const filterOptions = [
    { value: 'all', label: 'All users' },
    { value: 'admins', label: 'Platform administrators' },
    { value: 'unverified', label: 'E-mail not confirmed' },
];
</script>

<template>
    <Head title="Users · Platform admin" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Everybody with an account on the platform."
        />

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search name or e-mail…"
            />
            <SelectInput
                v-model="filters.filter"
                :options="filterOptions"
                class="lg:w-56"
            />
        </div>

        <EmptyState
            v-if="users.data.length === 0"
            :icon="Users"
            title="No users found"
            description="Try another search or filter."
        />

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>User</TableHead>
                        <TableHead class="hidden text-right sm:table-cell"
                            >Companies</TableHead
                        >
                        <TableHead class="hidden lg:table-cell"
                            >Since</TableHead
                        >
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="user in users.data" :key="user.id">
                        <TableCell>
                            <Link
                                :href="show(user.id).url"
                                class="font-medium hover:underline"
                                >{{ user.name }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </TableCell>
                        <TableCell
                            class="hidden text-right tabular-nums sm:table-cell"
                            >{{ user.teams_count }}</TableCell
                        >
                        <TableCell
                            class="hidden text-sm text-muted-foreground lg:table-cell"
                            >{{ formatDate(user.created_at) }}</TableCell
                        >
                        <TableCell class="space-x-1 text-right">
                            <Pill v-if="user.is_platform_admin" color="purple"
                                >Platform admin</Pill
                            >
                            <Pill v-if="!user.verified" color="amber"
                                >Unconfirmed</Pill
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="users.meta" :links="users.links" />
    </div>
</template>
