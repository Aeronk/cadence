<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    modelValue: number;
    total: number;
    pageSize: number;
}>();

const emit = defineEmits<{ 'update:modelValue': [number] }>();

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)));
const from = computed(() => (props.total === 0 ? 0 : (props.modelValue - 1) * props.pageSize + 1));
const to = computed(() => Math.min(props.modelValue * props.pageSize, props.total));

function go(p: number) {
    if (p < 1 || p > totalPages.value || p === props.modelValue) return;
    emit('update:modelValue', p);
}
</script>

<template>
    <div
        v-if="total > pageSize"
        class="flex items-center justify-between gap-2 border-t bg-card px-4 py-2 text-xs text-muted-foreground"
    >
        <span class="tabular-nums">{{ from }}–{{ to }} of {{ total }}</span>
        <div class="flex items-center gap-1">
            <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded border bg-background hover:bg-muted disabled:opacity-40"
                :disabled="modelValue === 1"
                @click="go(modelValue - 1)"
            >
                <ChevronLeft class="h-3.5 w-3.5" />
            </button>
            <span class="px-2 tabular-nums">page {{ modelValue }} / {{ totalPages }}</span>
            <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded border bg-background hover:bg-muted disabled:opacity-40"
                :disabled="modelValue === totalPages"
                @click="go(modelValue + 1)"
            >
                <ChevronRight class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>
</template>
