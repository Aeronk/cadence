<script setup lang="ts">
import { ChevronDown, ChevronRight, Flag, Lock, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import GoalRow from './GoalRow.vue';

type Milestone = {
    id: number;
    title: string;
    progress: number;
    is_manual: boolean;
    due_date: string | null;
    completed_at: string | null;
    project: { id: number; title: string; url: string } | null;
};

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
    milestones: Milestone[];
    children: Node[];
};

defineProps<{ node: Node; depth: number }>();

// Milestones are collapsed by default so a long tree stays readable; opening one
// is how you see what the goal's percentage is actually made of.
const showMilestones = ref(false);
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
            <button
                v-if="node.milestones_count > 0"
                class="flex shrink-0 items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted"
                :title="`${node.milestones_count} milestone(s)`"
                @click="showMilestones = !showMilestones"
            >
                <ChevronDown v-if="showMilestones" class="h-3.5 w-3.5" />
                <ChevronRight v-else class="h-3.5 w-3.5" />
                <Flag class="h-3 w-3" />
                {{ node.milestones_count }}
            </button>

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

        <!-- What the percentage is made of. -->
        <div
            v-if="showMilestones && node.milestones.length"
            class="space-y-2 border-t border-dashed bg-muted/30 px-3 py-3"
            :style="{ paddingLeft: `${1.75 + depth * 1.25}rem` }"
        >
            <div
                v-for="m in node.milestones"
                :key="m.id"
                class="flex items-center gap-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium">
                        <span :class="{ 'line-through text-muted-foreground': m.completed_at }">
                            {{ m.title }}
                        </span>
                        <Lock
                            v-if="m.is_manual"
                            class="ml-1 inline h-2.5 w-2.5 text-muted-foreground"
                            title="Progress set by hand rather than counted from tasks"
                        />
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        <a
                            v-if="m.project"
                            :href="m.project.url"
                            class="hover:underline"
                        >{{ m.project.title }}</a>
                        <template v-if="m.due_date">
                            <span v-if="m.project"> &middot; </span>due {{ m.due_date }}
                        </template>
                    </p>
                </div>
                <div class="hidden w-24 sm:block">
                    <div class="h-1 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-primary/70" :style="{ width: `${m.progress}%` }" />
                    </div>
                </div>
                <span class="w-9 text-right text-[11px] tabular-nums text-muted-foreground">
                    {{ m.progress }}%
                </span>
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
