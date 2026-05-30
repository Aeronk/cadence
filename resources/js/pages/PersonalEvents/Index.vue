<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Heart, Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PersonalEvent = {
    id: number;
    title: string;
    category: string | null;
    event_date: string;
    recurs_yearly: boolean;
    notes: string | null;
};

defineProps<{ events: PersonalEvent[] }>();

const form = useForm({
    title: '',
    category: 'birthday',
    event_date: '',
    recurs_yearly: true,
    notes: '',
});

function submit() {
    form.post('/personal-events', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(id: number) {
    if (!confirm('Remove this event?')) return;
    router.delete(`/personal-events/${id}`, { preserveScroll: true });
}

const catPill = (c: string | null) => ({
    birthday: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',
    anniversary: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    school: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    health: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    other: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
}[c ?? 'other'] ?? 'bg-muted');
</script>

<template>
    <Head title="Personal events" />

    <AppLayout :breadcrumbs="[{ title: 'Personal', href: '/personal-events' }]">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6">
            <header>
                <h1 class="text-2xl font-bold">Personal events</h1>
                <p class="text-sm text-muted-foreground">
                    Birthdays, anniversaries, school events. Yearly events overlay on your calendar.
                </p>
            </header>

            <form class="space-y-3 rounded-xl border bg-card p-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" placeholder="Mom's birthday" required />
                    </div>
                    <div>
                        <Label for="category">Category</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="birthday">Birthday</option>
                            <option value="anniversary">Anniversary</option>
                            <option value="school">School</option>
                            <option value="health">Health</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <Label for="event_date">Date</Label>
                        <Input id="event_date" v-model="form.event_date" type="date" required />
                    </div>
                    <label class="flex items-center gap-2 pt-7 text-sm">
                        <input type="checkbox" v-model="form.recurs_yearly" />
                        Recurs every year
                    </label>
                </div>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        <Plus class="mr-2 h-4 w-4" /> Add event
                    </Button>
                </div>
            </form>

            <div v-if="events.length === 0" class="rounded-xl border border-dashed p-12 text-center">
                <Heart class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No personal events yet.</p>
            </div>

            <div v-else class="overflow-hidden rounded-xl border">
                <div
                    v-for="ev in events"
                    :key="ev.id"
                    class="group flex items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0"
                >
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            {{ ev.title }}
                            <span
                                v-if="ev.category"
                                class="rounded-full px-2 py-0.5 text-[10px] capitalize"
                                :class="catPill(ev.category)"
                            >
                                {{ ev.category }}
                            </span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ new Date(ev.event_date).toLocaleDateString() }}
                            <span v-if="ev.recurs_yearly"> · every year</span>
                        </p>
                    </div>
                    <button
                        class="opacity-0 transition group-hover:opacity-100"
                        @click="remove(ev.id)"
                    >
                        <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
