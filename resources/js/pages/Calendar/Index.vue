<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

type CalendarEvent = {
    id: string;
    source: 'cadence' | 'external';
    title: string;
    starts_at: string;
    ends_at: string;
    url: string | null;
    meta: string | null;
    meeting_type: string | null;
};

type TravelDay = { date: string; trip_id: number; trip_name: string; destination: string | null };
type PersonalDay = { id: number; date: string; title: string; category: string | null };

const props = defineProps<{
    view: 'day' | 'week' | 'month';
    cursor_iso: string;
    cursor_label: string;
    prev_cursor: string;
    next_cursor: string;
    today_iso: string;
    events: CalendarEvent[];
    travel_days: TravelDay[];
    personal_events: PersonalDay[];
}>();

const personalByDay = computed(() => {
    const map: Record<string, PersonalDay[]> = {};
    for (const p of props.personal_events ?? []) {
        (map[p.date] ||= []).push(p);
    }
    return map;
});

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function go(date: string, view: 'day' | 'week' | 'month' = props.view) {
    router.get('/calendar', { view, date }, { preserveState: true, preserveScroll: true });
}

function setView(view: 'day' | 'week' | 'month') {
    go(props.cursor_iso.slice(0, 10), view);
}

function today() {
    const t = new Date().toISOString().slice(0, 10);
    go(t, props.view);
}

function fmtTime(iso: string) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ---- Month view (6×7 grid) ----
const monthDays = computed(() => {
    if (props.view !== 'month') return [];
    const cursor = new Date(props.cursor_iso);
    const first = new Date(cursor);
    first.setDate(1);
    const start = new Date(first);
    start.setDate(first.getDate() - first.getDay());
    const cells: { iso: string; date: Date; inMonth: boolean }[] = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        cells.push({
            iso: d.toISOString().slice(0, 10),
            date: d,
            inMonth: d.getMonth() === cursor.getMonth(),
        });
    }
    return cells;
});

// ---- Week view (7 day columns) ----
const weekDays = computed(() => {
    if (props.view !== 'week') return [];
    const cursor = new Date(props.cursor_iso);
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(cursor);
        d.setDate(cursor.getDate() + i);
        return { iso: d.toISOString().slice(0, 10), date: d };
    });
});

// ---- Day view (24 hour rows) ----
const hours = Array.from({ length: 24 }, (_, i) => i);

const eventsByDay = computed(() => {
    const map: Record<string, CalendarEvent[]> = {};
    for (const ev of props.events) {
        const key = ev.starts_at.slice(0, 10);
        (map[key] ||= []).push(ev);
    }
    return map;
});

const travelByDay = computed(() => {
    const map: Record<string, TravelDay[]> = {};
    for (const t of props.travel_days ?? []) {
        (map[t.date] ||= []).push(t);
    }
    return map;
});

// Place an event in the day/week grid (top + height as % of 24h).
function eventSlot(ev: CalendarEvent) {
    const start = new Date(ev.starts_at);
    const end = new Date(ev.ends_at);
    const dayStart = new Date(start);
    dayStart.setHours(0, 0, 0, 0);
    const startMin = (start.getTime() - dayStart.getTime()) / 60000;
    const durationMin = Math.max(30, (end.getTime() - start.getTime()) / 60000);
    return {
        top: `${(startMin / 1440) * 100}%`,
        height: `${(durationMin / 1440) * 100}%`,
    };
}

const eventClass = (ev: CalendarEvent) =>
    ev.source === 'cadence'
        ? 'bg-primary/10 border border-primary/30 text-primary hover:bg-primary/20'
        : 'bg-emerald-100 border border-emerald-300 text-emerald-800 dark:bg-emerald-900/40 dark:border-emerald-700 dark:text-emerald-300';
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="[{ title: 'Calendar', href: '/calendar' }]">
        <div class="flex h-full flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ cursor_label }}</h1>
                    <p class="text-xs text-muted-foreground">
                        Cadence meetings &amp; connected Google / Outlook events
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-md border bg-card p-0.5 text-xs">
                        <button
                            v-for="v in (['day', 'week', 'month'] as const)"
                            :key="v"
                            type="button"
                            class="rounded px-3 py-1 capitalize transition"
                            :class="view === v ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                            @click="setView(v)"
                        >
                            {{ v }}
                        </button>
                    </div>
                    <Button variant="outline" size="sm" @click="go(prev_cursor)">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="today">Today</Button>
                    <Button variant="outline" size="sm" @click="go(next_cursor)">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </header>

            <!-- Month grid -->
            <template v-if="view === 'month'">
                <div class="grid grid-cols-7 border-b text-xs font-medium text-muted-foreground">
                    <div v-for="w in WEEKDAYS" :key="w" class="px-2 py-2">{{ w }}</div>
                </div>
                <div class="grid flex-1 grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border">
                    <div
                        v-for="cell in monthDays"
                        :key="cell.iso"
                        class="flex min-h-[6rem] flex-col gap-1 bg-background p-1.5"
                        :class="{ 'opacity-50': !cell.inMonth }"
                    >
                        <button
                            type="button"
                            class="flex items-center justify-between text-[11px]"
                            @click="go(cell.iso, 'day')"
                        >
                            <span
                                :class="
                                    cell.iso === today_iso
                                        ? 'grid h-5 w-5 place-items-center rounded-full bg-primary text-primary-foreground font-semibold'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                            >
                                {{ cell.date.getDate() }}
                            </span>
                        </button>
                        <Link
                            v-for="t in travelByDay[cell.iso] || []"
                            :key="`tr-${t.trip_id}-${t.date}`"
                            :href="`/trips/${t.trip_id}`"
                            class="flex items-center gap-1 truncate rounded bg-orange-100 px-1.5 py-0.5 text-[11px] text-orange-800 dark:bg-orange-900/40 dark:text-orange-300"
                            :title="`${t.trip_name}${t.destination ? ' · ' + t.destination : ''}`"
                        >
                            ✈ {{ t.destination || t.trip_name }}
                        </Link>
                        <div
                            v-for="p in personalByDay[cell.iso] || []"
                            :key="`pe-${p.id}-${p.date}`"
                            class="flex items-center gap-1 truncate rounded bg-pink-100 px-1.5 py-0.5 text-[11px] text-pink-800 dark:bg-pink-900/40 dark:text-pink-300"
                            :title="p.title"
                        >
                            🎉 {{ p.title }}
                        </div>
                        <template v-for="ev in eventsByDay[cell.iso] || []" :key="ev.id">
                            <Link
                                v-if="ev.url"
                                :href="ev.url"
                                :class="['truncate rounded px-1.5 py-0.5 text-[11px]', eventClass(ev)]"
                                :title="`${ev.title} — ${fmtTime(ev.starts_at)}`"
                            >
                                <span class="font-medium">{{ fmtTime(ev.starts_at) }}</span>
                                {{ ev.title }}
                            </Link>
                            <div
                                v-else
                                :class="['truncate rounded px-1.5 py-0.5 text-[11px]', eventClass(ev)]"
                                :title="`${ev.title} — ${fmtTime(ev.starts_at)}`"
                            >
                                <span class="font-medium">{{ fmtTime(ev.starts_at) }}</span>
                                {{ ev.title }}
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Week grid -->
            <template v-else-if="view === 'week'">
                <div class="grid grid-cols-[3rem_repeat(7,1fr)] border-b text-xs text-muted-foreground">
                    <div />
                    <button
                        v-for="d in weekDays"
                        :key="d.iso"
                        type="button"
                        class="border-l px-2 py-2 text-left transition hover:bg-muted/50"
                        :class="{ 'font-semibold text-foreground': d.iso === today_iso }"
                        @click="go(d.iso, 'day')"
                    >
                        {{ WEEKDAYS[d.date.getDay()] }} {{ d.date.getDate() }}
                    </button>
                </div>

                <div
                    class="relative grid h-[70vh] grid-cols-[3rem_repeat(7,1fr)] overflow-auto rounded-lg border bg-background"
                >
                    <div class="border-r">
                        <div
                            v-for="h in hours"
                            :key="h"
                            class="h-12 border-b px-1 text-right text-[10px] text-muted-foreground"
                        >
                            {{ h }}:00
                        </div>
                    </div>
                    <div
                        v-for="d in weekDays"
                        :key="d.iso"
                        class="relative border-r last:border-r-0"
                    >
                        <div
                            v-for="h in hours"
                            :key="h"
                            class="h-12 border-b"
                        />
                        <component
                            :is="ev.url ? Link : 'div'"
                            v-for="ev in eventsByDay[d.iso] || []"
                            :key="ev.id"
                            :href="ev.url ?? undefined"
                            :class="['absolute left-1 right-1 rounded px-1.5 py-1 text-[11px] overflow-hidden', eventClass(ev)]"
                            :style="eventSlot(ev)"
                            :title="`${ev.title} — ${fmtTime(ev.starts_at)}`"
                        >
                            <div class="font-medium leading-tight">{{ fmtTime(ev.starts_at) }}</div>
                            <div class="truncate">{{ ev.title }}</div>
                        </component>
                    </div>
                </div>
            </template>

            <!-- Day grid -->
            <template v-else>
                <div class="relative grid h-[75vh] grid-cols-[3.5rem_1fr] overflow-auto rounded-lg border bg-background">
                    <div class="border-r">
                        <div
                            v-for="h in hours"
                            :key="h"
                            class="h-16 border-b px-1 text-right text-[11px] text-muted-foreground"
                        >
                            {{ h }}:00
                        </div>
                    </div>
                    <div class="relative">
                        <div v-for="h in hours" :key="h" class="h-16 border-b" />
                        <component
                            :is="ev.url ? Link : 'div'"
                            v-for="ev in eventsByDay[cursor_iso.slice(0, 10)] || []"
                            :key="ev.id"
                            :href="ev.url ?? undefined"
                            :class="['absolute left-2 right-2 rounded-md px-3 py-2 text-sm', eventClass(ev)]"
                            :style="eventSlot(ev)"
                        >
                            <div class="font-semibold">{{ ev.title }}</div>
                            <div class="text-xs opacity-80">
                                {{ fmtTime(ev.starts_at) }} — {{ fmtTime(ev.ends_at) }}
                            </div>
                            <div v-if="ev.meta" class="mt-1 truncate text-xs opacity-80">
                                {{ ev.meta }}
                            </div>
                        </component>
                    </div>
                </div>
            </template>

            <div class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-primary" /> Cadence meeting
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-emerald-500" /> External (Google / Outlook)
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-orange-500" /> Travel day
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-pink-500" /> Personal event
                </span>
            </div>
        </div>
    </AppLayout>
</template>
