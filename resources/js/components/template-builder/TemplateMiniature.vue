<script setup lang="ts">
import TemplateRenderer from './TemplateRenderer.vue';
import type { Sample, TemplateDefinition } from './types';

/**
 * A small picture of a template: the template drawn with the example document, scaled down.
 */
const props = withDefaults(
    defineProps<{
        definition: TemplateDefinition;
        sample: Sample;
        gates?: Record<string, boolean>;
        scale?: number;
    }>(),
    { gates: undefined, scale: 0.22 },
);
</script>

<template>
    <div
        class="overflow-hidden rounded-sm bg-white shadow-sm ring-1 ring-black/10"
        :style="{
            width: `${794 * props.scale}px`,
            height: `${1123 * props.scale}px`,
        }"
    >
        <div
            :style="{ zoom: props.scale, pointerEvents: 'none' }"
            aria-hidden="true"
        >
            <TemplateRenderer
                :definition="definition"
                :sample="sample"
                :gates="gates"
            />
        </div>
    </div>
</template>
