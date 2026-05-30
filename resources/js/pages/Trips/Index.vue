<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plane, Plus, MapPin } from 'lucide-vue-next';
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

type Trip = {
    id: number;
    name: string;
    purpose: string | null;
    destination_country: string | null;
    destination_city: string | null;
    departs_at: string;
    returns_at: string;
    status: string;
};

defineProps<{ trips: Trip[] }>();

const dialogOpen = ref(false);
const form = useForm({
    name: '',
    purpose: 'donor',
    destination_city: '',
    destination_country: '',
    departs_at: '',
    returns_at: '',
    notes: '',
});

function submit() {
    form.post('/trips', {
        onSuccess: () => {
            form.reset();
            dialogOpen.value = false;
        },
    });
}

const statusPill = (s: string) => ({
    planned: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    cancelled: 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800',
}[s] ?? 'bg-muted');
</script>

<template>
    <Head title="Trips" />

    <AppLayout :breadcrumbs="[{ title: 'Trips', href: '/trips' }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Trips</h1>
                    <p class="text-sm text-muted-foreground">
                        Plan travel, attach segments, and your calendar will block transit days.
                    </p>
                </div>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" /> New trip
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>New trip</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" placeholder="Nairobi field visit" required />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="purpose">Purpose</Label>
                                    <select
                                        id="purpose"
                                        v-model="form.purpose"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="donor">Donor</option>
                                        <option value="fieldwork">Fieldwork</option>
                                        <option value="conference">Conference</option>
                                        <option value="personal">Personal</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <Label for="destination_city">City</Label>
                                    <Input id="destination_city" v-model="form.destination_city" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="departs_at">Departs</Label>
                                    <Input id="departs_at" v-model="form.departs_at" type="datetime-local" required />
                                </div>
                                <div>
                                    <Label for="returns_at">Returns</Label>
                                    <Input id="returns_at" v-model="form.returns_at" type="datetime-local" required />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div
                v-if="trips.length === 0"
                class="rounded-xl border border-dashed p-12 text-center"
            >
                <Plane class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No trips yet.</p>
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="trip in trips"
                    :key="trip.id"
                    :href="`/trips/${trip.id}`"
                    class="group rounded-xl border bg-card p-5 transition hover:border-primary hover:shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold group-hover:text-primary">{{ trip.name }}</h3>
                            <p class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                <MapPin class="h-3 w-3" />
                                {{ trip.destination_city ?? '—' }}
                                <span v-if="trip.destination_country">· {{ trip.destination_country }}</span>
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wider"
                            :class="statusPill(trip.status)"
                        >
                            {{ trip.status.replace('_', ' ') }}
                        </span>
                    </div>
                    <div class="mt-4 text-xs text-muted-foreground">
                        {{ new Date(trip.departs_at).toLocaleDateString() }} →
                        {{ new Date(trip.returns_at).toLocaleDateString() }}
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
