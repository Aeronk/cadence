<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import notificationsRoutes from '@/routes/notifications';

type AppNotification = {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
};

const props = defineProps<{
    notifications: AppNotification[];
    unread_count: number;
}>();

function markRead(n: AppNotification) {
    router.patch(notificationsRoutes.read(n.id).url, {}, { preserveScroll: true });
}

function markAll() {
    router.post(notificationsRoutes['read-all']().url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="[{ title: 'Notifications', href: notificationsRoutes.index().url }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Notifications</h1>
                <Button v-if="unread_count > 0" variant="outline" @click="markAll">
                    Mark all read ({{ unread_count }})
                </Button>
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
                            {{ new Date(n.created_at).toLocaleString() }}
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
