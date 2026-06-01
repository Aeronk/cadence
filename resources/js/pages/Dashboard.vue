<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    Calendar,
    CalendarRange,
    CheckCircle2,
    CheckSquare,
    FileText,
    Flame,
    ListTodo,
    Plus,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

type Stats = {
    projects_count: number;
    my_open_tasks_count: number;
    open_todos_count: number;
    upcoming_meetings_count: number;
};

type Task = { id: number; title: string; due_date: string | null };
type Meeting = { id: number; title: string; starts_at: string };
type ActivityEntry = {
    id: number;
    description: string;
    created_at: string;
    actor: { name: string } | null;
};

type Charts = {
    this_week: { total: number; done: number };
    by_priority: { label: string; count: number; level: number }[];
    activity_14d: { day: string; label: string; count: number }[];
};

type Briefing = {
    trips: { id: number; destination: string; departs_at: string; days_away: number }[];
    events: { id: number; title: string; category: string | null; date: string; days_away: number }[];
    meetings: { id: number; title: string; host: string | null; starts_at: string; when: string }[];
    projects: { id: number; title: string; due_date: string }[];
};

type StatusOverview = {
    projects: { active: number; completed: number; overdue: number; total: number; overall_progress: number };
    task_stages: { name: string; color: string; count: number }[];
};

const props = defineProps<{
    stats: Stats | null;
    my_tasks?: Task[];
    upcoming_meetings?: Meeting[];
    recent_activity?: ActivityEntry[];
    charts?: Charts;
    briefing?: Briefing;
    status_overview?: StatusOverview;
}>();

const stageColorPill = (color: string) =>
    ({
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        purple: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300',
        gray: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    } as Record<string, string>)[color] ?? 'bg-muted text-muted-foreground';

const stageDot = (color: string) =>
    ({
        blue: 'bg-blue-500',
        green: 'bg-emerald-500',
        orange: 'bg-orange-500',
        amber: 'bg-amber-500',
        red: 'bg-red-500',
        purple: 'bg-violet-500',
        pink: 'bg-pink-500',
        gray: 'bg-zinc-400',
    } as Record<string, string>)[color] ?? 'bg-zinc-400';

const briefingLines = computed(() => {
    const lines: { icon: string; text: string }[] = [];
    const b = props.briefing;
    if (!b) return lines;
    const dayLabel = (n: number) => (n === 0 ? 'today' : n === 1 ? 'tomorrow' : `in ${n} days`);
    b.trips.forEach((t) => lines.push({ icon: '✈', text: `Trip to ${t.destination} ${dayLabel(t.days_away)}` }));
    b.events.forEach((e) => {
        const verb = e.category === 'birthday' ? "Birthday for" : e.category === 'anniversary' ? 'Anniversary —' : '';
        lines.push({ icon: '🎉', text: `${verb} ${e.title} ${dayLabel(e.days_away)}`.trim() });
    });
    b.meetings.forEach((m) =>
        lines.push({
            icon: '🗓',
            text: `Meeting "${m.title}"${m.host ? ` with ${m.host}` : ''} ${m.when}`,
        }),
    );
    b.projects.forEach((p) =>
        lines.push({ icon: '📁', text: `Project "${p.title}" due ${new Date(p.due_date).toLocaleDateString()}` }),
    );
    return lines;
});

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

// Donut math for "this week" progress
const donut = computed(() => {
    const total = props.charts?.this_week.total ?? 0;
    const done = props.charts?.this_week.done ?? 0;
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    const r = 36;
    const c = 2 * Math.PI * r;
    const offset = c - (pct / 100) * c;
    return { pct, total, done, r, c, offset };
});

// Priority bar chart scale
const priorityMax = computed(() => {
    const counts = (props.charts?.by_priority ?? []).map((p) => p.count);
    return counts.length ? Math.max(...counts) : 0;
});

const priorityColor = (level: number) =>
    level >= 4 ? 'fill-red-500' : level === 3 ? 'fill-orange-500' : level === 2 ? 'fill-blue-500' : 'fill-zinc-400';

// 14-day spark bar chart
const activityMax = computed(() => {
    const counts = (props.charts?.activity_14d ?? []).map((d) => d.count);
    return counts.length ? Math.max(...counts, 1) : 1;
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="[{ title: 'Dashboard', href: '/dashboard' }]">
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

                    <ul
                        v-if="briefingLines.length"
                        class="mt-4 space-y-1 text-sm"
                    >
                        <li
                            v-for="(line, i) in briefingLines.slice(0, 6)"
                            :key="i"
                            class="flex items-start gap-2"
                        >
                            <span class="leading-5">{{ line.icon }}</span>
                            <span>{{ line.text }}</span>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        href="/projects"
                        class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground shadow-sm hover:opacity-90"
                    >
                        <Plus class="h-4 w-4" /> Project
                    </Link>
                    <Link
                        href="/tasks"
                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <Plus class="h-4 w-4" /> Task
                    </Link>
                    <Link
                        href="/meetings"
                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <Plus class="h-4 w-4" /> Meeting
                    </Link>
                    <Link
                        href="/calendar"
                        class="inline-flex items-center gap-1.5 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <CalendarRange class="h-4 w-4" /> Calendar
                    </Link>
                </div>
            </div>
        </section>

        <!-- Stat cards -->
        <section v-if="stats" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                href="/projects"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-blue-500/10 transition group-hover:scale-125" />
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
                href="/tasks"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-orange-500/10 transition group-hover:scale-125" />
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
                href="/todos"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-emerald-500/10 transition group-hover:scale-125" />
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
                href="/meetings"
                class="group relative overflow-hidden rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-md"
            >
                <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-violet-500/10 transition group-hover:scale-125" />
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

        <!-- Charts -->
        <section v-if="charts" class="grid gap-4 md:grid-cols-3">
            <!-- This-week donut -->
            <div class="rounded-xl border bg-card p-4">
                <header class="mb-3 flex items-center gap-2">
                    <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                    <h2 class="font-semibold">This week</h2>
                </header>
                <div class="flex items-center gap-4">
                    <svg width="96" height="96" viewBox="0 0 96 96" class="shrink-0">
                        <circle
                            cx="48"
                            cy="48"
                            :r="donut.r"
                            class="fill-none stroke-muted"
                            stroke-width="10"
                        />
                        <circle
                            cx="48"
                            cy="48"
                            :r="donut.r"
                            class="fill-none stroke-emerald-500 transition-all"
                            stroke-width="10"
                            stroke-linecap="round"
                            :stroke-dasharray="donut.c"
                            :stroke-dashoffset="donut.offset"
                            transform="rotate(-90 48 48)"
                        />
                        <text
                            x="48"
                            y="52"
                            text-anchor="middle"
                            class="fill-foreground text-sm font-bold"
                        >
                            {{ donut.pct }}%
                        </text>
                    </svg>
                    <div class="text-sm">
                        <p class="text-muted-foreground">Tasks created</p>
                        <p class="text-lg font-bold">{{ donut.total }}</p>
                        <p class="mt-1 text-muted-foreground">Completed</p>
                        <p class="text-lg font-bold text-emerald-500">{{ donut.done }}</p>
                    </div>
                </div>
            </div>

            <!-- Tasks by priority -->
            <div class="rounded-xl border bg-card p-4 md:col-span-2">
                <header class="mb-3 flex items-center gap-2">
                    <BarChart3 class="h-4 w-4 text-orange-500" />
                    <h2 class="font-semibold">Open tasks by priority</h2>
                </header>
                <div v-if="!charts.by_priority.length" class="py-6 text-center text-sm text-muted-foreground">
                    No open tasks have a priority set yet.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="p in charts.by_priority"
                        :key="p.label"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="w-20 shrink-0 text-muted-foreground">{{ p.label }}</span>
                        <div class="h-6 flex-1 overflow-hidden rounded-md bg-muted">
                            <div
                                class="h-full rounded-md transition-all"
                                :class="priorityColor(p.level).replace('fill', 'bg')"
                                :style="{ width: priorityMax > 0 ? `${(p.count / priorityMax) * 100}%` : '0%' }"
                            />
                        </div>
                        <span class="w-8 shrink-0 text-right font-semibold tabular-nums">
                            {{ p.count }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 14-day activity -->
            <div class="rounded-xl border bg-card p-4 md:col-span-3">
                <header class="mb-3 flex items-center gap-2">
                    <TrendingUp class="h-4 w-4 text-violet-500" />
                    <h2 class="font-semibold">Activity — last 14 days</h2>
                </header>
                <div class="flex h-32 items-end gap-1">
                    <div
                        v-for="d in charts.activity_14d"
                        :key="d.day"
                        class="group relative flex-1"
                    >
                        <div
                            class="w-full rounded-t bg-violet-500/70 transition-all hover:bg-violet-500"
                            :style="{ height: `${(d.count / activityMax) * 100}%`, minHeight: '2px' }"
                        />
                        <div
                            class="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-0.5 text-[10px] text-background opacity-0 transition group-hover:opacity-100"
                        >
                            {{ d.count }} on {{ d.day }}
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-muted-foreground">
                    <span
                        v-for="(d, i) in charts.activity_14d"
                        :key="d.day"
                        :class="{ 'opacity-0': i % 2 !== 0 }"
                    >
                        {{ d.label }}
                    </span>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- My tasks -->
            <section class="rounded-xl border bg-card">
                <header class="flex items-center justify-between border-b px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Flame class="h-4 w-4 text-orange-500" />
                        <h2 class="font-semibold">My tasks</h2>
                    </div>
                    <Link href="/tasks" class="text-xs text-muted-foreground hover:text-foreground">
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
                    :href="`/tasks/${task.id}`"
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
                    <Link href="/calendar" class="text-xs text-muted-foreground hover:text-foreground">
                        Open calendar →
                    </Link>
                </header>
                <div v-if="!upcoming_meetings?.length" class="px-4 py-10 text-center">
                    <Calendar class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">No meetings scheduled.</p>
                </div>
                <Link
                    v-for="meeting in upcoming_meetings"
                    :key="meeting.id"
                    :href="`/meetings/${meeting.id}`"
                    class="flex items-center justify-between gap-3 border-b px-4 py-3 text-sm transition last:border-b-0 hover:bg-muted/50"
                >
                    <span class="truncate">{{ meeting.title }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ new Date(meeting.starts_at).toLocaleString([], { weekday: 'short', hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                </Link>
            </section>
        </div>

        <!-- Project Status Overview -->
        <section v-if="status_overview" class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border bg-card p-5 lg:col-span-2">
                <header class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <span class="inline-block h-2 w-2 rounded-full bg-primary" />
                        Project Status Overview
                    </h2>
                    <span class="rounded-full border px-2 py-0.5 text-xs tabular-nums">
                        {{ status_overview.projects.total }} total
                    </span>
                </header>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <p class="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Projects</p>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm dark:bg-emerald-950/30">
                                <span class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500" /> Active
                                </span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    {{ status_overview.projects.active }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-blue-50 px-3 py-2 text-sm dark:bg-blue-950/30">
                                <span class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-blue-500" /> Completed
                                </span>
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ status_overview.projects.completed }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-red-50 px-3 py-2 text-sm dark:bg-red-950/30">
                                <span class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-red-500" /> Overdue
                                </span>
                                <span class="rounded-full bg-red-500 px-2 py-0.5 text-xs font-medium text-white">
                                    {{ status_overview.projects.overdue }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Task stages</p>
                        <div v-if="!status_overview.task_stages.length" class="text-xs text-muted-foreground">
                            No statuses defined.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="s in status_overview.task_stages"
                                :key="s.name"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-muted/40"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" :class="stageDot(s.color)" />
                                    {{ s.name }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="stageColorPill(s.color)">
                                    {{ s.count }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 border-t pt-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Overall progress</span>
                        <span class="font-medium tabular-nums">{{ status_overview.projects.overall_progress }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-all"
                            :style="{ width: status_overview.projects.overall_progress + '%' }"
                        />
                    </div>
                </div>
            </div>

            <!-- Activity feed (with Live badge if WS connected) -->
            <section class="rounded-xl border bg-card">
                <header class="flex items-center justify-between gap-2 border-b px-4 py-3">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <Activity class="h-4 w-4 text-muted-foreground" />
                        Recent activity
                    </h2>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        </span>
                        Live
                    </span>
                </header>
                <div v-if="!recent_activity?.length" class="px-4 py-10 text-center text-sm text-muted-foreground">
                    No activity yet.
                </div>
                <div v-else class="max-h-96 overflow-y-auto">
                    <div
                        v-for="entry in recent_activity?.slice(0, 8)"
                        :key="entry.id"
                        class="border-b px-4 py-3 last:border-b-0 hover:bg-muted/30"
                    >
                        <p class="text-sm">{{ entry.description }}</p>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            {{ entry.actor?.name ?? 'System' }} · {{ new Date(entry.created_at).toLocaleString() }}
                        </p>
                    </div>
                </div>
            </section>
        </section>

        <!-- Legacy activity feed fallback (kept for the old layout below) -->
        <section v-if="!status_overview" class="rounded-xl border bg-card">
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
    </AppLayout>
</template>
