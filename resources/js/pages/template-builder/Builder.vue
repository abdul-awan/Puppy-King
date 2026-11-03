<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileText,
    LoaderCircle,
    Redo2,
    RefreshCw,
    Trash2,
    Undo2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ConfirmDialog from '@/components/app/ConfirmDialog.vue';
import SelectInput from '@/components/app/SelectInput.vue';
import BuilderInspector from '@/components/template-builder/BuilderInspector.vue';
import BuilderPalette from '@/components/template-builder/BuilderPalette.vue';
import { postJson } from '@/components/template-builder/http';
import TemplateRenderer from '@/components/template-builder/TemplateRenderer.vue';
import type {
    PageChoices,
    Registry,
    Sample,
    Structure,
    TemplateDefinition,
    TemplatePreset,
    UploadedImage,
} from '@/components/template-builder/types';
import { useTemplateBuilder } from '@/components/template-builder/useTemplateBuilder';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTeam } from '@/composables/useTeam';
import { edit as generalSettings } from '@/routes/settings/general';
import { edit as invoiceSettings } from '@/routes/settings/invoice-settings';
import {
    create,
    destroy,
    edit,
    preview,
    store,
    update,
} from '@/routes/settings/invoice-templates';
import { store as uploadImage } from '@/routes/settings/template-images';
import type { DocumentKind, Option, Team } from '@/types';

type Values = {
    name: string;
    layout: string;
    use_for: DocumentKind[];
    definition: TemplateDefinition;
};

const props = defineProps<{
    template: { id: number; name: string } | null;
    values: Values;
    registry: Registry;
    structure: Structure;
    pageChoices: PageChoices;
    presets: TemplatePreset[];
    samples: Record<DocumentKind, Sample>;
    gates: Record<string, boolean>;
    placeholders: Record<string, string>;
    images: UploadedImage[];
    types: Option<DocumentKind>[];
}>();

defineOptions({
    layout: (props: {
        currentTeam: Team;
        template: { id: number; name: string } | null;
    }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: generalSettings(props.currentTeam.slug),
            },
            {
                title: 'Invoice settings',
                href: invoiceSettings(props.currentTeam.slug),
            },
            props.template
                ? {
                      title: props.template.name,
                      href: edit([props.currentTeam.slug, props.template.id]),
                  }
                : {
                      title: 'New template',
                      href: create(props.currentTeam.slug),
                  },
        ],
    }),
});

const { slug } = useTeam();
const builder = useTemplateBuilder(
    props.values.definition,
    props.registry,
    props.structure,
);

const form = useForm({
    name: props.values.name,
    layout: props.values.layout,
    use_for: [...props.values.use_for],
});

// ----- Uploaded images.
const images = ref<UploadedImage[]>([...props.images]);
const imageMap = computed(() =>
    Object.fromEntries(images.value.map((image) => [image.name, image.url])),
);
const addImage = (image: UploadedImage) => {
    images.value = [
        ...images.value.filter((item) => item.name !== image.name),
        image,
    ];
};

// ----- The example shown on the page.
const kind = ref<DocumentKind>('invoice');
const sample = computed(() => props.samples[kind.value]);
const kinds = computed(() =>
    props.types.map((type) => ({
        value: type.value,
        label: type.label.replace(/s$/, ''),
    })),
);

// ----- Start from a ready-made template.
const startFrom = ref<string | null>(null);
const pendingPreset = ref<TemplatePreset | null>(null);
const presetOptions = computed(() =>
    props.presets.map((preset) => ({ value: preset.key, label: preset.name })),
);

watch(startFrom, (key) => {
    pendingPreset.value =
        props.presets.find((preset) => preset.key === key) ?? null;
});

const cancelPreset = () => {
    pendingPreset.value = null;
    startFrom.value = null;
};

const applyPreset = () => {
    if (pendingPreset.value) {
        builder.replace(pendingPreset.value.definition);

        if (pendingPreset.value.key !== 'blank') {
            form.layout = pendingPreset.value.key;
        }
    }

    cancelPreset();
};

// ----- Unsaved changes.
const initialJson = JSON.stringify(props.values.definition);
const saving = ref(false);
const dirty = computed(
    () =>
        form.isDirty ||
        JSON.stringify(builder.definition.value) !== initialJson,
);

const warnBeforeUnload = (event: BeforeUnloadEvent) => {
    if (dirty.value && !saving.value) {
        event.preventDefault();
    }
};

let removeNavigationGuard: (() => void) | undefined;

// ----- Saving.
const toggleUseFor = (type: DocumentKind, on: boolean) => {
    form.use_for = on
        ? [...form.use_for, type]
        : form.use_for.filter((value) => value !== type);
};

const errors = computed(() => form.errors as Record<string, string>);
const definitionError = computed(
    () =>
        Object.entries(errors.value).find(([key]) =>
            key.startsWith('definition'),
        )?.[1],
);

const save = () => {
    saving.value = true;
    const options = {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        definition: builder.definition.value,
    }));

    if (props.template) {
        form.put(update([slug.value, props.template.id]).url, options);
    } else {
        form.post(store(slug.value).url, options);
    }
};

const deleting = ref(false);

const destroyTemplate = () => {
    if (props.template) {
        saving.value = true;
        router.delete(destroy([slug.value, props.template.id]).url, {
            onFinish: () => {
                saving.value = false;
            },
        });
    }
};

// ----- The real PDF, rendered by the server from the template on the page.
const pdfOpen = ref(false);
const pdfUrl = ref<string | null>(null);
const pdfLoading = ref(false);
const pdfError = ref<string | null>(null);
let pdfTimer: ReturnType<typeof setTimeout> | undefined;

const loadPdf = async () => {
    pdfLoading.value = true;
    pdfError.value = null;

    try {
        const response = await postJson(
            preview(slug.value).url,
            { definition: builder.definition.value, type: kind.value },
            'application/pdf',
        );

        if (!response.ok) {
            pdfError.value =
                response.status === 429
                    ? 'Too many previews in a short time. Wait a moment and refresh.'
                    : 'The preview could not be made.';

            return;
        }

        const url = URL.createObjectURL(await response.blob());

        if (pdfUrl.value) {
            URL.revokeObjectURL(pdfUrl.value);
        }

        pdfUrl.value = url;
    } catch {
        pdfError.value = 'The preview could not be made.';
    } finally {
        pdfLoading.value = false;
    }
};

watch(pdfOpen, (open) => {
    if (open) {
        void loadPdf();
    }
});

watch(
    [() => builder.definition.value, kind],
    () => {
        if (pdfOpen.value) {
            clearTimeout(pdfTimer);
            pdfTimer = setTimeout(() => void loadPdf(), 1200);
        }
    },
    { deep: true },
);

// ----- Zoom: the page fits the space next to the panels.
const canvas = ref<HTMLElement | null>(null);
const zoomChoice = ref<'fit' | '0.6' | '0.8' | '1'>('fit');
const fitZoom = ref(1);
let observer: ResizeObserver | undefined;
const zoom = computed(() =>
    zoomChoice.value === 'fit' ? fitZoom.value : Number(zoomChoice.value),
);
const zoomOptions = [
    { value: 'fit', label: 'Fit' },
    { value: '0.6', label: '60%' },
    { value: '0.8', label: '80%' },
    { value: '1', label: '100%' },
] as { value: 'fit' | '0.6' | '0.8' | '1'; label: string }[];

// ----- Keyboard shortcuts (not while typing in a field).
const typing = (target: EventTarget | null): boolean =>
    target instanceof HTMLElement &&
    (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
        target.isContentEditable);

const onKeydown = (event: KeyboardEvent) => {
    if (typing(event.target)) {
        return;
    }

    const selection = builder.selection.value;
    const meta = event.ctrlKey || event.metaKey;

    if (meta && event.key.toLowerCase() === 'z') {
        event.preventDefault();

        if (event.shiftKey) {
            builder.redo();
        } else {
            builder.undo();
        }
    } else if (meta && event.key.toLowerCase() === 'y') {
        event.preventDefault();
        builder.redo();
    } else if (
        meta &&
        event.key.toLowerCase() === 'd' &&
        selection.kind === 'element'
    ) {
        event.preventDefault();
        builder.duplicateElement(selection.elementId);
    } else if (
        (event.key === 'Delete' || event.key === 'Backspace') &&
        selection.kind === 'element'
    ) {
        event.preventDefault();
        builder.removeElement(selection.elementId);
    } else if (
        event.altKey &&
        (event.key === 'ArrowUp' || event.key === 'ArrowDown') &&
        selection.kind === 'element'
    ) {
        event.preventDefault();
        builder.moveElementBy(
            selection.elementId,
            event.key === 'ArrowUp' ? -1 : 1,
        );
    } else if (event.key === 'Escape') {
        builder.select({ kind: 'page' });
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard = router.on('before', (event) => {
        if (
            dirty.value &&
            !saving.value &&
            event.detail.visit.method === 'get'
        ) {
            if (
                !window.confirm(
                    'Leave the builder? Your changes are not saved.',
                )
            ) {
                event.preventDefault();
            }
        }
    });

    if (canvas.value) {
        observer = new ResizeObserver(([entry]) => {
            fitZoom.value = Math.max(
                0.35,
                Math.min(1, (entry.contentRect.width - 80) / 794),
            );
        });
        observer.observe(canvas.value);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard?.();
    observer?.disconnect();
    clearTimeout(pdfTimer);

    if (pdfUrl.value) {
        URL.revokeObjectURL(pdfUrl.value);
    }
});
</script>

<template>
    <Head :title="template ? `Template ${template.name}` : 'New template'" />

    <div class="flex h-[calc(100svh-4rem)] min-h-[640px] flex-col">
        <!-- Top bar -->
        <div class="flex flex-wrap items-center gap-2 border-b px-4 py-2">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="invoiceSettings(slug).url"
                    ><ArrowLeft /> Invoice settings</Link
                >
            </Button>
            <Input
                v-model="form.name"
                maxlength="80"
                placeholder="Name of the template"
                class="h-8 w-56"
                aria-label="Name of the template"
            />
            <SelectInput
                v-model="startFrom"
                :options="presetOptions"
                placeholder="Start from…"
                class="h-8 w-40"
                aria-label="Start from a ready-made template"
            />
            <div class="flex items-center gap-3 px-1 text-sm">
                <span class="text-muted-foreground">Use for</span>
                <label
                    v-for="type in types"
                    :key="type.value"
                    class="flex items-center gap-1.5"
                >
                    <Checkbox
                        :model-value="form.use_for.includes(type.value)"
                        @update:model-value="
                            toggleUseFor(type.value, Boolean($event))
                        "
                    />
                    {{ type.label }}
                </label>
            </div>

            <div class="ml-auto flex items-center gap-1.5">
                <SelectInput
                    v-model="kind"
                    :options="kinds"
                    class="h-8 w-32"
                    aria-label="Example document"
                />
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    title="Undo (Ctrl+Z)"
                    :disabled="!builder.canUndo.value"
                    @click="builder.undo()"
                >
                    <Undo2 class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    title="Redo (Ctrl+Y)"
                    :disabled="!builder.canRedo.value"
                    @click="builder.redo()"
                >
                    <Redo2 class="size-4" />
                </Button>
                <Button variant="outline" size="sm" @click="pdfOpen = true"
                    ><FileText /> PDF preview</Button
                >
                <Button
                    v-if="template"
                    variant="ghost"
                    size="icon"
                    class="size-8 text-red-600 hover:text-red-700 dark:text-red-400"
                    title="Delete the template"
                    @click="deleting = true"
                >
                    <Trash2 class="size-4" />
                </Button>
                <Button size="sm" :disabled="form.processing" @click="save">
                    {{ template ? 'Save template' : 'Create template' }}
                </Button>
            </div>
        </div>

        <div
            v-if="errors.name || errors.use_for || definitionError"
            class="border-b bg-red-50 px-4 py-2 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ errors.name ?? errors.use_for ?? definitionError }}
        </div>

        <!-- Palette, page and settings -->
        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <aside
                class="w-full shrink-0 border-b p-3 lg:w-64 lg:border-r lg:border-b-0"
            >
                <BuilderPalette :builder="builder" />
            </aside>

            <main
                ref="canvas"
                class="relative min-h-0 min-w-0 flex-1 overflow-auto bg-muted/60"
            >
                <div
                    class="sticky top-0 z-20 flex items-center justify-between gap-2 bg-muted/90 px-4 py-1.5 text-xs text-muted-foreground backdrop-blur"
                >
                    <span>
                        {{ builder.elementCount.value }} elements ·
                        {{ builder.definition.value.rows.length }} rows
                        <template v-if="dirty">
                            ·
                            <span
                                class="font-medium text-amber-700 dark:text-amber-400"
                                >Not saved yet</span
                            ></template
                        >
                    </span>
                    <span class="flex items-center gap-1.5">
                        Zoom
                        <SelectInput
                            v-model="zoomChoice"
                            :options="zoomOptions"
                            class="h-7 w-20 text-xs"
                        />
                    </span>
                </div>
                <div class="flex justify-center px-10 pt-4 pb-16">
                    <div
                        class="shadow-lg"
                        :style="{ zoom }"
                        @dragend="builder.endDrag()"
                    >
                        <TemplateRenderer
                            :definition="builder.definition.value"
                            :sample="sample"
                            :images="imageMap"
                            :gates="gates"
                            :builder="builder"
                        />
                    </div>
                </div>
            </main>

            <aside
                class="w-full shrink-0 overflow-y-auto border-t p-4 lg:w-80 lg:border-t-0 lg:border-l"
            >
                <BuilderInspector
                    :builder="builder"
                    :page-choices="pageChoices"
                    :images="images"
                    :upload-url="uploadImage(slug).url"
                    :placeholders="placeholders"
                    :gates="gates"
                    @uploaded="addImage"
                />
            </aside>
        </div>
    </div>

    <Sheet v-model:open="pdfOpen">
        <SheetContent side="right" class="w-full sm:max-w-3xl">
            <SheetHeader>
                <SheetTitle>PDF preview</SheetTitle>
                <SheetDescription>
                    The template as the PDF of an example
                    {{ kind.replace('_', ' ') }}, with your company details and
                    invoice settings. It follows your changes.
                </SheetDescription>
            </SheetHeader>
            <div class="flex items-center gap-2 px-4">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="pdfLoading"
                    @click="loadPdf"
                >
                    <LoaderCircle v-if="pdfLoading" class="animate-spin" />
                    <RefreshCw v-else />
                    Refresh
                </Button>
                <span v-if="pdfError" class="text-sm text-destructive">{{
                    pdfError
                }}</span>
            </div>
            <div class="min-h-0 flex-1 px-4 pb-4">
                <iframe
                    v-if="pdfUrl"
                    :key="pdfUrl"
                    :src="`${pdfUrl}#toolbar=0&view=FitH`"
                    title="PDF preview"
                    class="h-full min-h-[70vh] w-full rounded-md border bg-muted"
                />
                <div
                    v-else
                    class="flex h-[70vh] items-center justify-center rounded-md border text-sm text-muted-foreground"
                >
                    <LoaderCircle class="mr-2 size-4 animate-spin" /> Making the
                    PDF…
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <ConfirmDialog
        :open="pendingPreset !== null"
        :title="`Start from ${pendingPreset?.name ?? ''}?`"
        description="This replaces the template on the page. You can undo it with Ctrl+Z."
        confirm-label="Replace"
        @update:open="(open: boolean) => !open && cancelPreset()"
        @confirm="applyPreset"
    />

    <ConfirmDialog
        v-if="template"
        v-model:open="deleting"
        :title="`Delete the template “${template.name}”?`"
        description="Documents that use it get the ready-made template it started from."
        confirm-label="Delete template"
        destructive
        @confirm="destroyTemplate"
    />
</template>
