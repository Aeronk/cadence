<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentThread from '@/components/CommentThread.vue';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';

type Project = {
    id: number;
    title: string;
    description: string | null;
    status: { name: string } | null;
    priority: { name: string } | null;
    creator: { name: string };
    members: { id: number; name: string }[];
    tags: { id: number; name: string }[];
};
type Comment = { id: number; body: string; user: { id: number; name: string }; created_at: string };

const props = defineProps<{ project: Project; comments: Comment[] }>();
const page = usePage<{ auth: { user: { id: number } } }>();
</script>

<template>
    <Head :title="project.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Projects', href: projects.index().url },
            { title: project.title, href: projects.show(project.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">{{ project.title }}</h1>
                <p v-if="project.description" class="mt-2 text-muted-foreground">{{ project.description }}</p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs font-medium text-muted-foreground">Status</p>
                    <p class="mt-1 font-medium">{{ project.status?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs font-medium text-muted-foreground">Priority</p>
                    <p class="mt-1 font-medium">{{ project.priority?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs font-medium text-muted-foreground">Created by</p>
                    <p class="mt-1 font-medium">{{ project.creator.name }}</p>
                </div>
            </div>

            <div class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Members</h2>
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

            <Link
                :href="tasks.index({ query: { project_id: project.id } }).url"
                class="rounded-md border border-border px-4 py-2 text-center text-sm hover:bg-muted"
            >
                View tasks
            </Link>

            <CommentThread
                commentable-type="project"
                :commentable-id="project.id"
                :comments="comments"
                :current-user-id="page.props.auth.user.id"
            />
        </div>
    </AppLayout>
</template>
