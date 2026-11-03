<script setup lang="ts">
import { Eraser } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * A box to sign with the mouse, a finger or a stylus. The value is a PNG data URL.
 */
const model = defineModel<string>({ default: '' });

const canvas = ref<HTMLCanvasElement | null>(null);
let drawing = false;
let context: CanvasRenderingContext2D | null = null;

const setUp = () => {
    const element = canvas.value;

    if (!element) {
        return;
    }

    const ratio = window.devicePixelRatio || 1;
    element.width = element.offsetWidth * ratio;
    element.height = element.offsetHeight * ratio;

    context = element.getContext('2d');
    context?.scale(ratio, ratio);

    if (context) {
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#111827';
    }
};

onMounted(setUp);

const point = (event: PointerEvent) => {
    const rect = canvas.value?.getBoundingClientRect();

    return {
        x: event.clientX - (rect?.left ?? 0),
        y: event.clientY - (rect?.top ?? 0),
    };
};

const start = (event: PointerEvent) => {
    if (!context) {
        return;
    }

    drawing = true;
    canvas.value?.setPointerCapture(event.pointerId);
    const { x, y } = point(event);
    context.beginPath();
    context.moveTo(x, y);
};

const draw = (event: PointerEvent) => {
    if (!drawing || !context) {
        return;
    }

    const { x, y } = point(event);
    context.lineTo(x, y);
    context.stroke();
};

const end = () => {
    if (!drawing) {
        return;
    }

    drawing = false;
    model.value = canvas.value?.toDataURL('image/png') ?? '';
};

const clear = () => {
    const element = canvas.value;

    if (element && context) {
        context.clearRect(0, 0, element.width, element.height);
    }

    model.value = '';
};
</script>

<template>
    <div class="space-y-1">
        <canvas
            ref="canvas"
            class="h-36 w-full touch-none rounded-md border border-dashed bg-white"
            @pointerdown="start"
            @pointermove="draw"
            @pointerup="end"
            @pointerleave="end"
        />
        <div
            class="flex items-center justify-between text-xs text-muted-foreground"
        >
            <span>Sign in the box above</span>
            <Button type="button" variant="ghost" size="sm" @click="clear">
                <Eraser /> Clear
            </Button>
        </div>
    </div>
</template>
