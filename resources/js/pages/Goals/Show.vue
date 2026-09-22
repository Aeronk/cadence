<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    Circle,
    Flag,
    Link2Off,
    Lock,
    Pencil,
    Plus,
    Target,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import GoalFormDialog from '@/components/GoalFormDialog.vue';
import MilestoneFormDialog from '@/components/MilestoneFormDialog.vue';
import {
    GOAL_STATUSES,
    formatDate,
    progressBar,
    statusBadge,
    statusLabel,
    typeBadge,
    type Goal,
    type GoalMilestone,
} from '@/lib/goals';

const props = defineProps<{
    goal: Goal & { parent: { id: number; title: string; type: string; url: string } | null };
    children: Goal[];
    parent_options: { id: number; title: string; type: string }[];
    projects: { id: number; title: string }[];
}>();

const editOpen = ref(false);
const milestoneOpen = ref(false);
const subGoalOpen = ref(false);

const doneMilestones = computed(
    () => props.goal.milestones.filter((m) => m.completed_at).length,
);

/**
 * `parent_options` deliberately omits this goal's own subtree, so that editing
 * cannot reparent it onto a descendant. Creating a sub-goal is the opposite
 * case — this goal is exactly the parent wanted — so it is added back here.
 */
const subGoalParentOptions = computed(() => [
    { id: props.goal.id, title: props.goal.title, type: props.goal.type },
    ...props.parent_options,
]);

function toggleComplete() {
    router.patch(
        `/goals/${props.goal.id}`,
        { completed: !props.goal.completed_at },
        { preserveScroll: true },
    );
}

function setStatus(status: string) {
    router.patch(`/goals/${props.goal.id}`, { status }, { preserveScroll: true });
}

function remove() {
    if (
        !confirm(
            `Delete "${props.goal.title}"? Sub-goals move up a level and project milestones are unlinked, not deleted.`,
        )
    ) {
        return;
    }
    router.delete(`/goals/${props.goal.id}`, {
        onSuccess: () => router.visit('/goals'),
    });
}

function toggleMilestone(m: GoalMilestone) {
    router.patch(`/milestones/${m.id}`, { completed: !m.completed_at }, { preserveScroll: true });
}

function unlinkMilestone(m: GoalMilestone) {
    if (!confirm(`Unlink "${m.title}" from this goal? It stays in its project.`)) return;
    router.patch(`/milestones/${m.id}`, { goal_id: null }, { preserveScroll: true });
}

function removeMilestone(m: GoalMilestone) {
    if (!confirm(`Delete milestone "${m.title}"?`)) return;
    router.delete(`/milestones/${m.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="goal.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Goals', href: '/goals' },
            { title: goal.title, href: goal.url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wider"
                            :class="typeBadge(goal.type)"
                        >
                            {{ goal.type }}
                        </span>
                        <Link
                            v-if="goal.parent"
                            :href="goal.parent.url"
                            class="text-xs text-muted-foreground hover:underline"
                        >
                            under {{ goal.parent.title }}
                        </Link>
                    </div>
                    <h1
                        class="text-2xl font-bold"
                        :class="{ 'text-muted-foreground line-through': goal.completed_at }"
                    >
                        {{ goal.title }}
                    </h1>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span v-if="goal.horizon" class="capitalize">{{ goal.horizon }}</span>
                        <span v-if="goal.target_date" class="flex items-center gap-1">
                            <CalendarDays class="h-3.5 w-3.5" />
                            <span :class="{ 'font-medium text-rose-600 dark:text-rose-400': goal.overdue }">
                                {{ formatDate(goal.target_date) }}
                                <template v-if="goal.overdue">(overdue)</template>
                            </span>
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" @click="toggleComplete">
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        {{ goal.completed_at ? 'Reopen' : 'Mark done' }}
                    </Button>
                    <Button variant="outline" @click="editOpen = true">
                        <Pencil class="mr-2 h-4 w-4" /> Edit
                    </Button>
                    <Button variant="outline" @click="remove">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Progress + status -->
            <div class="rounded-xl border bg-card p-5">
                <div class="mb-2 flex items-end justify-between">
                    <div>
                        <p class="text-3xl font-bold tabular-nums">{{ goal.progress }}%</p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="goal.completed_at">Completed</template>
                            <template v-else-if="goal.is_leaf">Set by hand — nothing beneath this goal yet</template>
                            <template v-else>
                                Averaged from {{ goal.milestones_count }} milestone(s)
                                <template v-if="children.length">
                                    and {{ children.length }} sub-goal(s)
                                </template>
                            </template>
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <button
                            v-for="s in GOAL_STATUSES"
                            :key="s.value"
                            class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                            :class="
                                goal.status === s.value
                                    ? statusBadge(s.value)
                                    : 'text-muted-foreground hover:bg-muted'
                            "
                            @click="setStatus(s.value)"
                        >
                            {{ s.label }}
                        </button>
                    </div>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full transition-all"
                        :class="progressBar(goal)"
                        :style="{ width: `${goal.progress}%` }"
                    />
                </div>
            </div>

            <!-- Description -->
            <div v-if="goal.description" class="rounded-xl border bg-card p-5">
                <h2 class="mb-2 text-sm font-semibold">Description</h2>
                <div class="prose prose-sm max-w-none dark:prose-invert" v-html="goal.description" />
            </div>

            <!-- Milestones -->
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <Flag class="h-4 w-4" />
                        Milestones
                        <span class="font-normal text-muted-foreground">
                            {{ doneMilestones }}/{{ goal.milestones.length }} done
                        </span>
                    </h2>
                    <Button size="sm" variant="outline" @click="milestoneOpen = true">
                        <Plus class="mr-1.5 h-3.5 w-3.5" /> Add milestone
                    </Button>
                </div>

                <div
                    v-if="goal.milestones.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No milestones yet. Progress stays where you set it until this goal has some.
                </div>

                <div v-else class="divide-y rounded-xl border bg-card">
                    <div
                        v-for="m in goal.milestones"
                        :key="m.id"
                        class="group flex items-center gap-3 p-3"
                    >
                        <button
                            class="shrink-0 text-muted-foreground transition hover:text-foreground"
                            :title="m.completed_at ? 'Mark as not done' : 'Mark as done'"
                            @click="toggleMilestone(m)"
                        >
                            <CheckCircle2 v-if="m.completed_at" class="h-4 w-4 text-emerald-600" />
                            <Circle v-else class="h-4 w-4" />
                        </button>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                <span :class="{ 'text-muted-foreground line-through': m.completed_at }">
                                    {{ m.title }}
                                </span>
                                <Lock
                                    v-if="m.is_manual"
                                    class="ml-1 inline h-3 w-3 text-muted-foreground"
                                    aria-label="Progress set by hand rather than counted from tasks"
                                />
                            </p>
                            <p class="text-xs text-muted-foreground">
                                <a v-if="m.project" :href="m.project.url" class="hover:underline">
                                    {{ m.project.title }}
                                </a>
                                <span v-else class="italic">Goal milestone</span>
                                <template v-if="m.due_date">
                                    <span> &middot; </span>due {{ formatDate(m.due_date) }}
                                </template>
                            </p>
                        </div>

                        <div class="hidden w-28 sm:block">
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div class="h-full bg-primary/70" :style="{ width: `${m.progress}%` }" />
                            </div>
                        </div>
                        <span class="w-10 text-right text-xs tabular-nums text-muted-foreground">
                            {{ m.progress }}%
                        </span>

                        <button
                            v-if="m.project"
                            class="opacity-0 transition group-hover:opacity-100"
                            title="Unlink from this goal"
                            @click="unlinkMilestone(m)"
                        >
                            <Link2Off class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                        </button>
                        <button
                            v-else
                            class="opacity-0 transition group-hover:opacity-100"
                            title="Delete milestone"
                            @click="removeMilestone(m)"
                        >
                            <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                        </button>
                    </div>
                </div>
            </section>

            <!-- Sub-goals -->
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <Target class="h-4 w-4" /> Sub-goals
                    </h2>
                    <Button size="sm" variant="outline" @click="subGoalOpen = true">
                        <Plus class="mr-1.5 h-3.5 w-3.5" /> Add sub-goal
                    </Button>
                </div>

                <div
                    v-if="children.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Nothing underneath this goal.
                </div>

                <div v-else class="divide-y rounded-xl border bg-card">
                    <Link
                        v-for="c in children"
                        :key="c.id"
                        :href="c.url"
                        class="flex items-center gap-3 p-3 transition hover:bg-muted/50"
                    >
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wider"
                            :class="typeBadge(c.type)"
                        >
                            {{ c.type }}
                        </span>
                        <span
                            class="min-w-0 flex-1 truncate text-sm font-medium"
                            :class="{ 'text-muted-foreground line-through': c.completed_at }"
                        >
                            {{ c.title }}
                        </span>
                        <span
                            v-if="!c.completed_at"
                            class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium md:inline"
                            :class="statusBadge(c.status)"
                        >
                            {{ statusLabel(c.status) }}
                        </span>
                        <div class="hidden w-28 sm:block">
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full"
                                    :class="progressBar(c)"
                                    :style="{ width: `${c.progress}%` }"
                                />
                            </div>
                        </div>
                        <span class="w-10 text-right text-xs tabular-nums">{{ c.progress }}%</span>
                    </Link>
                </div>
            </section>
        </div>

        <GoalFormDialog v-model:open="editOpen" :goal="goal" :parent-options="parent_options" />

        <GoalFormDialog
            v-model:open="subGoalOpen"
            :goal="null"
            :parent-options="subGoalParentOptions"
            :default-parent-id="goal.id"
        />

        <MilestoneFormDialog
            v-model:open="milestoneOpen"
            :goal-id="goal.id"
            :goal-title="goal.title"
            :projects="projects"
        />
    </AppLayout>
</template>
