<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import calendar from '@/routes/calendar';

type CalendarEvent = {
    id: string;
    source: 'cadence' | 'external';
    title: string;
    starts_at: string;
    ends_at: string;
    url: string | null;
    meta: string | null;
};

const props = defineProps<{
    cursor_iso: string;
    cursor_label: string;
    prev_month: string;
    next_month: string;
    today_iso: string;
    events: CalendarEvent[];
}>();

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const cursorDate = computed(() => new Date(props.cursor_iso));

const days = computed(() => {
    const first = new Date(cursorDate.value);
    first.setDate(1);
    const startOffset = first.getDay(); // 0=Sun
    const start = new Date(first);
    start.setDate(first.getDate() - startOffset);

    const cells: { date: Date; inMonth: boolean; iso: string }[] = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const iso = d.toISOString().slice(0, 10);
        cells.push({
            date: d,
            inMonth: d.getMonth() === cursorDate.value.getMonth(),
            iso,
        });
    }
    return cells;
});

const eventsByDay = computed(() => {
    const map: Record<string, CalendarEvent[]> = {};
    for (const ev of props.events) {
        const key = ev.starts_at.slice(0, 10);
        (map[key] ||= []).push(ev);
    }
    return map;
});

function go(month: string) {
    router.get(calendar({ query: { month } }).url, {}, { preserveScroll: true });
}

function today() {
    const t = new Date();
    go(`${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}`);
}

function eventTime(iso: string) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="[{ title: 'Calendar', href: calendar().url }]">
        <div class="flex h-full flex-col gap-4 p-6">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ cursor_label }}</h1>
                    <p class="text-xs text-muted-foreground">
                        Cadence meetings &amp; connected Google / Outlook events
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="go(prev_month)">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" @click="today">Today</Button>
                    <Button variant="outline" size="sm" @click="go(next_month)">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </header>

            <div class="grid grid-cols-7 border-b text-xs font-medium text-muted-foreground">
                <div v-for="w in WEEKDAYS" :key="w" class="px-2 py-2">{{ w }}</div>
            </div>

            <div class="grid flex-1 grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border">
                <div
                    v-for="cell in days"
                    :key="cell.iso"
                    class="flex min-h-[6rem] flex-col gap-1 bg-background p-1.5"
                    :class="{ 'opacity-50': !cell.inMonth }"
                >
                    <div class="flex items-center justify-between text-[11px]">
                        <span
                            :class="
                                cell.iso === today_iso
                                    ? 'grid h-5 w-5 place-items-center rounded-full bg-primary text-primary-foreground font-semibold'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ cell.date.getDate() }}
                        </span>
                        <CalendarDays
                            v-if="cell.iso === today_iso"
                            class="h-3 w-3 text-primary"
                        />
                    </div>

                    <component
                        :is="ev.url ? Link : 'div'"
                        v-for="ev in eventsByDay[cell.iso] || []"
                        :key="ev.id"
                        :href="ev.url ?? undefined"
                        :class="[
                            'truncate rounded px-1.5 py-0.5 text-[11px] leading-tight',
                            ev.source === 'cadence'
                                ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                        ]"
                        :title="`${ev.title} — ${eventTime(ev.starts_at)}`"
                    >
                        <span class="font-medium">{{ eventTime(ev.starts_at) }}</span>
                        {{ ev.title }}
                    </component>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-primary"></span> Cadence meeting
                </span>
                <span class="flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> External (Google / Outlook)
                </span>
            </div>
        </div>
    </AppLayout>
</template>
