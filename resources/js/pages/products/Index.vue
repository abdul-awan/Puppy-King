<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Package, Plus } from '@lucide/vue';
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
import { formatMoney, formatNumber } from '@/lib/format';
import { idOptions } from '@/lib/options';
import { create, exportMethod, index, show } from '@/routes/products';
import type { IdName, Option, Paginated, Product, Team } from '@/types';

const props = defineProps<{
    products: Paginated<Product>;
    filters: {
        search: string;
        category: number | null;
        type: string;
        inactive: boolean;
    };
    categories: IdName[];
    types: Option[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            { title: 'Products', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const { filters } = useFilters(
    {
        search: props.filters.search,
        category: props.filters.category,
        type: props.filters.type || null,
        inactive: props.filters.inactive,
    },
    () => index(slug.value).url,
);
</script>

<template>
    <Head title="Products" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            title="Products & services"
            description="Everything you sell, with prices and tax."
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
                <Button v-if="canEdit('products')" size="sm" as-child>
                    <Link :href="create(slug).url"><Plus /> New product</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput
                v-model="filters.search"
                placeholder="Search name, code, HS code…"
            />
            <SelectInput
                v-model="filters.type"
                :options="types"
                placeholder="All types"
                class="lg:w-40"
            />
            <SelectInput
                v-model="filters.category"
                :options="idOptions(categories)"
                placeholder="All categories"
                class="lg:w-48"
            />
            <label class="flex items-center gap-2 text-sm whitespace-nowrap">
                <Checkbox
                    :model-value="Boolean(filters.inactive)"
                    @update:model-value="filters.inactive = Boolean($event)"
                />
                Inactive
            </label>
        </div>

        <EmptyState
            v-if="products.data.length === 0"
            :icon="Package"
            title="No products found"
            :description="
                filters.search
                    ? 'Try a different search.'
                    : 'Add the products and services you sell.'
            "
        >
            <Button
                v-if="canEdit('products') && !filters.search"
                size="sm"
                as-child
            >
                <Link :href="create(slug).url"><Plus /> New product</Link>
            </Button>
        </EmptyState>

        <div v-else class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead class="hidden md:table-cell"
                            >Category</TableHead
                        >
                        <TableHead class="text-right">Price</TableHead>
                        <TableHead class="hidden sm:table-cell">Tax</TableHead>
                        <TableHead class="hidden text-right lg:table-cell"
                            >Stock</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="product in products.data"
                        :key="product.id"
                    >
                        <TableCell>
                            <Link
                                :href="show([slug, product.id]).url"
                                class="font-medium hover:underline"
                            >
                                {{ product.name }}
                            </Link>
                            <div
                                class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <span v-if="product.code">{{
                                    product.code
                                }}</span>
                                <Pill
                                    v-if="product.type === 'service'"
                                    color="indigo"
                                    >Service</Pill
                                >
                                <Pill v-if="!product.is_active" color="gray"
                                    >Inactive</Pill
                                >
                            </div>
                        </TableCell>
                        <TableCell class="hidden text-sm md:table-cell">{{
                            product.category?.name ?? ''
                        }}</TableCell>
                        <TableCell class="text-right text-sm whitespace-nowrap">
                            {{ formatMoney(product.sale_price, currency) }}
                            <span class="text-muted-foreground"
                                >/ {{ product.unit }}</span
                            >
                        </TableCell>
                        <TableCell class="hidden text-sm sm:table-cell">{{
                            product.tax_rate?.name ?? 'No tax'
                        }}</TableCell>
                        <TableCell
                            class="hidden text-right text-sm lg:table-cell"
                        >
                            <span
                                v-if="product.track_stock"
                                :class="{
                                    'text-destructive':
                                        Number(product.stock_quantity) <= 0,
                                }"
                            >
                                {{ formatNumber(product.stock_quantity, 3) }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :meta="products.meta" :links="products.links" />
    </div>
</template>
