<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Calendar, Plus } from 'lucide-vue-next';
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
import meetingsRoutes from '@/routes/meetings';

type Meeting = {
    id: number;
    title: string;
    starts_at: string;
    ends_at: string;
    location: string | null;
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
});

function submit() {
    form.post(meetingsRoutes.store().url, {
        onSuccess: () => {
            form.reset();
            dialogOpen.value = false;
        },
    });
}
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
                                <Label for="location">Location</Label>
                                <Input id="location" v-model="form.location" placeholder="Optional" />
                            </div>
                            <div>
                                <Label for="meeting_url">Meeting URL</Label>
                                <Input id="meeting_url" v-model="form.meeting_url" placeholder="https://…" />
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
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold">{{ meeting.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ new Date(meeting.starts_at).toLocaleString() }}
                            </p>
                            <p v-if="meeting.location" class="mt-1 text-xs text-muted-foreground">
                                📍 {{ meeting.location }}
                            </p>
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ meeting.attendees.length }} attending
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
