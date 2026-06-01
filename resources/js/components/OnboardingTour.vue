<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarDays,
    CheckSquare,
    LayoutGrid,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Step = {
    icon: typeof LayoutGrid;
    accent: string;
    title: string;
    body: string;
};

const STEPS: Step[] = [
    {
        icon: Sparkles,
        accent: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
        title: 'Welcome to Cadence',
        body: 'Cadence is your single home for projects, tasks, meetings, and personal commitments. This 90-second tour will show you the four screens you will use the most.',
    },
    {
        icon: LayoutGrid,
        accent: 'bg-blue-500/15 text-blue-600 dark:text-blue-400',
        title: 'Projects',
        body: 'Group work into projects. Each project has its own kanban board, milestones, files, comments, and team. Set a budget, status, priority, then mark it complete or put it on hold from the header.',
    },
    {
        icon: CheckSquare,
        accent: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        title: 'Tasks & Todos',
        body: 'Tasks live inside a project and can be assigned to teammates. Todos are personal — quick captures with priority and category. Both surface on your dashboard and analytics.',
    },
    {
        icon: CalendarDays,
        accent: 'bg-pink-500/15 text-pink-600 dark:text-pink-400',
        title: 'Calendar, Trips & Briefing',
        body: 'The calendar combines meetings, travel, personal events, and Google/Outlook items. Each morning the AI Briefing summarises the day, and Cadence reminds you of trips and birthdays in advance.',
    },
    {
        icon: BarChart3,
        accent: 'bg-orange-500/15 text-orange-600 dark:text-orange-400',
        title: 'Analytics & you',
        body: 'Analytics shows how your time is split across the eight life categories, plus completion stats and workload across the team. You can revisit this tour anytime from Settings → Profile.',
    },
];

const page = usePage<{ auth: { needsOnboarding?: boolean } }>();

const open = ref(false);
const index = ref(0);

watch(
    () => page.props.auth?.needsOnboarding,
    (needs) => {
        if (needs) open.value = true;
    },
    { immediate: true },
);

const current = computed(() => STEPS[index.value]);
const isLast = computed(() => index.value === STEPS.length - 1);

function next() {
    if (isLast.value) {
        finish();
    } else {
        index.value++;
    }
}

function prev() {
    if (index.value > 0) index.value--;
}

function finish() {
    router.post(
        '/onboarding/complete',
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                open.value = false;
                index.value = 0;
            },
        },
    );
}

function skip() {
    // Same effect as finish — we don't want to nag.
    finish();
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="grid h-10 w-10 place-items-center rounded-lg"
                        :class="current.accent"
                    >
                        <component :is="current.icon" class="h-5 w-5" />
                    </div>
                    <DialogTitle class="text-lg">{{ current.title }}</DialogTitle>
                </div>
            </DialogHeader>

            <p class="text-sm leading-relaxed text-muted-foreground">
                {{ current.body }}
            </p>

            <div class="flex items-center gap-1.5 pt-2">
                <span
                    v-for="(_, i) in STEPS"
                    :key="i"
                    class="h-1.5 rounded-full transition-all"
                    :class="i === index ? 'w-6 bg-primary' : 'w-1.5 bg-muted'"
                />
            </div>

            <DialogFooter class="flex items-center justify-between gap-2 sm:justify-between">
                <Button variant="ghost" size="sm" @click="skip">Skip tour</Button>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" :disabled="index === 0" @click="prev">
                        Back
                    </Button>
                    <Button size="sm" @click="next">
                        {{ isLast ? 'Got it' : 'Next' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
