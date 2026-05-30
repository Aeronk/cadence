<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BellRing, Calendar, MapPin, Pencil, Sparkles, Trash2, Users2, Video } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import RichEditor from '@/components/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import meetingsRoutes from '@/routes/meetings';

type Meeting = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    location: string | null;
    meeting_url: string | null;
    meeting_type: 'physical' | 'online' | 'hybrid';
    channel: string | null;
    reminder_minutes_before: number | null;
    host: { name: string };
    attendees: { id: number; name: string }[];
    project: { id: number; title: string } | null;
};

const props = defineProps<{ meeting: Meeting }>();

const typeIcon = (t: string) => (t === 'physical' ? MapPin : t === 'hybrid' ? Users2 : Video);

const editOpen = ref(false);
const editForm = useForm({
    title: props.meeting.title,
    description: props.meeting.description ?? '',
    starts_at: props.meeting.starts_at.slice(0, 16),
    ends_at: props.meeting.ends_at.slice(0, 16),
    location: props.meeting.location ?? '',
    meeting_url: props.meeting.meeting_url ?? '',
    meeting_type: props.meeting.meeting_type,
    channel: props.meeting.channel ?? '',
    reminder_minutes_before: props.meeting.reminder_minutes_before,
});

function save() {
    editForm.patch(meetingsRoutes.update(props.meeting.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

function extractActionItems() {
    router.post(
        `/meetings/${props.meeting.id}/extract-action-items`,
        { notes: props.meeting.description ?? '' },
        { preserveScroll: true },
    );
}

function cancelMeeting() {
    if (!confirm(`Cancel "${props.meeting.title}"?`)) return;
    router.delete(meetingsRoutes.destroy(props.meeting.id).url);
}
</script>

<template>
    <Head :title="meeting.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Meetings', href: meetingsRoutes.index().url },
            { title: meeting.title, href: meetingsRoutes.show(meeting.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold">{{ meeting.title }}</h1>
                        <span
                            class="flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs uppercase tracking-wider"
                        >
                            <component :is="typeIcon(meeting.meeting_type)" class="h-3.5 w-3.5" />
                            {{ meeting.meeting_type }}
                        </span>
                        <span
                            v-if="meeting.channel"
                            class="rounded-full bg-primary/10 px-2 py-0.5 text-xs uppercase tracking-wider text-primary"
                        >
                            {{ meeting.channel.replace('_', ' ') }}
                        </span>
                    </div>
                    <p class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                        <Calendar class="h-4 w-4" />
                        {{ new Date(meeting.starts_at).toLocaleString() }} — {{ new Date(meeting.ends_at).toLocaleTimeString() }}
                    </p>
                </div>

                <div class="flex shrink-0 gap-2">
                    <Button
                        v-if="meeting.description"
                        variant="outline"
                        size="sm"
                        @click="extractActionItems"
                    >
                        <Sparkles class="mr-1.5 h-3.5 w-3.5 text-violet-500" /> Extract action items
                    </Button>
                    <Button variant="outline" size="sm" @click="editOpen = true">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit
                    </Button>
                    <Button variant="outline" size="sm" class="text-destructive" @click="cancelMeeting">
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Cancel
                    </Button>
                </div>
            </div>

            <div
                v-if="meeting.description"
                class="prose prose-sm max-w-none dark:prose-invert"
                v-html="meeting.description"
            ></div>

            <Dialog v-model:open="editOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit meeting</DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="save">
                        <div>
                            <Label for="edit-title">Title</Label>
                            <Input id="edit-title" v-model="editForm.title" required />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="edit-starts">Starts</Label>
                                <Input id="edit-starts" v-model="editForm.starts_at" type="datetime-local" required />
                            </div>
                            <div>
                                <Label for="edit-ends">Ends</Label>
                                <Input id="edit-ends" v-model="editForm.ends_at" type="datetime-local" required />
                            </div>
                        </div>
                        <div>
                            <Label>Type</Label>
                            <div class="mt-1 grid grid-cols-3 gap-2">
                                <button
                                    v-for="t in (['online', 'physical', 'hybrid'] as const)"
                                    :key="t"
                                    type="button"
                                    :class="[
                                        'rounded-md border px-3 py-2 text-sm capitalize transition',
                                        editForm.meeting_type === t
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-input hover:bg-muted',
                                    ]"
                                    @click="editForm.meeting_type = t"
                                >
                                    <component :is="typeIcon(t)" class="mx-auto h-4 w-4" />
                                    <span class="mt-1 block">{{ t }}</span>
                                </button>
                            </div>
                        </div>
                        <div v-if="editForm.meeting_type !== 'physical'">
                            <Label for="edit-channel">Channel</Label>
                            <select
                                id="edit-channel"
                                v-model="editForm.channel"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Custom URL</option>
                                <option value="zoom">Zoom</option>
                                <option value="google_meet">Google Meet</option>
                                <option value="microsoft_teams">Microsoft Teams</option>
                                <option value="webex">Webex</option>
                                <option value="phone">Phone</option>
                            </select>
                        </div>
                        <div v-if="editForm.meeting_type !== 'online'">
                            <Label for="edit-location">Location</Label>
                            <Input id="edit-location" v-model="editForm.location" />
                        </div>
                        <div v-if="editForm.meeting_type !== 'physical'">
                            <Label for="edit-url">Meeting URL</Label>
                            <Input id="edit-url" v-model="editForm.meeting_url" placeholder="https://…" />
                        </div>
                        <div>
                            <Label for="edit-reminder">Reminder</Label>
                            <select
                                id="edit-reminder"
                                v-model="editForm.reminder_minutes_before"
                                class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option :value="null">No reminder</option>
                                <option :value="5">5 minutes before</option>
                                <option :value="15">15 minutes before</option>
                                <option :value="30">30 minutes before</option>
                                <option :value="60">1 hour before</option>
                                <option :value="1440">1 day before</option>
                            </select>
                        </div>
                        <div>
                            <Label>Agenda</Label>
                            <RichEditor v-model="editForm.description" placeholder="Topics, links, attendees…" />
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Host</p>
                    <p class="mt-1 font-medium">{{ meeting.host.name }}</p>
                </div>
                <div v-if="meeting.location" class="rounded-lg border p-4">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <MapPin class="h-3 w-3" /> Location
                    </p>
                    <p class="mt-1 font-medium">{{ meeting.location }}</p>
                </div>
                <a
                    v-if="meeting.meeting_url"
                    :href="meeting.meeting_url"
                    target="_blank"
                    rel="noopener"
                    class="rounded-lg border p-4 hover:border-primary"
                >
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Video class="h-3 w-3" /> Join link
                    </p>
                    <p class="mt-1 truncate font-medium text-primary">{{ meeting.meeting_url }}</p>
                </a>
                <div v-if="meeting.reminder_minutes_before" class="rounded-lg border p-4">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <BellRing class="h-3 w-3" /> Reminder
                    </p>
                    <p class="mt-1 font-medium">{{ meeting.reminder_minutes_before }} minutes before</p>
                </div>
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
