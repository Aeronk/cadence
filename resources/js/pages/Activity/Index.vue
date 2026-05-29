<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import activityRoutes from '@/routes/activity';

type ActivityEntry = {
    id: number;
    action: string;
    description: string;
    created_at: string;
    actor: { name: string } | null;
};

defineProps<{ activity: ActivityEntry[] }>();
</script>

<template>
    <Head title="Activity" />

    <AppLayout :breadcrumbs="[{ title: 'Activity', href: activityRoutes.index().url }]">
        <div class="flex flex-col gap-4 p-6">
            <h1 class="text-2xl font-bold">Workspace activity</h1>

            <div v-if="activity.length === 0" class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                No activity yet.
            </div>

            <ol v-else class="space-y-3">
                <li v-for="entry in activity" :key="entry.id" class="rounded-lg border p-3 text-sm">
                    <p>{{ entry.description }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ new Date(entry.created_at).toLocaleString() }}
                    </p>
                </li>
            </ol>
        </div>
    </AppLayout>
</template>
