<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Archive,
    ArchiveRestore,
    Briefcase,
    Calendar,
    CheckCircle2,
    DollarSign,
    FileText,
    Info,
    LayoutGrid,
    Link2,
    Link2Off,
    ListTodo,
    MessageSquare,
    Milestone as MilestoneIcon,
    PauseCircle,
    Paperclip,
    Plane,
    Pencil,
    PlayCircle,
    Plus,
    StickyNote,
    Target,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentThread from '@/components/CommentThread.vue';
import KanbanBoard from '@/components/KanbanBoard.vue';
import ProjectFiles from '@/components/ProjectFiles.vue';
import RichEditor from '@/components/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import projectsRoutes from '@/routes/projects';
import tasksRoutes from '@/routes/tasks';
import milestonesRoutes from '@/routes/milestones';

type Project = {
    id: number;
    title: string;
    description: string | null;
    status: { id: number; name: string; color: string } | null;
    priority: { id: number; name: string; color: string } | null;
    status_id: number | null;
    priority_id: number | null;
    creator: { name: string };
    members: { id: number; name: string; email: string }[];
    tags: { id: number; name: string; color: string }[];
    clients: { id: number; name: string; company: string | null }[];
    start_date: string | null;
    due_date: string | null;
    budget: string | null;
    budget_currency: string | null;
    state: 'active' | 'on_hold' | 'completed';
    completed_at: string | null;
    on_hold_at: string | null;
    archived_at: string | null;
};
type Comment = { id: number; body: string; user: { id: number; name: string }; created_at: string };
type Priority = { id: number; name: string; color: string; level: number };
type Task = {
    id: number;
    title: string;
    status_id: number | null;
    priority: Priority | null;
    due_date: string | null;
    completed_at: string | null;
    position: number;
    assignees: { id: number; name: string }[];
};
type Status = { id: number; name: string; color: string; position: number; is_completed: boolean };
type Milestone = {
    id: number;
    title: string;
    description: string | null;
    due_date: string | null;
    /** The effective percentage: pinned if manual, otherwise counted from tasks. */
    progress: number;
    manual_progress: number | null;
    is_manual: boolean;
    tasks_count: number;
    completed_tasks_count: number;
    completed_at: string | null;
    position: number;
    creator: { id: number; name: string } | null;
    goal: { id: number; title: string } | null;
};
type ProjectFile = {
    id: number;
    original_name: string;
    mime_type: string | null;
    size_bytes: number;
    created_at: string;
    uploaded_by: number;
    uploader: { id: number; name: string } | null;
};
type Member = { id: number; name: string; email: string };

type LinkedGoal = { id: number; title: string; type: string; status: string; url: string };
type ProjectMeeting = {
    id: number; title: string; starts_at: string | null; ends_at: string | null;
    location: string | null; url: string;
};
type ProjectTrip = {
    id: number; name: string; destination: string | null; departs_at: string | null;
    returns_at: string | null; status: string; traveller: string | null; url: string;
};
type ProjectNote = { id: number; title: string; body: string | null; color: string | null; is_pinned: boolean; updated_at: string };
type ProjectTodo = { id: number; title: string; due_date: string | null; completed_at: string | null; priority: string };

const props = defineProps<{
    project: Project;
    comments: Comment[];
    tasks: Task[];
    statuses: Status[];
    priorities: Priority[];
    milestones: Milestone[];
    files: ProjectFile[];
    workspace_members: Member[];
    meetings: ProjectMeeting[];
    trips: ProjectTrip[];
    // Personal records, so the server only sends the ones belonging to whoever
    // is looking — a project page is shared with the whole team.
    notes: ProjectNote[];
    todos: ProjectTodo[];
    goals: LinkedGoal[];
    linkable_goals: { id: number; title: string; type: string }[];
    progress: number;
}>();

const colorPill = (color: string | undefined) => ({
    blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    purple: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    gray: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
} as Record<string, string>)[color ?? 'gray'] ?? 'bg-muted text-muted-foreground';

const page = usePage<{ auth: { user: { id: number } } }>();

const tab = ref<
    'overview' | 'board' | 'milestones' | 'work' | 'files' | 'comments'
>('overview');
const workCount = computed(
    () => props.meetings.length + props.trips.length + props.notes.length + props.todos.length,
);
const tabs = computed(() => [
    { id: 'overview' as const, label: 'Overview', icon: Info },
    { id: 'board' as const, label: 'Board', icon: LayoutGrid },
    { id: 'milestones' as const, label: `Milestones (${props.milestones.length})`, icon: MilestoneIcon },
    // Everything else that belongs to this project but is not a task: meetings,
    // travel, and the notes and to-dos you filed under it.
    { id: 'work' as const, label: `Related (${workCount.value})`, icon: Link2 },
    { id: 'files' as const, label: `Files (${props.files.length})`, icon: Paperclip },
    { id: 'comments' as const, label: `Comments (${props.comments.length})`, icon: MessageSquare },
]);

/* Goals this project serves. Personal to the viewer, hence scoped server-side. */
const goalOpen = ref(false);
const goalToLink = ref<number | null>(null);

function linkGoal() {
    if (!goalToLink.value) return;
    router.post(
        `/goals/${goalToLink.value}/projects`,
        { project_id: props.project.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                goalOpen.value = false;
                goalToLink.value = null;
            },
        },
    );
}

function unlinkGoal(goal: LinkedGoal) {
    if (!confirm(`Unlink this project from "${goal.title}"? Nothing is deleted.`)) return;
    router.delete(`/goals/${goal.id}/projects/${props.project.id}`, { preserveScroll: true });
}

const isArchived = computed(() => props.project.archived_at !== null);

// Edit dialog
const editOpen = ref(false);
const editForm = useForm({
    title: props.project.title,
    description: props.project.description ?? '',
    start_date: props.project.start_date ?? '',
    due_date: props.project.due_date ?? '',
    status_id: props.project.status_id ?? '',
    priority_id: props.project.priority_id ?? '',
    budget: props.project.budget ?? '',
    budget_currency: props.project.budget_currency ?? 'USD',
});

function saveEdit() {
    editForm.patch(projectsRoutes.update(props.project.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

function transition(state: 'active' | 'on_hold' | 'completed') {
    router.patch(
        projectsRoutes.update(props.project.id).url,
        { state },
        { preserveScroll: true },
    );
}

function deleteProject() {
    if (!confirm(`Delete project "${props.project.title}"? Tasks and milestones will be soft-deleted too.`)) return;
    router.delete(projectsRoutes.destroy(props.project.id).url);
}

function toggleArchive() {
    if (isArchived.value) {
        router.delete(`/projects/${props.project.id}/archive`, { preserveScroll: true });
    } else {
        if (!confirm('Archive this project? It will be hidden from the active list.')) return;
        router.post(`/projects/${props.project.id}/archive`, {}, { preserveScroll: true });
    }
}

// Members
const memberCandidates = computed(() => {
    const ids = new Set(props.project.members.map((m) => m.id));
    return props.workspace_members.filter((m) => !ids.has(m.id));
});
const memberSelect = ref<number | ''>('');

function addMember() {
    if (!memberSelect.value) return;
    const next = [...props.project.members.map((m) => m.id), memberSelect.value];
    router.patch(
        projectsRoutes.update(props.project.id).url,
        { member_ids: next },
        { preserveScroll: true, onSuccess: () => (memberSelect.value = '') },
    );
}

function removeMember(memberId: number) {
    const next = props.project.members.map((m) => m.id).filter((id) => id !== memberId);
    router.patch(
        projectsRoutes.update(props.project.id).url,
        { member_ids: next },
        { preserveScroll: true },
    );
}

// Milestones
const milestoneOpen = ref(false);
const milestoneForm = useForm({
    project_id: props.project.id,
    title: '',
    description: '',
    due_date: '',
});

function addMilestone() {
    // No progress field: a new milestone starts tracking its tasks, and the bar
    // fills as they are completed.
    milestoneForm.post(milestonesRoutes.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            milestoneForm.reset('title', 'description', 'due_date');
            milestoneOpen.value = false;
        },
    });
}

function toggleMilestone(m: Milestone) {
    router.patch(
        milestonesRoutes.update(m.id).url,
        { completed: !m.completed_at },
        { preserveScroll: true },
    );
}

/** Pin progress to a hand-entered number. */
function updateMilestoneProgress(m: Milestone, value: number) {
    router.patch(
        milestonesRoutes.update(m.id).url,
        { manual_progress: value },
        { preserveScroll: true },
    );
}

/** Hand progress back to the task count. */
function trackMilestoneFromTasks(m: Milestone) {
    router.patch(
        milestonesRoutes.update(m.id).url,
        { manual_progress: null },
        { preserveScroll: true },
    );
}

function deleteMilestone(m: Milestone) {
    if (!confirm(`Delete milestone "${m.title}"?`)) return;
    router.delete(milestonesRoutes.destroy(m.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="project.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Projects', href: projectsRoutes.index().url },
            { title: project.title, href: projectsRoutes.show(project.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <header class="flex flex-col gap-3">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">{{ project.title }}</h1>
                            <span
                                v-if="project.state === 'completed'"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="h-3 w-3" /> Completed
                            </span>
                            <span
                                v-else-if="project.state === 'on_hold'"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                            >
                                <PauseCircle class="h-3 w-3" /> On hold
                            </span>
                            <span
                                v-if="isArchived"
                                class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700 dark:bg-orange-900/40 dark:text-orange-300"
                            >
                                Archived
                            </span>
                        </div>
                        <div
                            v-if="project.description"
                            class="prose prose-sm mt-1 max-w-none text-muted-foreground dark:prose-invert"
                            v-html="project.description"
                        ></div>
                    </div>

                    <div class="-mx-2 flex shrink-0 items-center gap-2 overflow-x-auto px-2 md:overflow-visible">
                        <Link
                            :href="tasksRoutes.index({ query: { project_id: project.id } }).url"
                            class="shrink-0 rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                        >
                            All tasks
                        </Link>
                        <Button variant="outline" size="sm" @click="editOpen = true">
                            <Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit
                        </Button>
                        <Button
                            v-if="project.state !== 'completed'"
                            variant="outline"
                            size="sm"
                            class="text-emerald-600 hover:text-emerald-700"
                            title="Mark this project complete"
                            @click="transition('completed')"
                        >
                            <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" /> Mark complete
                        </Button>
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            @click="transition('active')"
                        >
                            <PlayCircle class="mr-1.5 h-3.5 w-3.5" /> Reopen
                        </Button>
                        <Button
                            v-if="project.state === 'active'"
                            variant="outline"
                            size="sm"
                            class="text-amber-600 hover:text-amber-700"
                            title="Pause work on this project"
                            @click="transition('on_hold')"
                        >
                            <PauseCircle class="mr-1.5 h-3.5 w-3.5" /> On hold
                        </Button>
                        <Button
                            v-else-if="project.state === 'on_hold'"
                            variant="outline"
                            size="sm"
                            @click="transition('active')"
                        >
                            <PlayCircle class="mr-1.5 h-3.5 w-3.5" /> Resume
                        </Button>
                        <Button variant="outline" size="sm" @click="toggleArchive">
                            <component :is="isArchived ? ArchiveRestore : Archive" class="mr-1.5 h-3.5 w-3.5" />
                            {{ isArchived ? 'Restore' : 'Archive' }}
                        </Button>
                        <Button variant="outline" size="sm" class="text-destructive" @click="deleteProject">
                            <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Delete
                        </Button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span
                        v-if="project.status"
                        class="rounded-full px-2 py-0.5"
                        :class="colorPill(project.status.color)"
                    >
                        {{ project.status.name }}
                    </span>
                    <span
                        v-if="project.priority"
                        class="rounded-full px-2 py-0.5"
                        :class="colorPill(project.priority.color)"
                    >
                        ⚑ {{ project.priority.name }}
                    </span>
                    <span
                        v-if="project.budget"
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-green-700 dark:bg-green-900/40 dark:text-green-300"
                    >
                        <DollarSign class="h-3 w-3" />
                        {{ project.budget_currency ?? '' }} {{ Number(project.budget).toLocaleString() }}
                    </span>
                    <span v-for="tag in project.tags" :key="tag.id" class="rounded-full bg-primary/10 px-2 py-0.5 text-primary">
                        #{{ tag.name }}
                    </span>
                    <span
                        v-for="c in project.clients"
                        :key="c.id"
                        class="flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-primary"
                    >
                        <Briefcase class="h-3 w-3" /> {{ c.name }}
                    </span>
                </div>
            </header>

            <!-- Edit dialog -->
            <Dialog v-model:open="editOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit project</DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="saveEdit">
                        <div>
                            <Label for="edit-title">Title</Label>
                            <Input id="edit-title" v-model="editForm.title" required />
                        </div>
                        <div>
                            <Label>Description</Label>
                            <RichEditor v-model="editForm.description" placeholder="What is this project about?" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="edit-status">Status</Label>
                                <select
                                    id="edit-status"
                                    v-model="editForm.status_id"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">— None —</option>
                                    <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <Label for="edit-priority">Priority</Label>
                                <select
                                    id="edit-priority"
                                    v-model="editForm.priority_id"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">— None —</option>
                                    <option v-for="p in priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="edit-start">Start date</Label>
                                <Input id="edit-start" v-model="editForm.start_date" type="date" />
                            </div>
                            <div>
                                <Label for="edit-due">Due date</Label>
                                <Input id="edit-due" v-model="editForm.due_date" type="date" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <Label for="edit-budget">Budget</Label>
                                <Input
                                    id="edit-budget"
                                    v-model="editForm.budget"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                            </div>
                            <div>
                                <Label for="edit-currency">Currency</Label>
                                <Input id="edit-currency" v-model="editForm.budget_currency" maxlength="3" placeholder="USD" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <nav class="-mb-px flex gap-6 border-b overflow-x-auto">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="flex shrink-0 items-center gap-2 border-b-2 pb-2 text-sm transition"
                    :class="
                        tab === t.id
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="tab = t.id"
                >
                    <component :is="t.icon" class="h-4 w-4" />
                    {{ t.label }}
                </button>
            </nav>

            <!-- Overview -->
            <section v-if="tab === 'overview'" class="grid gap-6 md:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Owner</p>
                    <p class="mt-1 font-medium">{{ project.creator.name }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Calendar class="h-3 w-3" /> Start date
                    </p>
                    <p class="mt-1 font-medium">{{ project.start_date ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Calendar class="h-3 w-3" /> Due date
                    </p>
                    <p class="mt-1 font-medium">{{ project.due_date ?? '—' }}</p>
                </div>

                <!-- The outcomes this project exists to move. Personal to the
                     viewer: goals belong to individuals, not to the project. -->
                <div class="rounded-lg border p-4 md:col-span-3">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h2 class="flex items-center gap-2 font-semibold">
                                <Target class="h-4 w-4" /> Goals ({{ goals.length }})
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                This project's progress ({{ progress }}%) counts towards each of these.
                                Only your own goals are shown.
                            </p>
                        </div>
                        <Button
                            v-if="linkable_goals.length"
                            variant="outline"
                            size="sm"
                            @click="goalOpen = true"
                        >
                            <Link2 class="mr-1.5 h-3.5 w-3.5" /> Link goal
                        </Button>
                    </div>

                    <p v-if="!goals.length" class="text-sm text-muted-foreground">
                        Not linked to any of your goals yet.
                    </p>
                    <div v-else class="flex flex-wrap gap-2">
                        <span
                            v-for="g in goals"
                            :key="g.id"
                            class="group flex items-center gap-2 rounded-full border bg-background py-1 pl-3 pr-2 text-sm"
                        >
                            <a :href="g.url" class="hover:underline">{{ g.title }}</a>
                            <span class="text-[10px] uppercase tracking-wide text-muted-foreground">
                                {{ g.type }}
                            </span>
                            <button
                                class="opacity-0 transition group-hover:opacity-100"
                                title="Unlink"
                                @click="unlinkGoal(g)"
                            >
                                <Link2Off class="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
                            </button>
                        </span>
                    </div>
                </div>

                <!-- Team management -->
                <div class="rounded-lg border p-4 md:col-span-3">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Team ({{ project.members.length }})</h2>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="m in project.members"
                            :key="m.id"
                            class="group flex items-center gap-2 rounded-full bg-muted py-1 pl-3 pr-1.5 text-sm"
                        >
                            <span
                                class="grid h-5 w-5 place-items-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground"
                            >
                                {{ m.name.charAt(0).toUpperCase() }}
                            </span>
                            {{ m.name }}
                            <button
                                title="Remove from project"
                                class="rounded-full p-0.5 opacity-0 transition hover:bg-foreground/10 group-hover:opacity-100"
                                @click="removeMember(m.id)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <select
                            v-model="memberSelect"
                            :disabled="memberCandidates.length === 0"
                            class="flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm disabled:opacity-60"
                        >
                            <option value="" disabled>
                                {{ memberCandidates.length === 0
                                    ? 'All workspace members are already on this project'
                                    : 'Add workspace member…' }}
                            </option>
                            <option v-for="m in memberCandidates" :key="m.id" :value="m.id">
                                {{ m.name }} ({{ m.email }})
                            </option>
                        </select>
                        <Button size="sm" :disabled="!memberSelect" @click="addMember">
                            <UserPlus class="mr-1.5 h-3.5 w-3.5" /> Add
                        </Button>
                    </div>
                    <p v-if="workspace_members.length === 0" class="mt-2 text-xs text-muted-foreground">
                        Invite teammates to your workspace from Settings → Workspace to assign them here.
                    </p>
                </div>

                <div v-if="project.clients.length" class="rounded-lg border p-4 md:col-span-3">
                    <h2 class="mb-3 font-semibold">Clients</h2>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="c in project.clients"
                            :key="c.id"
                            class="rounded-full bg-primary/10 px-3 py-1 text-sm text-primary"
                        >
                            {{ c.name }}<span v-if="c.company" class="opacity-70"> · {{ c.company }}</span>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Kanban -->
            <section v-else-if="tab === 'board'">
                <KanbanBoard :statuses="statuses" :tasks="tasks" :project-id="project.id" />
            </section>

            <!-- Milestones -->
            <section v-else-if="tab === 'milestones'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">Track key deliverables and progress.</p>
                    <Button size="sm" @click="milestoneOpen = true">
                        <Plus class="mr-1.5 h-3.5 w-3.5" /> New milestone
                    </Button>
                </div>

                <Dialog v-model:open="milestoneOpen">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>New milestone</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="addMilestone">
                            <div>
                                <Label for="m-title">Title</Label>
                                <Input id="m-title" v-model="milestoneForm.title" required />
                            </div>
                            <div>
                                <Label>Description</Label>
                                <RichEditor v-model="milestoneForm.description" placeholder="Definition of done…" />
                            </div>
                            <div>
                                <Label for="m-due">Due date</Label>
                                <Input id="m-due" v-model="milestoneForm.due_date" type="date" />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Progress is counted from the tasks assigned to this milestone.
                                You can override it by hand afterwards.
                            </p>
                            <DialogFooter>
                                <Button type="submit" :disabled="milestoneForm.processing">Add</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <div v-if="milestones.length === 0" class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                    No milestones yet.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="m in milestones"
                        :key="m.id"
                        class="group rounded-lg border p-4"
                        :class="{ 'opacity-60': m.completed_at }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <label class="flex flex-1 items-start gap-3">
                                <input
                                    type="checkbox"
                                    :checked="m.completed_at !== null"
                                    class="mt-1 h-4 w-4"
                                    @change="toggleMilestone(m)"
                                />
                                <div class="flex-1">
                                    <p
                                        class="font-medium"
                                        :class="{ 'line-through text-muted-foreground': m.completed_at }"
                                    >
                                        {{ m.title }}
                                    </p>
                                    <div
                                        v-if="m.description"
                                        class="prose prose-sm mt-1 max-w-none text-sm text-muted-foreground dark:prose-invert"
                                        v-html="m.description"
                                    ></div>
                                </div>
                            </label>

                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                <span v-if="m.due_date">{{ m.due_date }}</span>
                                <button
                                    title="Delete"
                                    class="opacity-0 transition group-hover:opacity-100"
                                    @click="deleteMilestone(m)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-3">
                            <input
                                type="range"
                                min="0"
                                max="100"
                                :value="m.progress"
                                class="flex-1"
                                @change="(e) => updateMilestoneProgress(m, Number((e.target as HTMLInputElement).value))"
                            />
                            <span class="text-xs font-medium tabular-nums">{{ m.progress }}%</span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span v-if="m.is_manual" class="flex items-center gap-1">
                                Set by hand
                                <button class="underline hover:text-foreground" @click="trackMilestoneFromTasks(m)">
                                    track tasks instead
                                </button>
                            </span>
                            <span v-else>
                                {{ m.completed_tasks_count }} of {{ m.tasks_count }}
                                task{{ m.tasks_count === 1 ? '' : 's' }} done
                            </span>
                            <span v-if="m.goal" class="flex items-center gap-1">
                                &middot; rolls up to
                                <a href="/goals" class="underline hover:text-foreground">{{ m.goal.title }}</a>
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Files -->
            <!-- Everything that belongs to this project but is not a task. -->
            <section v-else-if="tab === 'work'" class="space-y-6">
                <div>
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
                        <Calendar class="h-4 w-4" /> Meetings ({{ meetings.length }})
                    </h3>
                    <div
                        v-if="!meetings.length"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        No meetings on this project yet.
                    </div>
                    <div v-else class="divide-y rounded-lg border">
                        <a
                            v-for="m in meetings"
                            :key="m.id"
                            :href="m.url"
                            class="flex items-center gap-3 p-3 transition hover:bg-muted/50"
                        >
                            <Calendar class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ m.title }}</span>
                            <span class="shrink-0 text-xs text-muted-foreground">
                                {{ m.starts_at ? new Date(m.starts_at).toLocaleString() : '' }}
                            </span>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
                        <Plane class="h-4 w-4" /> Travel ({{ trips.length }})
                    </h3>
                    <div
                        v-if="!trips.length"
                        class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        No travel filed under this project.
                    </div>
                    <div v-else class="divide-y rounded-lg border">
                        <a
                            v-for="t in trips"
                            :key="t.id"
                            :href="t.url"
                            class="flex items-center gap-3 p-3 transition hover:bg-muted/50"
                        >
                            <Plane class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ t.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="t.destination">{{ t.destination }} &middot; </span>
                                    {{ t.departs_at }} → {{ t.returns_at }}
                                    <span v-if="t.traveller"> &middot; {{ t.traveller }}</span>
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] capitalize">
                                {{ t.status?.replace('_', ' ') }}
                            </span>
                        </a>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
                            <StickyNote class="h-4 w-4" /> Your notes ({{ notes.length }})
                        </h3>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Notes stay private to you, even on a shared project.
                        </p>
                        <div
                            v-if="!notes.length"
                            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            Nothing filed here.
                        </div>
                        <div v-else class="divide-y rounded-lg border">
                            <div v-for="n in notes" :key="n.id" class="p-3">
                                <p class="truncate text-sm font-medium">{{ n.title }}</p>
                                <p class="truncate text-xs text-muted-foreground" v-html="n.body || ''" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold">
                            <ListTodo class="h-4 w-4" /> Your to-dos ({{ todos.length }})
                        </h3>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Personal to you, unlike the project's tasks.
                        </p>
                        <div
                            v-if="!todos.length"
                            class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            Nothing filed here.
                        </div>
                        <div v-else class="divide-y rounded-lg border">
                            <div v-for="t in todos" :key="t.id" class="flex items-center gap-2 p-3">
                                <CheckCircle2
                                    v-if="t.completed_at"
                                    class="h-3.5 w-3.5 shrink-0 text-emerald-600"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-sm"
                                    :class="{ 'text-muted-foreground line-through': t.completed_at }"
                                >
                                    {{ t.title }}
                                </span>
                                <span v-if="t.due_date" class="shrink-0 text-xs text-muted-foreground">
                                    {{ t.due_date }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="tab === 'files'">
                <ProjectFiles
                    :project-id="project.id"
                    :files="files"
                    :current-user-id="page.props.auth.user.id"
                    :can-manage="true"
                />
            </section>

            <!-- Comments -->
            <section v-else-if="tab === 'comments'">
                <CommentThread
                    commentable-type="project"
                    :commentable-id="project.id"
                    :comments="comments"
                    :current-user-id="page.props.auth.user.id"
                />
            </section>
        </div>

        <Dialog v-model:open="goalOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Link this project to a goal</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="linkGoal">
                    <p class="text-sm text-muted-foreground">
                        This project's progress will count towards the goal. Milestones inside
                        the project stop being counted separately, so nothing is double-counted.
                    </p>
                    <div>
                        <Label for="project-goal">Goal</Label>
                        <select
                            id="project-goal"
                            v-model="goalToLink"
                            required
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null" disabled>Choose a goal…</option>
                            <option v-for="g in linkable_goals" :key="g.id" :value="g.id">
                                {{ g.title }} — {{ g.type }}
                            </option>
                        </select>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="goalOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="!goalToLink">Link</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
