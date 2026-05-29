<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import meetings from '@/routes/meetings';

type Meeting = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    location: string | null;
    meeting_url: string | null;
    host: { name: string };
    attendees: { id: number; name: string }[];
    project: { id: number; title: string } | null;
};

const props = defineProps<{ meeting: Meeting }>();
</script>

<template>
    <Head :title="meeting.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Meetings', href: meetings.index().url },
            { title: meeting.title, href: meetings.show(meeting.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">{{ meeting.title }}</h1>
                <p class="mt-1 text-muted-foreground">
                    {{ new Date(meeting.starts_at).toLocaleString() }} —
                    {{ new Date(meeting.ends_at).toLocaleString() }}
                </p>
            </div>

            <p v-if="meeting.description" class="whitespace-pre-wrap">{{ meeting.description }}</p>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Host</p>
                    <p class="mt-1 font-medium">{{ meeting.host.name }}</p>
                </div>
                <div v-if="meeting.location" class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Location</p>
                    <p class="mt-1 font-medium">{{ meeting.location }}</p>
                </div>
                <a
                    v-if="meeting.meeting_url"
                    :href="meeting.meeting_url"
                    target="_blank"
                    rel="noopener"
                    class="rounded-lg border p-4 hover:border-primary"
                >
                    <p class="text-xs text-muted-foreground">Join link</p>
                    <p class="mt-1 truncate font-medium text-primary">{{ meeting.meeting_url }}</p>
                </a>
            </div>

            <div class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Attendees ({{ meeting.attendees.length }})</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="a in meeting.attendees"
                        :key="a.id"
                        class="rounded-full bg-muted px-3 py-1 text-sm"
                    >
                        {{ a.name }}
                    </span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
