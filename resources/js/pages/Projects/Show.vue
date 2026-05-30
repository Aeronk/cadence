<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Briefcase, LayoutGrid, MessageSquare, Info } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentThread from '@/components/CommentThread.vue';
import KanbanBoard from '@/components/KanbanBoard.vue';
import projectsRoutes from '@/routes/projects';
import tasksRoutes from '@/routes/tasks';

type Project = {
    id: number;
    title: string;
    description: string | null;
    status: { name: string; color: string } | null;
    priority: { name: string; color: string } | null;
    creator: { name: string };
    members: { id: number; name: string }[];
    tags: { id: number; name: string; color: string }[];
    clients: { id: number; name: string; company: string | null }[];
    start_date: string | null;
    due_date: string | null;
};
type Comment = {
    id: number;
    body: string;
    user: { id: number; name: string };
    created_at: string;
};
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

const props = defineProps<{
    project: Project;
    comments: Comment[];
    tasks: Task[];
    statuses: Status[];
}>();

const page = usePage<{ auth: { user: { id: number } } }>();

const tab = ref<'overview' | 'board' | 'comments'>('overview');

const tabs = [
    { id: 'overview' as const, label: 'Overview', icon: Info },
    { id: 'board' as const, label: 'Board', icon: LayoutGrid },
    { id: 'comments' as const, label: `Comments (${props.comments.length})`, icon: MessageSquare },
];
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
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">{{ project.title }}</h1>
                        <div
                            v-if="project.description"
                            class="prose prose-sm mt-1 max-w-none text-muted-foreground dark:prose-invert"
                            v-html="project.description"
                        ></div>
                    </div>
                    <Link
                        :href="tasksRoutes.index({ query: { project_id: project.id } }).url"
                        class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                    >
                        All tasks
                    </Link>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span v-if="project.status" class="rounded-full bg-muted px-2 py-0.5">
                        {{ project.status.name }}
                    </span>
                    <span v-if="project.priority" class="rounded-full bg-muted px-2 py-0.5">
                        Priority: {{ project.priority.name }}
                    </span>
                    <span
                        v-for="tag in project.tags"
                        :key="tag.id"
                        class="rounded-full bg-muted px-2 py-0.5"
                    >
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

            <nav class="-mb-px flex gap-6 border-b">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="flex items-center gap-2 border-b-2 pb-2 text-sm transition"
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

            <section v-if="tab === 'overview'" class="grid gap-6 md:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Owner</p>
                    <p class="mt-1 font-medium">{{ project.creator.name }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Start date</p>
                    <p class="mt-1 font-medium">{{ project.start_date ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Due date</p>
                    <p class="mt-1 font-medium">{{ project.due_date ?? '—' }}</p>
                </div>

                <div class="rounded-lg border p-4 md:col-span-3">
                    <h2 class="mb-3 font-semibold">Team ({{ project.members.length }})</h2>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="m in project.members"
                            :key="m.id"
                            class="rounded-full bg-muted px-3 py-1 text-sm"
                        >
                            {{ m.name }}
                        </span>
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

            <section v-else-if="tab === 'board'">
                <KanbanBoard :statuses="statuses" :tasks="tasks" />
            </section>

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
