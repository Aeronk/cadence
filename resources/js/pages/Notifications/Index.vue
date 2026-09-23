<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, Send, Activity } from 'lucide-vue-next';
import { onMounted, onBeforeUnmount, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { getEcho } from '@/lib/echo';
import notificationsRoutes from '@/routes/notifications';
import { formatDateTime, formatRelative } from '@/lib/dates';

type AppNotification = {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
};

type Diagnostics = {
    total: number;
    last_at: string | null;
    realtime_key_set: boolean;
    broadcaster: string;
};

const props = defineProps<{
    notifications: AppNotification[];
    unread_count: number;
    diagnostics: Diagnostics;
}>();

function markRead(n: AppNotification) {
    router.patch(notificationsRoutes.read(n.id).url, {}, { preserveScroll: true });
}

function markAll() {
    router.post(notificationsRoutes.readAll().url, {}, { preserveScroll: true });
}

function sendTest() {
    router.post('/notifications/test', {}, { preserveScroll: true });
}

// Live WS state: shows whether the Reverb / Echo connection is actually up
// in this browser session, beyond just having the env key set.
type WsState = 'connecting' | 'connected' | 'unavailable' | 'failed';
const wsState = ref<WsState>('connecting');

onMounted(async () => {
    if (!props.diagnostics.realtime_key_set) {
        wsState.value = 'unavailable';
        return;
    }
    const echo = (await getEcho()) as
        | { connector?: { pusher?: { connection?: { state: string; bind: Function } } } }
        | null;
    const pusher = echo?.connector?.pusher;
    if (!pusher?.connection) {
        wsState.value = 'unavailable';
        return;
    }
    const apply = (state: string) => {
        if (state === 'connected') wsState.value = 'connected';
        else if (state === 'failed' || state === 'disconnected') wsState.value = 'failed';
        else wsState.value = 'connecting';
    };
    apply(pusher.connection.state);
    pusher.connection.bind('state_change', (s: { current: string }) => apply(s.current));
});

onBeforeUnmount(() => {
    // no manual unbind needed; connection persists app-wide
});
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="[{ title: 'Notifications', href: notificationsRoutes.index().url }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-bold">Notifications</h1>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="sendTest">
                        <Send class="mr-1.5 h-3.5 w-3.5" /> Send test
                    </Button>
                    <Button v-if="unread_count > 0" variant="outline" size="sm" @click="markAll">
                        Mark all read ({{ unread_count }})
                    </Button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Total stored</p>
                    <p class="text-xl font-bold tabular-nums">{{ diagnostics.total }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">Last received</p>
                    <p class="text-sm">
                        {{ formatDateTime(diagnostics.last_at) || 'Never' }}
                    </p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Activity class="h-3 w-3" /> Real-time
                    </p>
                    <p class="mt-1 flex items-center gap-1.5 text-sm">
                        <span v-if="wsState === 'connected'" class="inline-flex items-center gap-1 text-emerald-600">
                            <CheckCircle2 class="h-4 w-4" /> Connected
                        </span>
                        <span v-else-if="wsState === 'connecting'" class="text-amber-600">
                            Connecting…
                        </span>
                        <span v-else-if="wsState === 'failed'" class="inline-flex items-center gap-1 text-red-600">
                            <XCircle class="h-4 w-4" /> Failed
                        </span>
                        <span v-else class="text-muted-foreground">
                            Disabled (no key)
                        </span>
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Driver: {{ diagnostics.broadcaster }}
                    </p>
                </div>
            </div>

            <div v-if="notifications.length === 0" class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                You're all caught up.
            </div>

            <div v-else class="rounded-lg border">
                <div
                    v-for="n in notifications"
                    :key="n.id"
                    :class="['flex items-start justify-between border-b p-4 last:border-b-0', !n.read_at && 'bg-primary/5']"
                >
                    <div>
                        <p class="text-sm font-medium">{{ n.data.task_title ?? n.data.meeting_title ?? n.type }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ formatRelative(n.created_at) }}
                        </p>
                    </div>
                    <button
                        v-if="!n.read_at"
                        @click="markRead(n)"
                        class="text-xs text-primary hover:underline"
                    >
                        Mark read
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
