<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    'Projects, tasks, todos, notes, meetings — one workspace',
    'Two-way Gmail / Outlook email + calendar sync',
    'SMS + WhatsApp outbound and inbound (Twilio + Meta)',
    'Per-workspace activity feed and notifications',
];
</script>

<template>
    <div class="min-h-svh bg-background lg:grid lg:grid-cols-2">
        <!-- Marketing panel -->
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-zinc-950 p-10 text-zinc-100 lg:flex"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(99,102,241,0.18),transparent_55%),radial-gradient(circle_at_80%_80%,rgba(16,185,129,0.18),transparent_50%)]"
            />

            <Link :href="home()" class="relative z-10 flex items-center gap-2 font-semibold">
                <div class="grid h-8 w-8 place-items-center rounded-md bg-white text-zinc-950">C</div>
                Cadence
            </Link>

            <div class="relative z-10 space-y-8">
                <div>
                    <p class="text-sm uppercase tracking-wider text-zinc-400">
                        Productivity, the modern way
                    </p>
                    <h2 class="mt-3 max-w-md text-3xl font-semibold leading-tight">
                        Tasks, calendar and messaging in one cadence.
                    </h2>
                </div>

                <ul class="space-y-3">
                    <li
                        v-for="line in highlights"
                        :key="line"
                        class="flex items-start gap-3 text-sm text-zinc-200"
                    >
                        <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </div>

            <p class="relative z-10 text-xs text-zinc-500">
                &copy; {{ new Date().getFullYear() }} Cadence. Built for teams that ship.
            </p>
        </aside>

        <!-- Form panel -->
        <main class="flex min-h-svh items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-md">
                <Link :href="home()" class="mb-8 flex items-center gap-2 font-semibold lg:hidden">
                    <div class="grid h-8 w-8 place-items-center rounded-md bg-primary text-primary-foreground">
                        C
                    </div>
                    Cadence
                </Link>

                <div class="mb-8 space-y-2">
                    <h1 v-if="title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </main>
    </div>
</template>
