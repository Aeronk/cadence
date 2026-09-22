<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Link2, Plus, Target } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import StatsBanner from '@/components/StatsBanner.vue';
import GoalRow from '@/components/GoalRow.vue';
import GoalFormDialog from '@/components/GoalFormDialog.vue';
import MilestoneFormDialog from '@/components/MilestoneFormDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { buildTree, type Goal, type GoalMilestone, type GoalProject } from '@/lib/goals';

type LinkableMilestone = {
    id: number;
    title: string;
    progress: number;
    project_title: string | null;
};

const props = defineProps<{
    goals: Goal[];
    stats: {
        total: number;
        completed: number;
        at_risk: number;
        off_track: number;
        overdue: number;
    };
    linkable_milestones: LinkableMilestone[];
    projects: { id: number; title: string }[];
}>();

const tree = computed(() => buildTree(props.goals));

const bannerStats = computed(() => [
    { label: 'Goals', value: props.stats.total, color: 'blue' as const },
    { label: 'Completed', value: props.stats.completed, color: 'emerald' as const },
    { label: 'At risk', value: props.stats.at_risk, color: 'amber' as const },
    { label: 'Off track', value: props.stats.off_track, color: 'red' as const },
    { label: 'Overdue', value: props.stats.overdue, color: 'orange' as const },
]);

/* Create / edit — one dialog, `editing` decides which. */
const goalDialogOpen = ref(false);
const editing = ref<Goal | null>(null);

/**
 * Parents the open dialog may offer. Editing a goal excludes its own subtree —
 * moving a goal under one of its descendants would cut the branch loose from
 * every root and it would stop being drawn at all. The server rejects it too;
 * this stops it being offered in the first place.
 */
const parentOptions = computed(() => {
    const banned = new Set<number>();

    if (editing.value) {
        const queue = [editing.value.id];
        while (queue.length) {
            const id = queue.shift()!;
            if (banned.has(id)) continue;
            banned.add(id);
            for (const g of props.goals) {
                if (g.parent_id === id) queue.push(g.id);
            }
        }
    }

    return props.goals
        .filter((g) => !banned.has(g.id))
        .map((g) => ({ id: g.id, title: g.title, type: g.type }));
});

function openCreate() {
    editing.value = null;
    goalDialogOpen.value = true;
}

function openEdit(goal: Goal) {
    // The tree nodes carry a `children` array the form has no use for; the flat
    // record is passed so the dialog only ever sees goal fields.
    editing.value = props.goals.find((g) => g.id === goal.id) ?? goal;
    goalDialogOpen.value = true;
}

/* Milestones under a goal. */
const milestoneDialogOpen = ref(false);
const milestoneGoal = ref<Goal | null>(null);

function openAddMilestone(goal: Goal) {
    milestoneGoal.value = goal;
    milestoneDialogOpen.value = true;
}

function toggleComplete(goal: Goal) {
    router.patch(
        `/goals/${goal.id}`,
        { completed: !goal.completed_at },
        { preserveScroll: true },
    );
}

function remove(goal: Goal) {
    if (
        !confirm(
            `Delete "${goal.title}"? Sub-goals move up a level and project milestones are unlinked, not deleted.`,
        )
    ) {
        return;
    }
    router.delete(`/goals/${goal.id}`, { preserveScroll: true });
}

function toggleMilestone(m: GoalMilestone) {
    router.patch(
        `/milestones/${m.id}`,
        { completed: !m.completed_at },
        { preserveScroll: true },
    );
}

function unlinkMilestone(m: GoalMilestone) {
    if (!confirm(`Unlink "${m.title}" from this goal? It stays in its project.`)) return;
    router.patch(`/milestones/${m.id}`, { goal_id: null }, { preserveScroll: true });
}

function removeMilestone(m: GoalMilestone) {
    if (!confirm(`Delete milestone "${m.title}"?`)) return;
    router.delete(`/milestones/${m.id}`, { preserveScroll: true });
}

/* Projects behind a goal. A linked project contributes its own progress as one
   item, and its milestones stop being counted separately so the same work
   cannot vote twice. */
const projectOpen = ref(false);
const projectGoal = ref<Goal | null>(null);
const projectToLink = ref<number | null>(null);

function openLinkProject(goal: Goal) {
    projectGoal.value = goal;
    projectToLink.value = null;
    projectOpen.value = true;
}

/** Projects not already under this goal. */
const availableProjects = computed(() => {
    const taken = new Set((projectGoal.value?.projects ?? []).map((p) => p.id));
    return props.projects.filter((p) => !taken.has(p.id));
});

function linkProject() {
    if (!projectGoal.value || !projectToLink.value) return;
    router.post(
        `/goals/${projectGoal.value.id}/projects`,
        { project_id: projectToLink.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                projectOpen.value = false;
                projectToLink.value = null;
            },
        },
    );
}

function unlinkProject(goal: Goal, project: GoalProject) {
    if (!confirm(`Unlink "${project.title}" from this goal? The project is not deleted.`)) return;
    router.delete(`/goals/${goal.id}/projects/${project.id}`, { preserveScroll: true });
}

/* Linking an existing project milestone is the other way progress rolls up. */
const linkOpen = ref(false);
const linkGoalId = ref<number | null>(null);
const linkMilestoneId = ref<number | null>(null);

function linkMilestone() {
    if (!linkMilestoneId.value || !linkGoalId.value) return;
    router.patch(
        `/milestones/${linkMilestoneId.value}`,
        { goal_id: linkGoalId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                linkGoalId.value = null;
                linkMilestoneId.value = null;
                linkOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Goals" />

    <AppLayout :breadcrumbs="[{ title: 'Goals', href: '/goals' }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Goals</h1>
                    <p class="text-sm text-muted-foreground">
                        Vision → Goal → Objective. Progress rolls up from milestones automatically.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="linkable_milestones.length && goals.length"
                        variant="outline"
                        @click="linkOpen = true"
                    >
                        <Link2 class="mr-2 h-4 w-4" /> Link milestone
                    </Button>
                    <Button @click="openCreate">
                        <Plus class="mr-2 h-4 w-4" /> New goal
                    </Button>
                </div>
            </div>

            <StatsBanner v-if="goals.length" :stats="bannerStats" />

            <div v-if="tree.length === 0" class="rounded-xl border border-dashed p-12 text-center">
                <Target class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No goals yet. Start with a vision.</p>
                <Button class="mt-4" variant="outline" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" /> New goal
                </Button>
            </div>

            <div v-else class="space-y-3">
                <div v-for="root in tree" :key="root.id" class="rounded-xl border bg-card">
                    <GoalRow
                        :node="root"
                        :depth="0"
                        @edit="openEdit"
                        @remove="remove"
                        @toggle-complete="toggleComplete"
                        @add-milestone="openAddMilestone"
                        @link-project="openLinkProject"
                        @unlink-project="unlinkProject"
                        @toggle-milestone="toggleMilestone"
                        @unlink-milestone="unlinkMilestone"
                        @remove-milestone="removeMilestone"
                    />
                </div>
            </div>
        </div>

        <GoalFormDialog
            v-model:open="goalDialogOpen"
            :goal="editing"
            :parent-options="parentOptions"
        />

        <MilestoneFormDialog
            v-model:open="milestoneDialogOpen"
            :goal-id="milestoneGoal?.id ?? null"
            :goal-title="milestoneGoal?.title ?? ''"
            :projects="projects"
        />

        <Dialog v-model:open="projectOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link a project to this goal</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="linkProject">
                    <p class="text-sm text-muted-foreground">
                        The project's own progress becomes one part of
                        <strong>{{ projectGoal?.title }}</strong>. Milestones inside it stop
                        being counted separately, so nothing is double-counted.
                    </p>
                    <div>
                        <Label for="link-project">Project</Label>
                        <select
                            id="link-project"
                            v-model="projectToLink"
                            required
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null" disabled>Choose a project…</option>
                            <option v-for="p in availableProjects" :key="p.id" :value="p.id">
                                {{ p.title }}
                            </option>
                        </select>
                        <p v-if="!availableProjects.length" class="mt-1 text-xs text-muted-foreground">
                            Every project in this workspace is already linked to this goal.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="!projectToLink">Link</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="linkOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link a milestone to a goal</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="linkMilestone">
                    <p class="text-sm text-muted-foreground">
                        A goal's progress is the average of the milestones under it, and each
                        milestone tracks the tasks assigned to it.
                    </p>
                    <div>
                        <Label for="link-goal">Goal</Label>
                        <select
                            id="link-goal"
                            v-model="linkGoalId"
                            required
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null" disabled>Choose a goal…</option>
                            <option v-for="g in goals" :key="g.id" :value="g.id">
                                {{ g.title }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="link-milestone">Milestone</Label>
                        <select
                            id="link-milestone"
                            v-model="linkMilestoneId"
                            required
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null" disabled>Choose a milestone…</option>
                            <option v-for="m in linkable_milestones" :key="m.id" :value="m.id">
                                {{ m.title
                                }}<template v-if="m.project_title"> — {{ m.project_title }}</template>
                            </option>
                        </select>
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="!linkMilestoneId || !linkGoalId">
                            Link
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
