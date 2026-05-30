<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Calendar, MapPin, Plus, Video, Users2, BellRing } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import RichEditor from '@/components/RichEditor.vue';
import meetingsRoutes from '@/routes/meetings';

type Meeting = {
    id: number;
    title: string;
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

defineProps<{ meetings: Meeting[] }>();

const dialogOpen = ref(false);
const form = useForm({
    title: '',
    description: '',
    starts_at: '',
    ends_at: '',
    location: '',
    meeting_url: '',
    meeting_type: 'online' as 'physical' | 'online' | 'hybrid',
    channel: '',
    reminder_minutes_before: 15 as number | null,
});

function submit() {
    form.post(meetingsRoutes.store().url, {
        onSuccess: () => {
            form.reset();
            dialogOpen.value = false;
        },
    });
}

const typeIcon = (t: string) => (t === 'physical' ? MapPin : t === 'hybrid' ? Users2 : Video);
</script>

<template>
    <Head title="Meetings" />

    <AppLayout :breadcrumbs="[{ title: 'Meetings', href: meetingsRoutes.index().url }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Meetings</h1>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" /> Schedule
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Schedule a meeting</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <Label for="title">Title</Label>
                                <Input id="title" v-model="form.title" required />
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="starts_at">Starts</Label>
                                    <Input id="starts_at" v-model="form.starts_at" type="datetime-local" required />
                                </div>
                                <div>
                                    <Label for="ends_at">Ends</Label>
                                    <Input id="ends_at" v-model="form.ends_at" type="datetime-local" required />
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
                                            form.meeting_type === t
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-input hover:bg-muted',
                                        ]"
                                        @click="form.meeting_type = t"
                                    >
                                        <component :is="typeIcon(t)" class="mx-auto h-4 w-4" />
                                        <span class="mt-1 block">{{ t }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="form.meeting_type !== 'physical'">
                                <Label for="channel">Channel</Label>
                                <select
                                    id="channel"
                                    v-model="form.channel"
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

                            <div v-if="form.meeting_type !== 'online'">
                                <Label for="location">Location</Label>
                                <Input
                                    id="location"
                                    v-model="form.location"
                                    placeholder="Room, address, etc."
                                />
                            </div>

                            <div v-if="form.meeting_type !== 'physical'">
                                <Label for="meeting_url">Meeting URL</Label>
                                <Input id="meeting_url" v-model="form.meeting_url" placeholder="https://…" />
                            </div>

                            <div>
                                <Label for="reminder">
                                    <BellRing class="mr-1 inline h-3.5 w-3.5" />
                                    Reminder
                                </Label>
                                <select
                                    id="reminder"
                                    v-model="form.reminder_minutes_before"
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
                                <RichEditor v-model="form.description" placeholder="Topics, links, attendees…" />
                            </div>

                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Schedule</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div v-if="meetings.length === 0" class="rounded-lg border border-dashed p-12 text-center">
                <Calendar class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No meetings scheduled.</p>
            </div>

            <div v-else class="space-y-2">
                <Link
                    v-for="meeting in meetings"
                    :key="meeting.id"
                    :href="meetingsRoutes.show(meeting.id).url"
                    class="block rounded-lg border p-4 hover:border-primary"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">{{ meeting.title }}</h3>
                                <span
                                    class="flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[10px] uppercase tracking-wider"
                                >
                                    <component :is="typeIcon(meeting.meeting_type)" class="h-3 w-3" />
                                    {{ meeting.meeting_type }}
                                </span>
                                <span
                                    v-if="meeting.channel"
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] uppercase tracking-wider text-primary"
                                >
                                    {{ meeting.channel.replace('_', ' ') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ new Date(meeting.starts_at).toLocaleString() }}
                            </p>
                            <p v-if="meeting.location" class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                <MapPin class="h-3 w-3" /> {{ meeting.location }}
                            </p>
                            <p v-if="meeting.meeting_url" class="mt-1 truncate text-xs text-primary">
                                {{ meeting.meeting_url }}
                            </p>
                        </div>
                        <div class="text-right text-xs text-muted-foreground">
                            <p>{{ meeting.attendees.length }} attending</p>
                            <p v-if="meeting.reminder_minutes_before" class="mt-1 flex items-center justify-end gap-1">
                                <BellRing class="h-3 w-3" /> {{ meeting.reminder_minutes_before }}m
                            </p>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
