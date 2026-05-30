<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import GoalRow from './GoalRow.vue';

type Node = {
    id: number;
    parent_id: number | null;
    type: 'vision' | 'goal' | 'objective';
    title: string;
    description: string | null;
    horizon: string | null;
    target_date: string | null;
    progress: number;
    completed_at: string | null;
    milestones_count: number;
    children: Node[];
};

defineProps<{ node: Node; depth: number }>();
const emit = defineEmits<{
    (e: 'remove', node: Node): void;
    (e: 'progress', node: Node, value: number): void;
}>();

const typeBadge = (t: string) => ({
    vision: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    goal: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    objective: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
}[t] ?? 'bg-muted');
</script>

<template>
    <div :class="['border-b last:border-b-0', depth > 0 ? 'border-dashed' : '']">
        <div class="group flex items-center gap-3 p-3" :style="{ paddingLeft: `${0.75 + depth * 1.25}rem` }">
            <span
                class="rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wider"
                :class="typeBadge(node.type)"
            >
                {{ node.type }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ node.title }}</p>
                <p v-if="node.target_date" class="text-xs text-muted-foreground">
                    {{ node.horizon }} · target {{ node.target_date }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <div class="hidden w-32 sm:block">
                    <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full bg-primary"
                            :style="{ width: `${node.progress}%` }"
                        />
                    </div>
                </div>
                <span class="text-xs font-medium tabular-nums">{{ node.progress }}%</span>
                <button
                    class="opacity-0 transition group-hover:opacity-100"
                    @click="emit('remove', node)"
                >
                    <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                </button>
            </div>
        </div>

        <GoalRow
            v-for="child in node.children"
            :key="child.id"
            :node="child"
            :depth="depth + 1"
            @remove="(n) => emit('remove', n)"
            @progress="(n, v) => emit('progress', n, v)"
        />
    </div>
</template>
