<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Building, Pencil, Plus, Trash2 } from '@lucide/vue';
import Pill from '@/components/app/Pill.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import { create, destroy, edit, index } from '@/routes/settings/departments';
import type { Team } from '@/types';

type DepartmentRow = {
    id: number;
    name: string;
    legal_name: string | null;
    ntn: string | null;
    strn: string | null;
    email: string | null;
    phone: string | null;
    address: string;
    is_default: boolean;
    logo_url: string | null;
};

defineProps<{
    departments: DepartmentRow[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Company profile', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();

const remove = (department: DepartmentRow) => {
    if (window.confirm(`Delete ${department.name}?`)) {
        router.delete(destroy([slug.value, department.id]).url, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Company profile" />

    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Company profile"
                description="Your business details as printed on quotations and invoices. Add more profiles if you invoice from several legal entities or branches — each keeps its own numbering."
            />
            <Button v-if="canEdit('settings')" size="sm" as-child>
                <Link :href="create(slug).url"><Plus /> Add profile</Link>
            </Button>
        </div>

        <div class="space-y-3">
            <div
                v-for="department in departments"
                :key="department.id"
                class="flex items-start justify-between gap-4 rounded-lg border p-4"
            >
                <div class="flex min-w-0 gap-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted"
                    >
                        <img
                            v-if="department.logo_url"
                            :src="department.logo_url"
                            alt="Logo"
                            class="max-h-full max-w-full object-contain"
                        />
                        <Building v-else class="size-6 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">
                                {{ department.legal_name || department.name }}
                            </p>
                            <Pill v-if="department.is_default" color="green"
                                >Default</Pill
                            >
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{
                                [
                                    department.ntn
                                        ? `NTN ${department.ntn}`
                                        : null,
                                    department.strn
                                        ? `STRN ${department.strn}`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || 'No tax numbers yet'
                            }}
                        </p>
                        <p
                            v-if="department.address"
                            class="truncate text-sm text-muted-foreground"
                        >
                            {{ department.address }}
                        </p>
                        <p
                            v-if="!department.ntn"
                            class="text-sm text-amber-700 dark:text-amber-400"
                        >
                            Add your NTN and STRN so they appear on your sales
                            tax invoices.
                        </p>
                    </div>
                </div>
                <div v-if="canEdit('settings')" class="flex shrink-0 gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Edit"
                        as-child
                    >
                        <Link :href="edit([slug, department.id]).url"
                            ><Pencil
                        /></Link>
                    </Button>
                    <Button
                        v-if="!department.is_default"
                        variant="ghost"
                        size="icon-sm"
                        title="Delete"
                        @click="remove(department)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
