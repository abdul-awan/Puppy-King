<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Ban, CirclePlay, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import Field from '@/components/app/Field.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { dashboard } from '@/routes/admin';
import {
    destroy,
    index,
    reactivate,
    show,
    suspend,
} from '@/routes/admin/companies';
import { show as showUser } from '@/routes/admin/users';
import type { AdminCompany } from '@/types';

type Company = AdminCompany & {
    suspension_reason: string | null;
    legal_name: string | null;
    ntn: string | null;
    strn: string | null;
    currency: string;
    last_activity_at: string | null;
    pending_invitations: number;
};

const props = defineProps<{
    company: Company;
    members: {
        id: number;
        name: string;
        email: string;
        verified: boolean;
        role: string;
        role_label: string;
        joined_at: string | null;
    }[];
    usage: { label: string; count: number }[];
}>();

defineOptions({
    layout: (props: { company: Company }) => ({
        breadcrumbs: [
            { title: 'Platform admin', href: dashboard() },
            { title: 'Companies', href: index() },
            { title: props.company.name, href: show(props.company.slug) },
        ],
    }),
});

const suspending = ref(false);
const reactivating = ref(false);
const form = useForm({ reason: '' });

const openSuspend = () => {
    form.reset();
    form.clearErrors();
    suspending.value = true;
};

const confirmSuspend = () => {
    form.transform((data) => ({ reason: data.reason || null })).post(
        suspend(props.company.slug).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                suspending.value = false;
            },
        },
    );
};

const confirmReactivate = () => {
    router.post(
        reactivate(props.company.slug).url,
        {},
        { preserveScroll: true },
    );
};

// Deleting for good: the name of the company has to be typed to confirm.
const deleting = ref(false);
const deleteForm = useForm({ name: '' });
const nameMatches = computed(() => deleteForm.name === props.company.name);

const openDelete = () => {
    deleteForm.reset();
    deleteForm.clearErrors();
    deleting.value = true;
};

const confirmDelete = () => {
    deleteForm.delete(destroy(props.company.slug).url, {
        onSuccess: () => {
            deleting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`${company.name} · Platform admin`" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="company.name">
            <template #badges>
                <Pill v-if="company.suspended_at" color="red">Suspended</Pill>
                <Pill v-else color="green">Active</Pill>
            </template>
            <template #meta>
                <p class="text-sm text-muted-foreground">
                    /{{ company.slug }} · since
                    {{ formatDate(company.created_at) }}
                </p>
            </template>
            <template #actions>
                <Button
                    v-if="company.suspended_at"
                    size="sm"
                    @click="reactivating = true"
                >
                    <CirclePlay /> Reactivate
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                    @click="openSuspend"
                >
                    <Ban /> Suspend
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="company.suspended_at"
            class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            <p class="font-medium">
                Suspended since {{ formatDateTime(company.suspended_at) }}
            </p>
            <p
                v-if="company.suspension_reason"
                class="mt-1 whitespace-pre-line"
            >
                {{ company.suspension_reason }}
            </p>
            <p class="mt-1">
                Its people cannot work in it and the links it sent to customers
                do not open. Its data stays as it is.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl>
                        <DetailItem label="Owner">
                            <Link
                                v-if="company.owner"
                                :href="showUser(company.owner.id).url"
                                class="hover:underline"
                                >{{ company.owner.name }}</Link
                            >
                            <span v-else>—</span>
                        </DetailItem>
                        <DetailItem
                            label="Owner e-mail"
                            :value="company.owner?.email ?? '—'"
                        />
                        <DetailItem
                            label="Name on documents"
                            :value="company.legal_name ?? '—'"
                        />
                        <DetailItem label="NTN" :value="company.ntn ?? '—'" />
                        <DetailItem label="STRN" :value="company.strn ?? '—'" />
                        <DetailItem
                            label="Currency"
                            :value="company.currency"
                        />
                        <DetailItem
                            label="Last activity"
                            :value="
                                company.last_activity_at
                                    ? formatDateTime(company.last_activity_at)
                                    : 'Nothing yet'
                            "
                        />
                        <DetailItem
                            label="Open invitations"
                            :value="company.pending_invitations"
                        />
                        <DetailItem
                            label="Kind"
                            :value="
                                company.is_personal
                                    ? 'Created when signing up'
                                    : 'Created later'
                            "
                        />
                    </dl>
                </CardContent>
            </Card>

            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Use of the software</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div
                                v-for="item in usage"
                                :key="item.label"
                                class="rounded-lg border p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd
                                    class="mt-1 text-xl font-semibold tabular-nums"
                                >
                                    {{ formatNumber(item.count, 0) }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle
                            >People ({{ company.members_count }})</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead class="hidden sm:table-cell"
                                            >Joined</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="member in members"
                                        :key="member.id"
                                    >
                                        <TableCell>
                                            <Link
                                                :href="showUser(member.id).url"
                                                class="font-medium hover:underline"
                                                >{{ member.name }}</Link
                                            >
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ member.email }}
                                                <template
                                                    v-if="!member.verified"
                                                >
                                                    · e-mail not
                                                    confirmed</template
                                                >
                                            </p>
                                        </TableCell>
                                        <TableCell class="text-sm">{{
                                            member.role_label
                                        }}</TableCell>
                                        <TableCell
                                            class="hidden text-sm text-muted-foreground sm:table-cell"
                                            >{{
                                                formatDate(member.joined_at)
                                            }}</TableCell
                                        >
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Card class="border-red-200 dark:border-red-900">
            <CardHeader>
                <CardTitle class="text-red-700 dark:text-red-300"
                    >Delete company</CardTitle
                >
            </CardHeader>
            <CardContent
                class="flex flex-wrap items-center justify-between gap-4"
            >
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Deletes {{ company.name }} for good, with all its data:
                    customers, documents, payments, projects, time, tickets,
                    work orders, settings, history and files. Its people keep
                    their accounts; whoever has no other company gets a new,
                    empty one. This cannot be undone.
                </p>
                <Button variant="destructive" size="sm" @click="openDelete">
                    <Trash2 /> Delete company
                </Button>
            </CardContent>
        </Card>

        <Dialog v-model:open="deleting">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle
                        >Delete {{ company.name }} and all its
                        data?</DialogTitle
                    >
                    <DialogDescription>
                        Everything of this company is deleted for good and
                        cannot be brought back. Consider suspending it instead
                        if you may need the data later.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="delete-company-form"
                    class="space-y-4"
                    @submit.prevent="confirmDelete"
                >
                    <Field
                        :label="`Type “${company.name}” to confirm`"
                        :error="deleteForm.errors.name"
                    >
                        <Input
                            v-model="deleteForm.name"
                            autocomplete="off"
                            placeholder="Name of the company"
                        />
                    </Field>
                </form>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="deleting = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        form="delete-company-form"
                        variant="destructive"
                        :disabled="!nameMatches || deleteForm.processing"
                        >Delete company and all data</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="suspending">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Suspend {{ company.name }}?</DialogTitle>
                    <DialogDescription>
                        Its people can no longer work in it and the links it
                        sent to customers stop working, until you reactivate it.
                        Nothing is deleted.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="suspend-form"
                    class="space-y-4"
                    @submit.prevent="confirmSuspend"
                >
                    <Field
                        label="Reason"
                        hint="Shown to the people of the company"
                        :error="form.errors.reason"
                    >
                        <Textarea
                            v-model="form.reason"
                            rows="3"
                            placeholder="e.g. The subscription has not been paid."
                        />
                    </Field>
                </form>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="suspending = false"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        form="suspend-form"
                        variant="destructive"
                        :disabled="form.processing"
                        >Suspend</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="reactivating"
            :title="`Reactivate ${company.name}?`"
            description="Its people can work in it again and its customer links open again."
            confirm-label="Reactivate"
            @confirm="confirmReactivate"
        />
    </div>
</template>
