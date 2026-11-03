<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MailCheck, ShieldCheck, ShieldOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import { show as showCompany } from '@/routes/admin/companies';
import { grant, index, revoke, show, verify } from '@/routes/admin/users';
import type { AdminUser } from '@/types';

type User = AdminUser & {
    two_factor: boolean;
    last_active_at: string | null;
    is_you: boolean;
};

const props = defineProps<{
    user: User;
    companies: {
        slug: string;
        name: string;
        role_label: string;
        suspended: boolean;
        is_current: boolean;
    }[];
}>();

defineOptions({
    layout: (props: { user: User }) => ({
        breadcrumbs: [
            { title: 'Platform admin', href: dashboard() },
            { title: 'Users', href: index() },
            { title: props.user.name, href: show(props.user.id) },
        ],
    }),
});

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const granting = ref(false);
const revoking = ref(false);

const confirmGrant = () => {
    router.post(grant(props.user.id).url, {}, { preserveScroll: true });
};

const confirmRevoke = () => {
    router.delete(revoke(props.user.id).url, { preserveScroll: true });
};

const confirmEmail = () => {
    router.post(verify(props.user.id).url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${user.name} · Platform admin`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="user.name">
            <template #badges>
                <Pill v-if="user.is_platform_admin" color="purple"
                    >Platform admin</Pill
                >
                <Pill v-if="!user.verified" color="amber"
                    >E-mail not confirmed</Pill
                >
                <Pill v-if="user.is_you" color="gray">You</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">{{ user.email }}</p>
            </template>
            <template #actions>
                <Button
                    v-if="!user.verified"
                    variant="outline"
                    size="sm"
                    @click="confirmEmail"
                >
                    <MailCheck /> Confirm e-mail address
                </Button>
                <Button
                    v-if="user.is_platform_admin && !user.is_you"
                    variant="outline"
                    size="sm"
                    @click="revoking = true"
                >
                    <ShieldOff /> Remove admin rights
                </Button>
                <Button
                    v-if="!user.is_platform_admin"
                    size="sm"
                    @click="granting = true"
                >
                    <ShieldCheck /> Make platform admin
                </Button>
            </template>
        </PageHeader>

        <InputError :message="errors.user" />

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle>Account</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl>
                        <DetailItem
                            label="Since"
                            :value="formatDate(user.created_at)"
                        />
                        <DetailItem
                            label="Last active"
                            :value="
                                user.last_active_at
                                    ? formatDateTime(user.last_active_at)
                                    : 'Not logged in recently'
                            "
                        />
                        <DetailItem
                            label="E-mail address"
                            :value="
                                user.verified ? 'Confirmed' : 'Not confirmed'
                            "
                        />
                        <DetailItem
                            label="Two-factor login"
                            :value="user.two_factor ? 'On' : 'Off'"
                        />
                    </dl>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Companies ({{ companies.length }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="!companies.length"
                        class="text-sm text-muted-foreground"
                    >
                        Not a member of any company.
                    </p>
                    <ul v-else class="divide-y text-sm">
                        <li
                            v-for="company in companies"
                            :key="company.slug"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <Link
                                    :href="showCompany(company.slug).url"
                                    class="font-medium hover:underline"
                                    >{{ company.name }}</Link
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ company.role_label
                                    }}<template v-if="company.is_current">
                                        · current company</template
                                    ></span
                                >
                            </span>
                            <Pill v-if="company.suspended" color="red"
                                >Suspended</Pill
                            >
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="granting"
            :title="`Make ${user.name} a platform administrator?`"
            description="Platform administrators see every company and user, and can suspend companies and manage administrators."
            confirm-label="Make platform admin"
            @confirm="confirmGrant"
        />

        <ConfirmDialog
            v-model:open="revoking"
            :title="`Remove the admin rights of ${user.name}?`"
            description="They keep their account and companies, but can no longer open the platform admin area."
            confirm-label="Remove admin rights"
            destructive
            @confirm="confirmRevoke"
        />
    </div>
</template>
