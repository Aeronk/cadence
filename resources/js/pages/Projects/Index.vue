<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, ExternalLink, FolderOpen, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import DataToolbar from '@/components/DataToolbar.vue';
import RichEditor from '@/components/RichEditor.vue';
import StatsBanner from '@/components/StatsBanner.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useViewMode } from '@/composables/useViewMode';
import projectsRoutes from '@/routes/projects';

type Project = {
    id: number;
    title: string;
    description: string | null;
    status: { id: number; name: string; color: string } | null;
    priority: { id: number; name: string; color: string } | null;
    status_id: number | null;
    priority_id: number | null;
    tags: { id: number; name: string; color: string }[];
    creator: { id: number; name: string };
    created_at: string;
    start_date?: string | null;
    due_date?: string | null;
    budget?: string | null;
    budget_currency?: string | null;
    state?: 'active' | 'on_hold' | 'completed';
    archived_at?: string | null;
};

type Option = { id: number; name: string; color: string };

const props = defineProps<{ projects: Project[]; statuses: Option[]; priorities: Option[] }>();

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

const dotColor = (color: string | undefined) => {
    if (!color) return 'bg-zinc-400';
    return ({
        blue: 'bg-blue-500',
        green: 'bg-emerald-500',
        orange: 'bg-orange-500',
        red: 'bg-red-500',
        gray: 'bg-zinc-400',
        slate: 'bg-slate-400',
        purple: 'bg-violet-500',
    } as Record<string, string>)[color] ?? 'bg-zinc-400';
};

const search = ref('');
const statusFilter = ref<string>('all');
const archivedFilter = ref<'all' | 'active' | 'archived'>('active');
const view = useViewMode('projects', 'cards');

const summaryStats = computed(() => {
    const total = props.projects.length;
    const active = props.projects.filter((p) => !p.archived_at && p.state !== 'completed').length;
    const completed = props.projects.filter((p) => p.state === 'completed').length;
    return [
        { label: 'Total projects', value: total, color: 'blue' as const },
        { label: 'Active', value: active, color: 'emerald' as const },
        { label: 'Completed', value: completed, color: 'violet' as const },
    ];
});

const statusOptions = computed(() => {
    const set = new Map<string, { name: string; color: string }>();
    props.projects.forEach((p) => {
        if (p.status) set.set(p.status.name, p.status);
    });
    return Array.from(set.values());
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.projects.filter((p) => {
        if (archivedFilter.value === 'active' && p.archived_at) return false;
        if (archivedFilter.value === 'archived' && !p.archived_at) return false;
        if (statusFilter.value !== 'all' && p.status?.name !== statusFilter.value) return false;
        if (!q) return true;
        const hay = [
            p.title,
            p.description ?? '',
            p.creator.name,
            ...p.tags.map((t) => t.name),
        ]
            .join(' ')
            .toLowerCase();
        return hay.includes(q);
    });
});

const dialogOpen = ref(false);
const form = useForm({
    title: '',
    description: '',
    status_id: '' as number | '',
    priority_id: '' as number | '',
    budget: '' as string,
    budget_currency: 'USD',
    start_date: '',
    due_date: '',
});

// Inline edit
const editOpen = ref(false);
const editing = ref<Project | null>(null);
const editForm = useForm({
    title: '',
    status_id: '' as number | '',
    priority_id: '' as number | '',
    budget: '' as string,
    budget_currency: 'USD',
    start_date: '',
    due_date: '',
});

function openEdit(project: Project) {
    editing.value = project;
    editForm.title = project.title;
    editForm.status_id = project.status_id ?? '';
    editForm.priority_id = project.priority_id ?? '';
    editForm.budget = project.budget ?? '';
    editForm.budget_currency = project.budget_currency ?? 'USD';
    editForm.start_date = project.start_date ?? '';
    editForm.due_date = project.due_date ?? '';
    editOpen.value = true;
}

function saveEdit() {
    if (!editing.value) return;
    editForm.patch(projectsRoutes.update(editing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

function toggleArchive(project: Project) {
    const url = `/projects/${project.id}/archive`;
    if (project.archived_at) {
        router.delete(url, { preserveScroll: true });
    } else {
        if (!confirm(`Archive "${project.title}"?`)) return;
        router.post(url, {}, { preserveScroll: true });
    }
}

function remove(project: Project) {
    if (!confirm(`Permanently delete "${project.title}"? This cannot be undone.`)) return;
    router.delete(projectsRoutes.destroy(project.id).url, { preserveScroll: true });
}

function submit() {
    form.post(projectsRoutes.store().url, {
        onSuccess: () => {
            form.reset();
            dialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Projects" />

    <AppLayout :breadcrumbs="[{ title: 'Projects', href: projectsRoutes.index().url }]">
        <div class="flex h-full flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Projects</h1>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" /> New project
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Create project</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <Label for="title">Title</Label>
                                <Input id="title" v-model="form.title" required />
                                <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                            </div>
                            <div>
                                <Label>Description</Label>
                                <RichEditor v-model="form.description" placeholder="What is this project about?" />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="create-status">Status</Label>
                                    <select
                                        id="create-status"
                                        v-model="form.status_id"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">—</option>
                                        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="create-priority">Priority</Label>
                                    <select
                                        id="create-priority"
                                        v-model="form.priority_id"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">—</option>
                                        <option v-for="p in priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="create-start">Start</Label>
                                    <Input id="create-start" v-model="form.start_date" type="date" />
                                </div>
                                <div>
                                    <Label for="create-due">Due</Label>
                                    <Input id="create-due" v-model="form.due_date" type="date" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2">
                                    <Label for="create-budget">Budget</Label>
                                    <Input id="create-budget" v-model="form.budget" type="number" step="0.01" min="0" placeholder="0.00" />
                                </div>
                                <div>
                                    <Label for="create-currency">Currency</Label>
                                    <Input id="create-currency" v-model="form.budget_currency" maxlength="3" />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <Dialog v-model:open="editOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit project</DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="saveEdit">
                        <div>
                            <Label for="row-title">Title</Label>
                            <Input id="row-title" v-model="editForm.title" required />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="row-status">Status</Label>
                                <select
                                    id="row-status"
                                    v-model="editForm.status_id"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">—</option>
                                    <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                            </div>
                            <div>
                                <Label for="row-priority">Priority</Label>
                                <select
                                    id="row-priority"
                                    v-model="editForm.priority_id"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">—</option>
                                    <option v-for="p in priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="row-start">Start</Label>
                                <Input id="row-start" v-model="editForm.start_date" type="date" />
                            </div>
                            <div>
                                <Label for="row-due">Due</Label>
                                <Input id="row-due" v-model="editForm.due_date" type="date" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <Label for="row-budget">Budget</Label>
                                <Input id="row-budget" v-model="editForm.budget" type="number" step="0.01" min="0" placeholder="0.00" />
                            </div>
                            <div>
                                <Label for="row-currency">Currency</Label>
                                <Input id="row-currency" v-model="editForm.budget_currency" maxlength="3" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <StatsBanner :stats="summaryStats" />

            <DataToolbar
                v-model="search"
                v-model:view-mode="view"
                placeholder="Search projects, tags, owners…"
                :count="filtered.length"
                :total="projects.length"
            >
                <template #filters>
                    <select
                        v-model="archivedFilter"
                        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    >
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                        <option value="all">All</option>
                    </select>
                    <select
                        v-if="statusOptions.length"
                        v-model="statusFilter"
                        class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    >
                        <option value="all">All statuses</option>
                        <option v-for="s in statusOptions" :key="s.name" :value="s.name">{{ s.name }}</option>
                    </select>
                </template>
            </DataToolbar>

            <div v-if="filtered.length === 0" class="rounded-lg border border-dashed p-12 text-center">
                <FolderOpen class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    {{ projects.length === 0 ? 'No projects yet. Create your first one.' : 'No projects match your filters.' }}
                </p>
            </div>

            <!-- Card view -->
            <div v-else-if="view === 'cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="project in filtered"
                    :key="project.id"
                    :href="projectsRoutes.show(project.id).url"
                    class="group flex flex-col rounded-xl border bg-card p-5 transition hover:border-primary hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-semibold leading-tight group-hover:text-primary">
                                {{ project.title }}
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Created by {{ project.creator.name }}
                            </p>
                        </div>
                        <span
                            v-if="project.archived_at"
                            class="shrink-0 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                        >
                            Archived
                        </span>
                    </div>

                    <div
                        v-if="project.description"
                        class="prose prose-sm line-clamp-2 mt-3 max-w-none text-sm text-muted-foreground dark:prose-invert"
                        v-html="project.description"
                    ></div>

                    <div class="mt-auto pt-4">
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
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
                                class="rounded-full bg-green-100 px-2 py-0.5 text-green-700 dark:bg-green-900/40 dark:text-green-300"
                            >
                                {{ project.budget_currency ?? '' }} {{ Number(project.budget).toLocaleString() }}
                            </span>
                            <span
                                v-for="tag in project.tags.slice(0, 3)"
                                :key="tag.id"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-primary"
                            >
                                #{{ tag.name }}
                            </span>
                            <span
                                v-if="project.tags.length > 3"
                                class="rounded-full bg-muted px-2 py-0.5"
                            >
                                +{{ project.tags.length - 3 }}
                            </span>
                        </div>
                        <p v-if="project.due_date" class="mt-2 text-xs text-muted-foreground">
                            Due {{ new Date(project.due_date).toLocaleDateString() }}
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Table view -->
            <div v-else class="overflow-hidden rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2">Title</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Priority</th>
                            <th class="px-4 py-2">Owner</th>
                            <th class="px-4 py-2">Tags</th>
                            <th class="px-4 py-2">Due</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="project in filtered"
                            :key="project.id"
                            class="border-b last:border-b-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="projectsRoutes.show(project.id).url"
                                    class="font-medium hover:text-primary"
                                >
                                    {{ project.title }}
                                </Link>
                                <span
                                    v-if="project.archived_at"
                                    class="ml-2 rounded-full bg-orange-100 px-1.5 py-0.5 text-[10px] uppercase text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                                >
                                    archived
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <span
                                    v-if="project.status"
                                    class="rounded-full px-2 py-0.5"
                                    :class="colorPill(project.status.color)"
                                >
                                    {{ project.status.name }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <span
                                    v-if="project.priority"
                                    class="rounded-full px-2 py-0.5"
                                    :class="colorPill(project.priority.color)"
                                >
                                    {{ project.priority.name }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ project.creator.name }}</td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="t in project.tags.slice(0, 4)"
                                        :key="t.id"
                                        class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] text-primary"
                                    >#{{ t.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">
                                {{ project.due_date ? new Date(project.due_date).toLocaleDateString() : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="projectsRoutes.show(project.id).url"
                                        title="Open"
                                        class="text-muted-foreground hover:text-foreground"
                                    >
                                        <ExternalLink class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        title="Edit"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="openEdit(project)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        :title="project.archived_at ? 'Unarchive' : 'Archive'"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="toggleArchive(project)"
                                    >
                                        <component :is="project.archived_at ? ArchiveRestore : Archive" class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Delete"
                                        class="text-muted-foreground hover:text-red-500"
                                        @click="remove(project)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
