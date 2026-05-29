<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentThread from '@/components/CommentThread.vue';
import tasks from '@/routes/tasks';

type Task = {
    id: number;
    title: string;
    description: string | null;
    status: { name: string } | null;
    priority: { name: string } | null;
    creator: { name: string };
    assignees: { id: number; name: string }[];
    subtasks: { id: number; title: string; completed_at: string | null }[];
};
type Comment = { id: number; body: string; user: { id: number; name: string }; created_at: string };

const props = defineProps<{ task: Task; comments: Comment[] }>();
const page = usePage<{ auth: { user: { id: number } } }>();
</script>

<template>
    <Head :title="task.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Tasks', href: tasks.index().url },
            { title: task.title, href: tasks.show(task.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">{{ task.title }}</h1>
                <p v-if="task.description" class="mt-2 text-muted-foreground">{{ task.description }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Status</p>
                    <p class="mt-1 font-medium">{{ task.status?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Priority</p>
                    <p class="mt-1 font-medium">{{ task.priority?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Created by</p>
                    <p class="mt-1 font-medium">{{ task.creator.name }}</p>
                </div>
            </div>

            <div v-if="task.subtasks.length" class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Subtasks</h2>
                <ul class="space-y-2">
                    <li v-for="s in task.subtasks" :key="s.id" class="flex items-center gap-2">
                        <input type="checkbox" :checked="s.completed_at !== null" />
                        <span :class="{ 'line-through text-muted-foreground': s.completed_at }">{{ s.title }}</span>
                    </li>
                </ul>
            </div>

            <CommentThread
                commentable-type="task"
                :commentable-id="task.id"
                :comments="comments"
                :current-user-id="page.props.auth.user.id"
            />
        </div>
    </AppLayout>
</template>
