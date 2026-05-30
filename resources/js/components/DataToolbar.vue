<script setup lang="ts">
import { LayoutGrid, Search, Table2, X } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import type { ViewMode } from '@/composables/useViewMode';

const props = defineProps<{
    modelValue: string;
    viewMode: ViewMode;
    placeholder?: string;
    count?: number;
    total?: number;
}>();

const emit = defineEmits<{
    'update:modelValue': [string];
    'update:viewMode': [ViewMode];
}>();

function clear() {
    emit('update:modelValue', '');
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-card px-3 py-2">
        <div class="relative min-w-[220px] flex-1 max-w-md">
            <Search class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
                :model-value="modelValue"
                :placeholder="placeholder ?? 'Search…'"
                class="pl-8 pr-8"
                @update:model-value="(v) => emit('update:modelValue', String(v))"
            />
            <button
                v-if="modelValue"
                type="button"
                class="absolute right-2 top-2.5 text-muted-foreground hover:text-foreground"
                @click="clear"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <div class="flex items-center gap-3">
            <slot name="filters" />
            <span v-if="count !== undefined" class="text-xs text-muted-foreground tabular-nums">
                <template v-if="total !== undefined && total !== count">{{ count }} of {{ total }}</template>
                <template v-else>{{ count }} result{{ count === 1 ? '' : 's' }}</template>
            </span>
            <div class="inline-flex rounded-md border bg-background p-0.5">
                <button
                    type="button"
                    class="grid h-7 w-7 place-items-center rounded transition"
                    :class="viewMode === 'cards' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                    title="Card view"
                    @click="emit('update:viewMode', 'cards')"
                >
                    <LayoutGrid class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    class="grid h-7 w-7 place-items-center rounded transition"
                    :class="viewMode === 'table' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                    title="Table view"
                    @click="emit('update:viewMode', 'table')"
                >
                    <Table2 class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
