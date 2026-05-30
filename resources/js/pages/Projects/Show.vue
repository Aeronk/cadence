<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Archive,
    ArchiveRestore,
    Briefcase,
    Calendar,
    FileText,
    Info,
    LayoutGrid,
    MessageSquare,
    Milestone as MilestoneIcon,
    Paperclip,
    Pencil,
    Plus,
    Trash2,
    UserPlus,
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
    status: { name: string; color: string } | null;
    priority: { name: string; color: string } | null;
    creator: { name: string };
    members: { id: number; name: string; email: string }[];
    tags: { id: number; name: string; color: string }[];
    clients: { id: number; name: string; company: string | null }[];
    start_date: string | null;
    due_date: string | null;
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
    progress: number;
    completed_at: string | null;
    position: number;
    creator: { id: number; name: string } | null;
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

const props = defineProps<{
    project: Project;
    comments: Comment[];
    tasks: Task[];
    statuses: Status[];
    milestones: Milestone[];
    files: ProjectFile[];
    workspace_members: Member[];
}>();

const page = usePage<{ auth: { user: { id: number } } }>();

const tab = ref<'overview' | 'board' | 'milestones' | 'files' | 'comments'>('overview');
const tabs = computed(() => [
    { id: 'overview' as const, label: 'Overview', icon: Info },
    { id: 'board' as const, label: 'Board', icon: LayoutGrid },
    { id: 'milestones' as const, label: `Milestones (${props.milestones.length})`, icon: MilestoneIcon },
    { id: 'files' as const, label: `Files (${props.files.length})`, icon: Paperclip },
    { id: 'comments' as const, label: `Comments (${props.comments.length})`, icon: MessageSquare },
]);

const isArchived = computed(() => props.project.archived_at !== null);

// Edit dialog
const editOpen = ref(false);
const editForm = useForm({
    title: props.project.title,
    description: props.project.description ?? '',
    start_date: props.project.start_date ?? '',
    due_date: props.project.due_date ?? '',
});

function saveEdit() {
    editForm.patch(projectsRoutes.update(props.project.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
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
    progress: 0,
});

function addMilestone() {
    milestoneForm.post(milestonesRoutes.store().url, {
        preserveScroll: true,
        onSuccess: () => {
            milestoneForm.reset('title', 'description', 'due_date');
            milestoneForm.progress = 0;
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

function updateMilestoneProgress(m: Milestone, value: number) {
    router.patch(
        milestonesRoutes.update(m.id).url,
        { progress: value },
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

                    <div class="flex shrink-0 items-center gap-2">
                        <Link
                            :href="tasksRoutes.index({ query: { project_id: project.id } }).url"
                            class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                        >
                            All tasks
                        </Link>
                        <Button variant="outline" size="sm" @click="editOpen = true">
                            <Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit
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
                    <span v-if="project.status" class="rounded-full bg-muted px-2 py-0.5">
                        {{ project.status.name }}
                    </span>
                    <span v-if="project.priority" class="rounded-full bg-muted px-2 py-0.5">
                        Priority: {{ project.priority.name }}
                    </span>
                    <span v-for="tag in project.tags" :key="tag.id" class="rounded-full bg-muted px-2 py-0.5">
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
                                <Label for="edit-start">Start date</Label>
                                <Input id="edit-start" v-model="editForm.start_date" type="date" />
                            </div>
                            <div>
                                <Label for="edit-due">Due date</Label>
                                <Input id="edit-due" v-model="editForm.due_date" type="date" />
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

                    <div v-if="memberCandidates.length" class="mt-4 flex items-center gap-2">
                        <select
                            v-model="memberSelect"
                            class="flex-1 rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                        >
                            <option value="" disabled>Add workspace member…</option>
                            <option v-for="m in memberCandidates" :key="m.id" :value="m.id">
                                {{ m.name }} ({{ m.email }})
                            </option>
                        </select>
                        <Button size="sm" :disabled="!memberSelect" @click="addMember">
                            <UserPlus class="mr-1.5 h-3.5 w-3.5" /> Add
                        </Button>
                    </div>
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
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="m-due">Due date</Label>
                                    <Input id="m-due" v-model="milestoneForm.due_date" type="date" />
                                </div>
                                <div>
                                    <Label for="m-progress">Progress ({{ milestoneForm.progress }}%)</Label>
                                    <input
                                        id="m-progress"
                                        v-model.number="milestoneForm.progress"
                                        type="range"
                                        min="0"
                                        max="100"
                                        class="mt-2 w-full"
                                    />
                                </div>
                            </div>
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
                    </div>
                </div>
            </section>

            <!-- Files -->
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
    </AppLayout>
</template>
