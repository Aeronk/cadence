<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import { CheckCircle2, Mail, MessageSquare, CalendarDays, ListTodo, Zap } from 'lucide-vue-next';

const features = [
    {
        icon: ListTodo,
        title: 'Projects & tasks',
        description: 'Workspaces, statuses, priorities, tags, subtasks, comments — everything teams need to ship.',
    },
    {
        icon: CalendarDays,
        title: 'Two-way calendar sync',
        description: 'Google Calendar and Microsoft 365. Meetings created in Cadence land on the right calendar instantly.',
    },
    {
        icon: Mail,
        title: 'Email threads on tasks',
        description: 'Connect Gmail or Outlook. Attach email threads to tasks and reply without leaving Cadence.',
    },
    {
        icon: MessageSquare,
        title: 'SMS & WhatsApp',
        description: 'Outbound notifications and two-way conversations powered by Twilio and Meta WhatsApp Cloud.',
    },
    {
        icon: Zap,
        title: 'Real-time activity',
        description: 'Every action streams into a per-workspace feed so your team always knows what changed.',
    },
    {
        icon: CheckCircle2,
        title: 'Built for teams',
        description: 'Role-based access, per-user notification preferences, soft deletes, full audit log.',
    },
];

const plans = [
    {
        name: 'Free',
        price: '$0',
        period: 'forever',
        cta: 'Start free',
        highlighted: false,
        features: ['1 workspace', 'Up to 3 members', 'Unlimited tasks & projects', 'Email + calendar integrations'],
    },
    {
        name: 'Team',
        price: '$12',
        period: 'per user / month',
        cta: 'Start free trial',
        highlighted: true,
        features: ['Unlimited workspaces', 'Unlimited members', 'SMS + WhatsApp', 'Activity log + audit', 'Priority support'],
    },
    {
        name: 'Business',
        price: 'Contact us',
        period: 'custom',
        cta: 'Get in touch',
        highlighted: false,
        features: ['SSO + SCIM', 'Custom integrations', 'Dedicated success manager', 'SLA & on-prem options'],
    },
];
</script>

<template>
    <Head title="Cadence — task & productivity for teams" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border/40">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2 font-semibold">
                    <div class="grid h-8 w-8 place-items-center rounded-md bg-primary text-primary-foreground">C</div>
                    Cadence
                </div>
                <nav class="flex items-center gap-4 text-sm">
                    <a href="#features" class="text-muted-foreground hover:text-foreground">Features</a>
                    <a href="#pricing" class="text-muted-foreground hover:text-foreground">Pricing</a>
                    <Link v-if="$page.props.auth.user" :href="dashboard()" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                        Open app
                    </Link>
                    <template v-else>
                        <Link :href="login()" class="text-muted-foreground hover:text-foreground">Log in</Link>
                        <Link :href="register()" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
                            Get started
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <section class="mx-auto max-w-7xl px-6 py-24 text-center">
            <p class="mb-4 inline-block rounded-full border border-border bg-muted/50 px-3 py-1 text-xs font-medium text-muted-foreground">
                New: Gmail + Calendar sync in beta
            </p>
            <h1 class="mx-auto max-w-3xl text-5xl font-bold tracking-tight md:text-6xl">
                Tasks, calendar and messaging in one cadence.
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                Run projects, schedule meetings, reply to email threads and send SMS or WhatsApp updates —
                from a single workspace your whole team shares.
            </p>
            <div class="mt-10 flex items-center justify-center gap-3">
                <Link :href="register()" class="rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                    Start free
                </Link>
                <a href="#features" class="rounded-md border border-border px-6 py-3 text-sm font-medium hover:bg-muted">
                    See features
                </a>
            </div>
        </section>

        <section id="features" class="border-t border-border/40 bg-muted/20 py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto mb-16 max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight">Everything in one place</h2>
                    <p class="mt-3 text-muted-foreground">No more juggling six tools. Cadence brings work, calendar and conversations together.</p>
                </div>
                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="feature in features" :key="feature.title" class="rounded-lg border border-border bg-background p-6">
                        <component :is="feature.icon" class="mb-4 h-6 w-6 text-primary" />
                        <h3 class="mb-2 font-semibold">{{ feature.title }}</h3>
                        <p class="text-sm text-muted-foreground">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing" class="py-24">
            <div class="mx-auto max-w-7xl px-6">
                <div class="mx-auto mb-16 max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight">Simple pricing</h2>
                    <p class="mt-3 text-muted-foreground">Start free. Upgrade when your team grows.</p>
                </div>
                <div class="grid gap-6 md:grid-cols-3">
                    <div
                        v-for="plan in plans"
                        :key="plan.name"
                        :class="[
                            'rounded-lg border p-8',
                            plan.highlighted ? 'border-primary bg-primary/5 ring-2 ring-primary' : 'border-border bg-background',
                        ]"
                    >
                        <h3 class="font-semibold">{{ plan.name }}</h3>
                        <div class="mt-4">
                            <span class="text-4xl font-bold">{{ plan.price }}</span>
                            <span class="ml-1 text-sm text-muted-foreground">{{ plan.period }}</span>
                        </div>
                        <Link
                            :href="register()"
                            :class="[
                                'mt-6 block w-full rounded-md px-4 py-2 text-center text-sm font-medium',
                                plan.highlighted
                                    ? 'bg-primary text-primary-foreground hover:opacity-90'
                                    : 'border border-border hover:bg-muted',
                            ]"
                        >
                            {{ plan.cta }}
                        </Link>
                        <ul class="mt-6 space-y-3">
                            <li v-for="f in plan.features" :key="f" class="flex items-start gap-2 text-sm">
                                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>{{ f }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-border/40 bg-muted/20 py-24">
            <div class="mx-auto max-w-3xl px-6 text-center">
                <h2 class="text-3xl font-bold tracking-tight">Get your team in cadence.</h2>
                <p class="mt-3 text-muted-foreground">Free workspace, no credit card required.</p>
                <Link :href="register()" class="mt-8 inline-block rounded-md bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                    Create your workspace
                </Link>
            </div>
        </section>

        <footer class="border-t border-border/40 py-8">
            <div class="mx-auto max-w-7xl px-6 text-center text-sm text-muted-foreground">
                &copy; {{ new Date().getFullYear() }} Cadence. All rights reserved.
            </div>
        </footer>
    </div>
</template>
