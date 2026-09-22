<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Circle,
    Flag,
    FolderKanban,
    Link2,
    Link2Off,
    Lock,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';
import GoalRow from './GoalRow.vue';
import {
    formatDate,
    progressBar,
    statusBadge,
    statusLabel,
    typeBadge,
    type GoalMilestone,
    type GoalNode,
    type GoalProject,
} from '@/lib/goals';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

defineProps<{ node: GoalNode; depth: number }>();

// Collapsed by default so a long tree stays readable; opening one is how you see
// what the goal's percentage is actually made of — the projects and milestones
// behind it.
const showMilestones = ref(false);

const emit = defineEmits<{
    (e: 'edit', node: GoalNode): void;
    (e: 'remove', node: GoalNode): void;
    (e: 'toggle-complete', node: GoalNode): void;
    (e: 'add-milestone', node: GoalNode): void;
    (e: 'link-project', node: GoalNode): void;
    (e: 'unlink-project', node: GoalNode, project: GoalProject): void;
    (e: 'toggle-milestone', milestone: GoalMilestone): void;
    (e: 'unlink-milestone', milestone: GoalMilestone): void;
    (e: 'remove-milestone', milestone: GoalMilestone): void;
}>();
</script>

<template>
    <div :class="['border-b last:border-b-0', depth > 0 ? 'border-dashed' : '']">
        <div
            class="group flex items-center gap-3 p-3"
            :style="{ paddingLeft: `${0.75 + depth * 1.25}rem` }"
        >
            <button
                class="shrink-0 text-muted-foreground transition hover:text-foreground"
                :title="node.completed_at ? 'Mark as not done' : 'Mark as done'"
                @click="emit('toggle-complete', node)"
            >
                <CheckCircle2 v-if="node.completed_at" class="h-4 w-4 text-emerald-600" />
                <Circle v-else class="h-4 w-4" />
            </button>

            <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wider"
                :class="typeBadge(node.type)"
            >
                {{ node.type }}
            </span>

            <div class="min-w-0 flex-1">
                <Link
                    :href="node.url"
                    class="block truncate text-sm font-medium hover:underline"
                    :class="{ 'text-muted-foreground line-through': node.completed_at }"
                >
                    {{ node.title }}
                </Link>
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span v-if="node.horizon" class="capitalize">{{ node.horizon }}</span>
                    <template v-if="node.target_date">
                        <span v-if="node.horizon">&middot;</span>
                        <span :class="{ 'font-medium text-rose-600 dark:text-rose-400': node.overdue }">
                            target {{ formatDate(node.target_date) }}
                        </span>
                    </template>
                </p>
            </div>

            <span
                v-if="!node.completed_at"
                class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium md:inline"
                :class="statusBadge(node.status)"
            >
                {{ statusLabel(node.status) }}
            </span>

            <button
                v-if="node.milestones_count > 0 || node.projects_count > 0"
                class="flex shrink-0 items-center gap-1.5 rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted"
                :title="`${node.projects_count} project(s), ${node.milestones_count} milestone(s)`"
                @click="showMilestones = !showMilestones"
            >
                <ChevronDown v-if="showMilestones" class="h-3.5 w-3.5" />
                <ChevronRight v-else class="h-3.5 w-3.5" />
                <span v-if="node.projects_count" class="flex items-center gap-1">
                    <FolderKanban class="h-3 w-3" />{{ node.projects_count }}
                </span>
                <span v-if="node.milestones_count" class="flex items-center gap-1">
                    <Flag class="h-3 w-3" />{{ node.milestones_count }}
                </span>
            </button>

            <div class="flex shrink-0 items-center gap-2">
                <div class="hidden w-32 sm:block">
                    <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full transition-all"
                            :class="progressBar(node)"
                            :style="{ width: `${node.progress}%` }"
                        />
                    </div>
                </div>
                <span class="w-9 text-right text-xs font-medium tabular-nums">{{ node.progress }}%</span>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="rounded p-1 text-muted-foreground opacity-0 transition hover:bg-muted hover:text-foreground focus:opacity-100 group-hover:opacity-100 data-[state=open]:opacity-100"
                            aria-label="Goal actions"
                        >
                            <MoreHorizontal class="h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem @select="emit('edit', node)">
                            <Pencil class="mr-2 h-4 w-4" /> Edit goal
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="emit('add-milestone', node)">
                            <Plus class="mr-2 h-4 w-4" /> Add milestone
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="emit('link-project', node)">
                            <Link2 class="mr-2 h-4 w-4" /> Link a project
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="emit('toggle-complete', node)">
                            <CheckCircle2 class="mr-2 h-4 w-4" />
                            {{ node.completed_at ? 'Reopen' : 'Mark done' }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive" @select="emit('remove', node)">
                            <Trash2 class="mr-2 h-4 w-4" /> Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- What the percentage is made of. -->
        <div
            v-if="showMilestones && (node.milestones.length || node.projects.length)"
            class="space-y-2 border-t border-dashed bg-muted/30 px-3 py-3"
            :style="{ paddingLeft: `${1.75 + depth * 1.25}rem` }"
        >
            <div
                v-for="p in node.projects"
                :key="`project-${p.id}`"
                class="group/p flex items-center gap-3"
            >
                <FolderKanban class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <div class="min-w-0 flex-1">
                    <a :href="p.url" class="truncate text-xs font-medium hover:underline">
                        {{ p.title }}
                    </a>
                    <p class="text-[11px] text-muted-foreground">
                        Project<template v-if="p.due_date"> &middot; due {{ formatDate(p.due_date) }}</template>
                    </p>
                </div>
                <div class="hidden w-24 sm:block">
                    <div class="h-1 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-primary/70" :style="{ width: `${p.progress}%` }" />
                    </div>
                </div>
                <span class="w-9 text-right text-[11px] tabular-nums text-muted-foreground">
                    {{ p.progress }}%
                </span>
                <button
                    class="opacity-0 transition group-hover/p:opacity-100"
                    title="Unlink from this goal"
                    @click="emit('unlink-project', node, p)"
                >
                    <Link2Off class="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
                </button>
            </div>
            <div
                v-for="m in node.milestones"
                :key="m.id"
                class="group/m flex items-center gap-3"
                :class="{ 'opacity-60': m.counted_via_project }"
            >
                <button
                    class="shrink-0 text-muted-foreground transition hover:text-foreground"
                    :title="m.completed_at ? 'Mark as not done' : 'Mark as done'"
                    @click="emit('toggle-milestone', m)"
                >
                    <CheckCircle2 v-if="m.completed_at" class="h-3.5 w-3.5 text-emerald-600" />
                    <Circle v-else class="h-3.5 w-3.5" />
                </button>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium">
                        <span :class="{ 'text-muted-foreground line-through': m.completed_at }">
                            {{ m.title }}
                        </span>
                        <Lock
                            v-if="m.is_manual"
                            class="ml-1 inline h-2.5 w-2.5 text-muted-foreground"
                            aria-label="Progress set by hand rather than counted from tasks"
                        />
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        <a v-if="m.project" :href="m.project.url" class="hover:underline">
                            {{ m.project.title }}
                        </a>
                        <span v-else class="italic">Goal milestone</span>
                        <template v-if="m.due_date">
                            <span> &middot; </span>due {{ formatDate(m.due_date) }}
                        </template>
                        <!-- Shown, but not counted again: its project is linked
                             here and already carries this milestone's progress. -->
                        <span v-if="m.counted_via_project"> &middot; counted via project</span>
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

                <!-- A milestone with a project is only unlinked here; it goes on
                     living in that project. One without a project exists solely
                     for this goal, so deleting is the only way out. -->
                <button
                    v-if="m.project"
                    class="opacity-0 transition group-hover/m:opacity-100"
                    title="Unlink from this goal"
                    @click="emit('unlink-milestone', m)"
                >
                    <Link2Off class="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
                </button>
                <button
                    v-else
                    class="opacity-0 transition group-hover/m:opacity-100"
                    title="Delete milestone"
                    @click="emit('remove-milestone', m)"
                >
                    <Trash2 class="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
                </button>
            </div>
        </div>

        <GoalRow
            v-for="child in node.children"
            :key="child.id"
            :node="child"
            :depth="depth + 1"
            @edit="(n) => emit('edit', n)"
            @remove="(n) => emit('remove', n)"
            @toggle-complete="(n) => emit('toggle-complete', n)"
            @add-milestone="(n) => emit('add-milestone', n)"
            @link-project="(n) => emit('link-project', n)"
            @unlink-project="(n, p) => emit('unlink-project', n, p)"
            @toggle-milestone="(m) => emit('toggle-milestone', m)"
            @unlink-milestone="(m) => emit('unlink-milestone', m)"
            @remove-milestone="(m) => emit('remove-milestone', m)"
        />
    </div>
</template>
