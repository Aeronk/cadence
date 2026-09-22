<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Link2, Target, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import GoalRow from '@/components/GoalRow.vue';

type Goal = {
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
};

type Milestone = {
    id: number;
    title: string;
    progress: number;
    is_manual: boolean;
    due_date: string | null;
    completed_at: string | null;
    project: { id: number; title: string; url: string } | null;
};

type LinkableMilestone = {
    id: number;
    title: string;
    progress: number;
    project_title: string | null;
};

const props = defineProps<{
    goals: Goal[];
    linkable_milestones: LinkableMilestone[];
}>();

const dialogOpen = ref(false);
const form = useForm({
    type: 'goal' as 'vision' | 'goal' | 'objective',
    parent_id: null as number | null,
    title: '',
    description: '',
    horizon: 'year' as 'year' | 'quarter' | 'month',
    target_date: '',
    // No progress field: `progress` is a reserved useForm name (so it never
    // actually worked), and a goal's progress is the average of its milestones.
});

function submit() {
    form.post('/goals', {
        onSuccess: () => {
            form.reset();
            form.type = 'goal';
            form.horizon = 'year';
            dialogOpen.value = false;
        },
    });
}

function remove(g: Goal) {
    if (!confirm(`Delete "${g.title}"?`)) return;
    router.delete(`/goals/${g.id}`, { preserveScroll: true });
}

function setProgress(g: Goal, v: number) {
    router.patch(`/goals/${g.id}`, { progress: v }, { preserveScroll: true });
}

/* Linking a milestone to a goal is what makes the goal's progress roll up. */
const linkOpen = ref(false);
const linkForm = useForm({
    goal_id: null as number | null,
    milestone_id: null as number | null,
});

function linkMilestone() {
    if (!linkForm.milestone_id) return;
    router.patch(
        `/milestones/${linkForm.milestone_id}`,
        { goal_id: linkForm.goal_id },
        {
            preserveScroll: true,
            onSuccess: () => {
                linkForm.reset();
                linkOpen.value = false;
            },
        },
    );
}

// Build a tree from flat list (parent_id chain)
type Node = Goal & { children: Node[] };
const tree = computed<Node[]>(() => {
    const map: Record<number, Node> = {};
    const roots: Node[] = [];
    for (const g of props.goals) map[g.id] = { ...g, children: [] };
    for (const g of props.goals) {
        if (g.parent_id && map[g.parent_id]) {
            map[g.parent_id].children.push(map[g.id]);
        } else {
            roots.push(map[g.id]);
        }
    }
    return roots;
});

const typeBadge = (t: string) => ({
    vision: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    goal: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    objective: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
}[t] ?? 'bg-muted');
</script>

<template>
    <Head title="Goals" />

    <AppLayout :breadcrumbs="[{ title: 'Goals', href: '/goals' }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Goals</h1>
                    <p class="text-sm text-muted-foreground">
                        Vision → Goal → Objective. Progress rolls up from milestones automatically.
                    </p>
                </div>
                <div class="flex gap-2">
                <Dialog v-if="linkable_milestones.length && goals.length" v-model:open="linkOpen">
                    <DialogTrigger as-child>
                        <Button variant="outline">
                            <Link2 class="mr-2 h-4 w-4" /> Link milestone
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Link a milestone to a goal</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="linkMilestone">
                            <p class="text-sm text-muted-foreground">
                                A goal's progress is the average of the milestones under it,
                                and each milestone tracks the tasks assigned to it.
                            </p>
                            <div>
                                <Label for="link-goal">Goal</Label>
                                <select
                                    id="link-goal"
                                    v-model="linkForm.goal_id"
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
                                    v-model="linkForm.milestone_id"
                                    required
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option :value="null" disabled>Choose a milestone…</option>
                                    <option
                                        v-for="m in linkable_milestones"
                                        :key="m.id"
                                        :value="m.id"
                                    >
                                        {{ m.title }}<template v-if="m.project_title"> — {{ m.project_title }}</template>
                                    </option>
                                </select>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="!linkForm.milestone_id || !linkForm.goal_id">
                                    Link
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" /> New goal
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>New goal</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="type">Type</Label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="vision">Vision</option>
                                        <option value="goal">Goal</option>
                                        <option value="objective">Objective</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="parent_id">Parent</Label>
                                    <select
                                        id="parent_id"
                                        v-model="form.parent_id"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option :value="null">— Top level —</option>
                                        <option v-for="g in goals" :key="g.id" :value="g.id">
                                            {{ g.title }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <Label for="title">Title</Label>
                                <Input id="title" v-model="form.title" required />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="horizon">Horizon</Label>
                                    <select
                                        id="horizon"
                                        v-model="form.horizon"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="year">Year</option>
                                        <option value="quarter">Quarter</option>
                                        <option value="month">Month</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="target_date">Target date</Label>
                                    <Input id="target_date" v-model="form.target_date" type="date" />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
                </div>
            </div>

            <div v-if="tree.length === 0" class="rounded-xl border border-dashed p-12 text-center">
                <Target class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No goals yet. Start with a vision.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="root in tree"
                    :key="root.id"
                    class="rounded-xl border bg-card"
                >
                    <GoalRow
                        :node="root"
                        :depth="0"
                        @remove="remove"
                        @progress="setProgress"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
