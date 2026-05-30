<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Calendar,
    CheckCircle2,
    CheckSquare,
    FileText,
    Flame,
    ListTodo,
    Plus,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import projectsRoutes from '@/routes/projects';
import tasksRoutes from '@/routes/tasks';
import todosRoutes from '@/routes/todos';
import meetingsRoutes from '@/routes/meetings';

type Stats = {
    projects_count: number;
    my_open_tasks_count: number;
    open_todos_count: number;
    upcoming_meetings_count: number;
};

type Task = { id: number; title: string; due_date: string | null };
type Meeting = { id: number; title: string; starts_at: string };
type Activity = { id: number; description: string; created_at: string; actor: { name: string } | null };

const props = defineProps<{
    stats: Stats | null;
    my_tasks?: Task[];
    upcoming_meetings?: Meeting[];
    recent_activity?: Activity[];
}>();

const page = usePage<{
    auth: {
        user: { id: number; name: string };
        currentWorkspace: { name: string } | null;
    };
}>();

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
});

const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Hero banner -->
        <section
            class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/15 via-primary/5 to-transparent p-6 md:p-8"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-primary/10 blur-3xl"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-12 left-1/3 h-40 w-40 rounded-full bg-emerald-400/10 blur-3xl"
            />
            <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-wider text-muted-foreground">
                        {{ page.props.auth.currentWorkspace?.name ?? 'Workspace' }}
                    </p>
                    <h1 class="mt-1 text-2xl font-bold md:text-3xl">
                        {{ greeting }}, {{ firstName }}.
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Here's what's in motion today.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="projectsRoutes.index().url"
                        class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground shadow-sm hover:opacity-90"
                    >
                        <Plus class="h-4 w-4" /> Project
                    </Link>
                    <Link
                        :href="tasksRoutes.index().url"
                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <Plus class="h-4 w-4" /> Task
                    </Link>
                    <Link
                        :href="meetingsRoutes.index().url"
                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <Plus class="h-4 w-4" /> Meeting
                    </Link>
                </div>
            </div>
        </section>

        <!-- Stat cards -->
        <section v-if="stats" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                :href="projectsRoutes.index().url"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div
                    class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-blue-500/10 transition group-hover:scale-125"
                />
                <div class="relative flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400">
                        <FileText class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Projects</p>
                        <p class="text-2xl font-bold leading-none">{{ stats.projects_count }}</p>
                    </div>
                </div>
            </Link>

            <Link
                :href="tasksRoutes.index().url"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div
                    class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-orange-500/10 transition group-hover:scale-125"
                />
                <div class="relative flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400">
                        <CheckSquare class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">My open tasks</p>
                        <p class="text-2xl font-bold leading-none">{{ stats.my_open_tasks_count }}</p>
                    </div>
                </div>
            </Link>

            <Link
                :href="todosRoutes.index().url"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div
                    class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-emerald-500/10 transition group-hover:scale-125"
                />
                <div class="relative flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                        <ListTodo class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Todos</p>
                        <p class="text-2xl font-bold leading-none">{{ stats.open_todos_count }}</p>
                    </div>
                </div>
            </Link>

            <Link
                :href="meetingsRoutes.index().url"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div
                    class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-violet-500/10 transition group-hover:scale-125"
                />
                <div class="relative flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-lg bg-violet-500/15 text-violet-600 dark:text-violet-400">
                        <Calendar class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Upcoming meetings</p>
                        <p class="text-2xl font-bold leading-none">{{ stats.upcoming_meetings_count }}</p>
                    </div>
                </div>
            </Link>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- My tasks -->
            <section class="rounded-xl border bg-card">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Flame class="h-4 w-4 text-orange-500" />
                        <h2 class="font-semibold">My tasks</h2>
                    </div>
                    <Link
                        :href="tasksRoutes.index().url"
                        class="text-xs text-muted-foreground hover:text-foreground"
                    >
                        View all →
                    </Link>
                </header>
                <div v-if="!my_tasks?.length" class="px-4 py-10 text-center">
                    <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-emerald-500" />
                    <p class="text-sm text-muted-foreground">All caught up. Nothing on your plate.</p>
                </div>
                <Link
                    v-for="task in my_tasks"
                    :key="task.id"
                    :href="tasksRoutes.show(task.id).url"
                    class="flex items-center justify-between border-b px-4 py-3 text-sm transition last:border-b-0 hover:bg-muted/50"
                >
                    <span class="truncate">{{ task.title }}</span>
                    <span v-if="task.due_date" class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs">
                        {{ task.due_date }}
                    </span>
                </Link>
            </section>

            <!-- Upcoming meetings -->
            <section class="rounded-xl border bg-card">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Calendar class="h-4 w-4 text-violet-500" />
                        <h2 class="font-semibold">Upcoming meetings</h2>
                    </div>
                    <Link
                        :href="meetingsRoutes.index().url"
                        class="text-xs text-muted-foreground hover:text-foreground"
                    >
                        View all →
                    </Link>
                </header>
                <div v-if="!upcoming_meetings?.length" class="px-4 py-10 text-center">
                    <Calendar class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">No meetings scheduled.</p>
                </div>
                <Link
                    v-for="meeting in upcoming_meetings"
                    :key="meeting.id"
                    :href="meetingsRoutes.show(meeting.id).url"
                    class="flex items-center justify-between gap-3 border-b px-4 py-3 text-sm transition last:border-b-0 hover:bg-muted/50"
                >
                    <span class="truncate">{{ meeting.title }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ new Date(meeting.starts_at).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                </Link>
            </section>
        </div>

        <!-- Activity -->
        <section class="rounded-xl border bg-card">
            <header class="flex items-center gap-2 border-b px-4 py-3">
                <Activity class="h-4 w-4 text-muted-foreground" />
                <h2 class="font-semibold">Recent activity</h2>
            </header>
            <div v-if="!recent_activity?.length" class="px-4 py-10 text-center text-sm text-muted-foreground">
                No activity yet.
            </div>
            <div
                v-for="entry in recent_activity"
                :key="entry.id"
                class="border-b px-4 py-3 last:border-b-0"
            >
                <p class="text-sm">{{ entry.description }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ new Date(entry.created_at).toLocaleString() }}
                </p>
            </div>
        </section>
    </div>
</template>
