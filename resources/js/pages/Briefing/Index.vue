<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Sparkles, RotateCw, CalendarClock, AlertTriangle, Briefcase, Plane } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

type Briefing = {
    id: number;
    summary: string;
    briefing_date: string;
    payload: {
        tasks_due_today: { id: number; title: string }[];
        overdue_tasks: { id: number; title: string }[];
        meetings_today: { id: number; title: string; starts_at: string; location: string | null }[];
        travel_today: { destination: string }[];
    } | null;
};

defineProps<{
    today: string;
    briefing: Briefing | null;
    recent: { id: number; briefing_date: string; summary: string }[];
}>();

function regenerate() {
    router.post('/briefing/regenerate', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Daily Briefing" />

    <AppLayout :breadcrumbs="[{ title: 'Briefing', href: '/briefing' }]">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-6">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        <Sparkles class="h-6 w-6 text-violet-500" />
                        Daily briefing
                    </h1>
                    <p class="text-sm text-muted-foreground">{{ today }}</p>
                </div>
                <Button variant="outline" size="sm" @click="regenerate">
                    <RotateCw class="mr-2 h-4 w-4" /> Regenerate
                </Button>
            </header>

            <article v-if="briefing" class="rounded-xl border bg-gradient-to-br from-violet-500/10 to-transparent p-5">
                <p class="whitespace-pre-line text-sm leading-relaxed">{{ briefing.summary }}</p>
            </article>
            <div v-else class="rounded-xl border border-dashed p-10 text-center">
                <Sparkles class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No briefing yet for today. Click regenerate to compose one.</p>
            </div>

            <section v-if="briefing?.payload" class="grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-2 flex items-center gap-2 text-sm font-semibold">
                        <Briefcase class="h-4 w-4 text-blue-500" /> Due today
                    </header>
                    <ul class="space-y-1 text-sm">
                        <li v-for="t in briefing.payload.tasks_due_today" :key="t.id" class="truncate">· {{ t.title }}</li>
                        <li v-if="!briefing.payload.tasks_due_today.length" class="text-xs text-muted-foreground">Nothing due.</li>
                    </ul>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-2 flex items-center gap-2 text-sm font-semibold">
                        <AlertTriangle class="h-4 w-4 text-red-500" /> Overdue
                    </header>
                    <ul class="space-y-1 text-sm">
                        <li v-for="t in briefing.payload.overdue_tasks" :key="t.id" class="truncate">· {{ t.title }}</li>
                        <li v-if="!briefing.payload.overdue_tasks.length" class="text-xs text-muted-foreground">All clear.</li>
                    </ul>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-2 flex items-center gap-2 text-sm font-semibold">
                        <CalendarClock class="h-4 w-4 text-violet-500" /> Meetings
                    </header>
                    <ul class="space-y-1 text-sm">
                        <li v-for="m in briefing.payload.meetings_today" :key="m.id" class="truncate">
                            · {{ m.starts_at }} — {{ m.title }}
                        </li>
                        <li v-if="!briefing.payload.meetings_today.length" class="text-xs text-muted-foreground">Open calendar.</li>
                    </ul>
                </div>
                <div class="rounded-xl border bg-card p-4">
                    <header class="mb-2 flex items-center gap-2 text-sm font-semibold">
                        <Plane class="h-4 w-4 text-orange-500" /> Travel
                    </header>
                    <ul class="space-y-1 text-sm">
                        <li v-for="(t, idx) in briefing.payload.travel_today" :key="idx" class="truncate">· {{ t.destination }}</li>
                        <li v-if="!briefing.payload.travel_today.length" class="text-xs text-muted-foreground">No travel today.</li>
                    </ul>
                </div>
            </section>

            <section v-if="recent.length > 1" class="rounded-xl border bg-card">
                <header class="border-b px-4 py-2 text-sm font-semibold">Recent briefings</header>
                <div
                    v-for="r in recent.slice(1)"
                    :key="r.id"
                    class="border-b px-4 py-3 last:border-b-0"
                >
                    <p class="text-xs text-muted-foreground">{{ r.briefing_date }}</p>
                    <p class="line-clamp-2 text-sm">{{ r.summary }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
