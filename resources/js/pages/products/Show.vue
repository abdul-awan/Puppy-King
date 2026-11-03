<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PackagePlus, Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AttachmentsPanel from '@/components/app/AttachmentsPanel.vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import CustomFieldValues from '@/components/app/CustomFieldValues.vue';
import DetailItem from '@/components/app/DetailItem.vue';
import Field from '@/components/app/Field.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import Pill from '@/components/app/Pill.vue';
import Timeline from '@/components/app/Timeline.vue';
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
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { formatMoney, formatNumber } from '@/lib/format';
import { destroy, edit, index, show, stock } from '@/routes/products';
import type {
    Attachment,
    CustomFieldDefinition,
    Product,
    Team,
    TimelineItem,
} from '@/types';

const props = defineProps<{
    product: Product;
    timeline: TimelineItem[];
    attachments: Attachment[];
    customFields: CustomFieldDefinition[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team; product: Product }) => ({
        breadcrumbs: [
            { title: 'Products', href: index(props.currentTeam.slug) },
            {
                title: props.product.name,
                href: show([props.currentTeam.slug, props.product.id]),
            },
        ],
    }),
});

const { slug, currency } = useTeam();
const { canEdit } = usePermissions();

const deleting = ref(false);
const adjusting = ref(false);
const stockForm = useForm({ quantity: '', reason: '' });

const adjustStock = () => {
    stockForm.post(stock([slug.value, props.product.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            adjusting.value = false;
            stockForm.reset();
        },
    });
};

const destroyProduct = () => {
    router.delete(destroy([slug.value, props.product.id]).url);
};
</script>

<template>
    <Head :title="product.name" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader :title="product.name" :description="product.code">
            <template #badges>
                <Pill :color="product.type === 'service' ? 'indigo' : 'blue'">{{
                    product.type_label
                }}</Pill>
                <Pill v-if="!product.is_active" color="gray">Inactive</Pill>
            </template>
            <template v-if="canEdit('products')" #actions>
                <Button variant="outline" size="sm" @click="adjusting = true">
                    <PackagePlus /> Adjust stock
                </Button>
                <Button variant="outline" size="sm" @click="deleting = true">
                    <Trash2 /> Delete
                </Button>
                <Button size="sm" as-child>
                    <Link :href="edit([slug, product.id]).url"
                        ><Pencil /> Edit</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="h-fit">
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl>
                        <DetailItem
                            label="Sale price"
                            :value="`${formatMoney(product.sale_price, currency)} / ${product.unit}`"
                        />
                        <DetailItem
                            label="Purchase price"
                            :value="
                                product.purchase_price
                                    ? formatMoney(
                                          product.purchase_price,
                                          currency,
                                      )
                                    : null
                            "
                        />
                        <DetailItem
                            label="Margin"
                            :value="
                                product.margin_percent !== null
                                    ? `${product.margin_percent}%`
                                    : null
                            "
                        />
                        <DetailItem
                            label="Tax rate"
                            :value="product.tax_rate?.name ?? 'No tax'"
                        />
                        <DetailItem label="HS code" :value="product.hs_code" />
                        <DetailItem
                            label="Category"
                            :value="product.category?.name"
                        />
                        <DetailItem
                            label="Stock"
                            :value="
                                product.track_stock
                                    ? `${formatNumber(product.stock_quantity, 3)} ${product.unit}`
                                    : 'Not tracked'
                            "
                        />
                    </dl>
                    <template v-if="product.description">
                        <Separator class="my-3" />
                        <p
                            class="text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ product.description }}
                        </p>
                    </template>
                    <template v-if="customFields.length > 0">
                        <Separator class="my-3" />
                        <CustomFieldValues
                            :fields="customFields"
                            :values="product.custom_fields"
                        />
                    </template>
                </CardContent>
            </Card>

            <div class="lg:col-span-2">
                <Tabs default-value="timeline">
                    <TabsList>
                        <TabsTrigger value="timeline">Timeline</TabsTrigger>
                        <TabsTrigger value="files"
                            >Files ({{ attachments.length }})</TabsTrigger
                        >
                    </TabsList>
                    <TabsContent value="timeline">
                        <Card>
                            <CardContent class="pt-6">
                                <Timeline
                                    :items="timeline"
                                    notable-type="product"
                                    :notable-id="product.id"
                                    :can-write="canEdit('products')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="files">
                        <Card>
                            <CardContent class="pt-6">
                                <AttachmentsPanel
                                    :attachments="attachments"
                                    attachable-type="product"
                                    :attachable-id="product.id"
                                    :can-write="canEdit('products')"
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <Dialog v-model:open="adjusting">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Adjust stock</DialogTitle>
                    <DialogDescription>
                        Enter a positive number to add stock (e.g. a delivery)
                        or a negative number to remove it.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="adjustStock">
                    <Field
                        :label="`Quantity (${product.unit})`"
                        :error="stockForm.errors.quantity"
                    >
                        <Input
                            v-model="stockForm.quantity"
                            type="number"
                            step="0.001"
                            required
                        />
                    </Field>
                    <Field label="Reason" :error="stockForm.errors.reason">
                        <Input
                            v-model="stockForm.reason"
                            placeholder="e.g. Delivery from supplier, stock count"
                        />
                    </Field>
                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="adjusting = false"
                            >Cancel</Button
                        >
                        <Button type="submit" :disabled="stockForm.processing"
                            >Save</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="deleting"
            title="Delete product?"
            :description="`${product.name} will no longer be available for new documents. Existing documents are not changed.`"
            confirm-label="Delete"
            destructive
            @confirm="destroyProduct"
        />
    </div>
</template>
