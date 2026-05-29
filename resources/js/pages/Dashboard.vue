<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckSquare, Calendar, FileText, ListTodo } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import todos from '@/routes/todos';
import meetings from '@/routes/meetings';

type Stats = {
    projects_count: number;
    my_open_tasks_count: number;
    open_todos_count: number;
    upcoming_meetings_count: number;
};

type Task = { id: number; title: string; due_date: string | null };
type Meeting = { id: number; title: string; starts_at: string };
type Activity = { id: number; description: string; created_at: string; actor: { name: string } | null };

defineProps<{
    stats: Stats | null;
    my_tasks?: Task[];
    upcoming_meetings?: Meeting[];
    recent_activity?: Activity[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        <div v-if="stats" class="grid gap-4 md:grid-cols-4">
            <Link :href="projects.index().url" class="rounded-xl border p-4 hover:border-primary">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Projects</span>
                    <FileText class="h-4 w-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-3xl font-bold">{{ stats.projects_count }}</p>
            </Link>
            <Link :href="tasks.index().url" class="rounded-xl border p-4 hover:border-primary">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">My open tasks</span>
                    <CheckSquare class="h-4 w-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-3xl font-bold">{{ stats.my_open_tasks_count }}</p>
            </Link>
            <Link :href="todos.index().url" class="rounded-xl border p-4 hover:border-primary">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Todos</span>
                    <ListTodo class="h-4 w-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-3xl font-bold">{{ stats.open_todos_count }}</p>
            </Link>
            <Link :href="meetings.index().url" class="rounded-xl border p-4 hover:border-primary">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Upcoming meetings</span>
                    <Calendar class="h-4 w-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-3xl font-bold">{{ stats.upcoming_meetings_count }}</p>
            </Link>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-xl border">
                <div class="border-b px-4 py-3 font-semibold">My tasks</div>
                <div v-if="!my_tasks?.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    Nothing on your plate.
                </div>
                <Link
                    v-for="task in my_tasks"
                    :key="task.id"
                    :href="tasks.show(task.id).url"
                    class="flex items-center justify-between border-b px-4 py-3 last:border-b-0 hover:bg-muted/50"
                >
                    <span class="truncate">{{ task.title }}</span>
                    <span v-if="task.due_date" class="text-xs text-muted-foreground">{{ task.due_date }}</span>
                </Link>
            </div>

            <div class="rounded-xl border">
                <div class="border-b px-4 py-3 font-semibold">Upcoming meetings</div>
                <div v-if="!upcoming_meetings?.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    No meetings scheduled.
                </div>
                <Link
                    v-for="meeting in upcoming_meetings"
                    :key="meeting.id"
                    :href="meetings.show(meeting.id).url"
                    class="flex items-center justify-between border-b px-4 py-3 last:border-b-0 hover:bg-muted/50"
                >
                    <span class="truncate">{{ meeting.title }}</span>
                    <span class="text-xs text-muted-foreground">{{ new Date(meeting.starts_at).toLocaleString() }}</span>
                </Link>
            </div>
        </div>

        <div class="rounded-xl border">
            <div class="border-b px-4 py-3 font-semibold">Recent activity</div>
            <div v-if="!recent_activity?.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                No activity yet.
            </div>
            <div
                v-for="entry in recent_activity"
                :key="entry.id"
                class="border-b px-4 py-3 text-sm last:border-b-0"
            >
                <p>{{ entry.description }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ new Date(entry.created_at).toLocaleString() }}</p>
            </div>
        </div>
    </div>
</template>
