<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Star, Trash2 } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import Pill from '@/components/app/Pill.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { useTeam } from '@/composables/useTeam';
import { edit as settingsHome } from '@/routes/settings/general';
import {
    destroy as destroyPhaseRoute,
    update as updatePhaseRoute,
} from '@/routes/settings/phases';
import {
    destroy as destroyPipelineRoute,
    index,
    store as storePipelineRoute,
    update as updatePipelineRoute,
} from '@/routes/settings/pipelines';
import {
    order as orderPhasesRoute,
    store as storePhaseRoute,
} from '@/routes/settings/pipelines/phases';
import type { Team } from '@/types';

type Phase = {
    id: number;
    name: string;
    probability: number;
    deals_count: number;
};
type Pipeline = {
    id: number;
    name: string;
    is_default: boolean;
    phases: Phase[];
};

const props = defineProps<{
    pipelines: Pipeline[];
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Company settings',
                href: settingsHome(props.currentTeam.slug),
            },
            { title: 'Pipelines', href: index(props.currentTeam.slug) },
        ],
    }),
});

const { slug } = useTeam();
const { canEdit } = usePermissions();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const editable = computed(() => canEdit('settings'));
const options = { preserveScroll: true };

// Editable copies of the names and probabilities.
const pipelineNames = reactive<Record<number, string>>({});
const phaseDrafts = reactive<
    Record<number, { name: string; probability: number }>
>({});
const newPhase = reactive<
    Record<number, { name: string; probability: number }>
>({});
const newPipelineName = ref('');

const sync = () => {
    for (const pipeline of props.pipelines) {
        pipelineNames[pipeline.id] = pipeline.name;
        newPhase[pipeline.id] ??= { name: '', probability: 50 };

        for (const phase of pipeline.phases) {
            phaseDrafts[phase.id] = {
                name: phase.name,
                probability: phase.probability,
            };
        }
    }
};

sync();
watch(() => props.pipelines, sync, { deep: true });

const savePipeline = (
    pipeline: Pipeline,
    makeDefault = pipeline.is_default,
) => {
    router.put(
        updatePipelineRoute([slug.value, pipeline.id]).url,
        { name: pipelineNames[pipeline.id], is_default: makeDefault },
        options,
    );
};

const deletePipeline = (pipeline: Pipeline) => {
    if (window.confirm(`Delete the pipeline "${pipeline.name}"?`)) {
        router.delete(
            destroyPipelineRoute([slug.value, pipeline.id]).url,
            options,
        );
    }
};

const addPipeline = () => {
    router.post(
        storePipelineRoute(slug.value).url,
        { name: newPipelineName.value },
        {
            ...options,
            onSuccess: () => {
                newPipelineName.value = '';
            },
        },
    );
};

const savePhase = (phase: Phase) => {
    const draft = phaseDrafts[phase.id];

    if (
        draft.name === phase.name &&
        Number(draft.probability) === phase.probability
    ) {
        return;
    }

    router.put(updatePhaseRoute([slug.value, phase.id]).url, draft, options);
};

const deletePhase = (phase: Phase) => {
    if (window.confirm(`Delete the phase "${phase.name}"?`)) {
        router.delete(destroyPhaseRoute([slug.value, phase.id]).url, options);
    }
};

const movePhase = (pipeline: Pipeline, position: number, direction: -1 | 1) => {
    const ids = pipeline.phases.map((phase) => phase.id);
    const target = position + direction;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[position], ids[target]] = [ids[target], ids[position]];
    router.put(
        orderPhasesRoute([slug.value, pipeline.id]).url,
        { phase_ids: ids },
        options,
    );
};

const addPhase = (pipeline: Pipeline) => {
    router.post(
        storePhaseRoute([slug.value, pipeline.id]).url,
        newPhase[pipeline.id],
        {
            ...options,
            onSuccess: () => {
                newPhase[pipeline.id] = { name: '', probability: 50 };
            },
        },
    );
};
</script>

<template>
    <Head title="Pipelines" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Sales pipelines"
            description="The phases your deals move through. The probability is used to calculate the weighted forecast."
        />

        <InputError :message="errors.pipeline ?? errors.phase" />

        <div
            v-for="pipeline in pipelines"
            :key="pipeline.id"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Input
                    v-model="pipelineNames[pipeline.id]"
                    class="max-w-xs font-medium"
                    :disabled="!editable"
                    @change="savePipeline(pipeline)"
                />
                <Pill v-if="pipeline.is_default" color="green">Default</Pill>
                <template v-if="editable">
                    <Button
                        v-if="!pipeline.is_default"
                        variant="ghost"
                        size="sm"
                        @click="savePipeline(pipeline, true)"
                    >
                        <Star /> Make default
                    </Button>
                    <Button
                        v-if="pipelines.length > 1"
                        variant="ghost"
                        size="icon-sm"
                        title="Delete pipeline"
                        @click="deletePipeline(pipeline)"
                    >
                        <Trash2 />
                    </Button>
                </template>
            </div>

            <div class="divide-y rounded-md border">
                <div
                    v-for="(phase, position) in pipeline.phases"
                    :key="phase.id"
                    class="flex flex-wrap items-center gap-2 px-3 py-2"
                >
                    <span class="w-6 text-sm text-muted-foreground"
                        >{{ position + 1 }}.</span
                    >
                    <Input
                        v-model="phaseDrafts[phase.id].name"
                        class="h-8 max-w-56 flex-1"
                        :disabled="!editable"
                        @change="savePhase(phase)"
                    />
                    <div class="flex items-center gap-1">
                        <Input
                            v-model="phaseDrafts[phase.id].probability"
                            type="number"
                            min="0"
                            max="100"
                            class="h-8 w-20"
                            :disabled="!editable"
                            @change="savePhase(phase)"
                        />
                        <span class="text-sm text-muted-foreground">%</span>
                    </div>
                    <span class="text-xs text-muted-foreground"
                        >{{ phase.deals_count }} deals</span
                    >
                    <div v-if="editable" class="ml-auto flex gap-1">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :disabled="position === 0"
                            @click="movePhase(pipeline, position, -1)"
                        >
                            <ArrowUp />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :disabled="position === pipeline.phases.length - 1"
                            @click="movePhase(pipeline, position, 1)"
                        >
                            <ArrowDown />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Delete phase"
                            @click="deletePhase(phase)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
            </div>

            <form
                v-if="editable"
                class="flex flex-wrap items-center gap-2"
                @submit.prevent="addPhase(pipeline)"
            >
                <Input
                    v-model="newPhase[pipeline.id].name"
                    placeholder="New phase"
                    class="h-8 max-w-56"
                    required
                />
                <Input
                    v-model="newPhase[pipeline.id].probability"
                    type="number"
                    min="0"
                    max="100"
                    class="h-8 w-20"
                />
                <span class="text-sm text-muted-foreground">%</span>
                <Button type="submit" size="sm" variant="outline"
                    ><Plus /> Add phase</Button
                >
            </form>
        </div>

        <form
            v-if="editable"
            class="flex items-center gap-2"
            @submit.prevent="addPipeline"
        >
            <Input
                v-model="newPipelineName"
                placeholder="Name of a new pipeline, e.g. Projects"
                class="max-w-xs"
                required
            />
            <Button type="submit" variant="outline"
                ><Plus /> Add pipeline</Button
            >
        </form>
    </div>
</template>
