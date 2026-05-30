<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    CheckCircle2,
    Clock,
    LineChart,
    Plane,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type Range = { starts_at: string; ends_at: string; days: number };
type TaskStatus = { open: number; completed: number; overdue: number };
type CategoryRow = { category: string; count: number };
type MeetingHours = { day: string; hours: number };
type WorkloadRow = { user_id: number; name: string; open: number; overdue: number };
type BalanceRow = { category: string; count: number; pct: number };

const props = defineProps<{
    range: Range;
    task_status: TaskStatus;
    tasks_by_category: CategoryRow[];
    meeting_hours_by_day: MeetingHours[];
    workload: WorkloadRow[];
    life_balance: BalanceRow[];
    productivity: {
        tasks_completed_30d: number;
        todos_completed_30d: number;
        travel_days_30d: number;
    };
}>();

// Donut math for status pie
const statusDonut = computed(() => {
    const total = props.task_status.open + props.task_status.completed;
    const completedPct = total > 0 ? Math.round((props.task_status.completed / total) * 100) : 0;
    const r = 36, c = 2 * Math.PI * r;
    return { completedPct, total, r, c, offset: c - (completedPct / 100) * c };
});

// Meeting hours bar chart scale
const meetingMax = computed(
    () => Math.max(1, ...props.meeting_hours_by_day.map((d) => d.hours)),
);

// Radar polygon for life balance (8 spokes)
const balancePolygon = computed(() => {
    if (props.life_balance.length === 0) return { points: '', max: 0 };
    const n = props.life_balance.length;
    const cx = 120, cy = 120, R = 100;
    const points = props.life_balance.map((bucket, i) => {
        const angle = (Math.PI * 2 * i) / n - Math.PI / 2;
        const r = (bucket.pct / 100) * R;
        const x = cx + r * Math.cos(angle);
        const y = cy + r * Math.sin(angle);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    return { points: points.join(' '), R, cx, cy };
});

const balanceLabels = computed(() => {
    const n = props.life_balance.length;
    const cx = 120, cy = 120, R = 115;
    return props.life_balance.map((b, i) => {
        const angle = (Math.PI * 2 * i) / n - Math.PI / 2;
        return {
            x: cx + R * Math.cos(angle),
            y: cy + R * Math.sin(angle),
            label: b.category,
            count: b.count,
        };
    });
});

const categoryPill = (c: string) => ({
    work: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    personal: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    family: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300',
    church: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    social: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    travel: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    health: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    finance: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
} as Record<string, string>)[c] ?? 'bg-muted';
</script>

<template>
    <Head title="Analytics" />

    <AppLayout :breadcrumbs="[{ title: 'Analytics', href: '/analytics' }]">
        <div class="flex flex-col gap-6 p-6">
            <header>
                <h1 class="text-2xl font-bold">Analytics</h1>
                <p class="text-sm text-muted-foreground">
                    Last 30 days · {{ range.starts_at }} → {{ range.ends_at }}
                </p>
            </header>

            <!-- Quick stats -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                            <CheckCircle2 class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Tasks completed</p>
                            <p class="text-2xl font-bold">{{ productivity.tasks_completed_30d }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400">
                            <CheckCircle2 class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Todos completed</p>
                            <p class="text-2xl font-bold">{{ productivity.todos_completed_30d }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400">
                            <Plane class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Travel days</p>
                            <p class="text-2xl font-bold">{{ productivity.travel_days_30d }}</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-lg bg-red-500/15 text-red-600 dark:text-red-400">
                            <Clock class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Overdue tasks</p>
                            <p class="text-2xl font-bold">{{ task_status.overdue }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Status donut + meetings + life balance -->
            <section class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-3 flex items-center gap-2">
                        <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                        <h2 class="font-semibold">Task completion (30d)</h2>
                    </header>
                    <div class="flex items-center gap-4">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" :r="statusDonut.r" class="fill-none stroke-muted" stroke-width="12" />
                            <circle
                                cx="60"
                                cy="60"
                                :r="statusDonut.r"
                                class="fill-none stroke-emerald-500"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="statusDonut.c"
                                :stroke-dashoffset="statusDonut.offset"
                                transform="rotate(-90 60 60)"
                            />
                            <text x="60" y="64" text-anchor="middle" class="fill-foreground text-base font-bold">
                                {{ statusDonut.completedPct }}%
                            </text>
                        </svg>
                        <div class="space-y-1 text-sm">
                            <p class="text-muted-foreground">Total</p>
                            <p class="text-xl font-bold">{{ statusDonut.total }}</p>
                            <p class="text-muted-foreground">Open / Done</p>
                            <p class="text-sm">
                                <span class="font-medium">{{ task_status.open }}</span> /
                                <span class="font-medium text-emerald-500">{{ task_status.completed }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-card p-4 lg:col-span-2">
                    <header class="mb-3 flex items-center gap-2">
                        <LineChart class="h-4 w-4 text-violet-500" />
                        <h2 class="font-semibold">Meeting hours by day</h2>
                    </header>
                    <div class="flex h-32 items-end gap-1">
                        <div
                            v-for="d in meeting_hours_by_day"
                            :key="d.day"
                            class="group relative flex-1"
                            :title="`${d.day}: ${d.hours}h`"
                        >
                            <div
                                class="w-full rounded-t bg-violet-500/70 transition-all hover:bg-violet-500"
                                :style="{ height: `${(d.hours / meetingMax) * 100}%`, minHeight: '2px' }"
                            />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Peak: {{ meetingMax.toFixed(1) }} hours
                    </p>
                </div>
            </section>

            <!-- Life balance radar + categories breakdown -->
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-3 flex items-center gap-2">
                        <BarChart3 class="h-4 w-4 text-pink-500" />
                        <h2 class="font-semibold">Life balance</h2>
                    </header>
                    <p class="mb-2 text-xs text-muted-foreground">
                        Activity volume across the 8 life categories.
                    </p>
                    <svg viewBox="0 0 240 240" class="mx-auto h-64 w-64">
                        <!-- Concentric grid -->
                        <circle cx="120" cy="120" :r="100" class="fill-none stroke-muted/40" />
                        <circle cx="120" cy="120" :r="66" class="fill-none stroke-muted/40" />
                        <circle cx="120" cy="120" :r="33" class="fill-none stroke-muted/40" />
                        <polygon
                            :points="balancePolygon.points"
                            class="fill-pink-500/30 stroke-pink-500"
                            stroke-width="2"
                        />
                        <text
                            v-for="lab in balanceLabels"
                            :key="lab.label"
                            :x="lab.x"
                            :y="lab.y"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[9px] capitalize"
                            dominant-baseline="middle"
                        >
                            {{ lab.label }} ({{ lab.count }})
                        </text>
                    </svg>
                </div>

                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-3 flex items-center gap-2">
                        <BarChart3 class="h-4 w-4 text-orange-500" />
                        <h2 class="font-semibold">Tasks by category</h2>
                    </header>
                    <div v-if="tasks_by_category.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        No tagged tasks yet.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="row in tasks_by_category"
                            :key="row.category"
                            class="flex items-center gap-3 text-sm"
                        >
                            <span class="w-24 rounded-full px-2 py-0.5 text-xs capitalize" :class="categoryPill(row.category)">
                                {{ row.category }}
                            </span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full bg-primary"
                                    :style="{
                                        width:
                                            (row.count / Math.max(...tasks_by_category.map((t) => t.count))) *
                                                100 +
                                            '%',
                                    }"
                                />
                            </div>
                            <span class="w-8 text-right text-xs font-medium tabular-nums">{{ row.count }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Workload (admin only) -->
            <section v-if="workload.length" class="rounded-xl border bg-card">
                <header class="flex items-center gap-2 border-b px-4 py-3">
                    <Users class="h-4 w-4 text-muted-foreground" />
                    <h2 class="font-semibold">Workload by assignee</h2>
                </header>
                <div
                    v-for="row in workload"
                    :key="row.user_id"
                    class="flex items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-7 w-7 place-items-center rounded-full bg-primary text-xs font-medium text-primary-foreground"
                        >
                            {{ row.name.charAt(0).toUpperCase() }}
                        </span>
                        <span class="text-sm font-medium">{{ row.name }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="rounded-full bg-muted px-2 py-0.5">
                            {{ row.open }} open
                        </span>
                        <span
                            v-if="row.overdue > 0"
                            class="rounded-full bg-red-100 px-2 py-0.5 text-red-700 dark:bg-red-900/40 dark:text-red-300"
                        >
                            {{ row.overdue }} overdue
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
