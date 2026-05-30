<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plane, Trash2, Plus, Hotel, Train, Car, MapPin } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Segment = {
    id: number;
    type: string;
    reference: string | null;
    from_location: string | null;
    to_location: string | null;
    starts_at: string;
    ends_at: string | null;
    details: string | null;
};
type ChecklistItem = { id: number; title: string; checked: boolean; position: number };
type Trip = {
    id: number;
    name: string;
    purpose: string | null;
    destination_country: string | null;
    destination_city: string | null;
    departs_at: string;
    returns_at: string;
    status: string;
    notes: string | null;
};

const props = defineProps<{
    trip: Trip;
    segments: Segment[];
    checklist: ChecklistItem[];
}>();

const segmentForm = useForm({
    type: 'flight',
    reference: '',
    from_location: '',
    to_location: '',
    starts_at: '',
    ends_at: '',
    details: '',
});
const showSegmentForm = ref(false);

function addSegment() {
    segmentForm.post(`/trips/${props.trip.id}/segments`, {
        preserveScroll: true,
        onSuccess: () => {
            segmentForm.reset();
            showSegmentForm.value = false;
        },
    });
}

function removeSegment(id: number) {
    if (!confirm('Remove this segment?')) return;
    router.delete(`/trip-segments/${id}`, { preserveScroll: true });
}

const itemForm = useForm({ title: '' });

function addItem() {
    itemForm.post(`/trips/${props.trip.id}/checklist`, {
        preserveScroll: true,
        onSuccess: () => itemForm.reset(),
    });
}
function toggleItem(id: number) {
    router.patch(`/trip-checklist/${id}/toggle`, {}, { preserveScroll: true });
}
function removeItem(id: number) {
    router.delete(`/trip-checklist/${id}`, { preserveScroll: true });
}

function segmentIcon(type: string) {
    return ({
        flight: Plane,
        train: Train,
        drive: Car,
        hotel: Hotel,
    } as Record<string, unknown>)[type] ?? MapPin;
}

const tripDelete = () => {
    if (!confirm(`Delete trip "${props.trip.name}"?`)) return;
    router.delete(`/trips/${props.trip.id}`);
};
</script>

<template>
    <Head :title="trip.name" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Trips', href: '/trips' },
            { title: trip.name, href: `/trips/${trip.id}` },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">{{ trip.name }}</h1>
                    <p class="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                        <MapPin class="h-4 w-4" />
                        {{ trip.destination_city ?? '—' }}
                        <span v-if="trip.destination_country">· {{ trip.destination_country }}</span>
                        ·
                        {{ new Date(trip.departs_at).toLocaleDateString() }} →
                        {{ new Date(trip.returns_at).toLocaleDateString() }}
                    </p>
                </div>
                <Button variant="outline" size="sm" class="text-destructive" @click="tripDelete">
                    <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Delete trip
                </Button>
            </header>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Segments -->
                <section class="rounded-xl border bg-card">
                    <header class="flex items-center justify-between border-b px-4 py-3">
                        <h2 class="font-semibold">Itinerary</h2>
                        <Button size="sm" variant="ghost" @click="showSegmentForm = !showSegmentForm">
                            <Plus class="mr-1.5 h-3.5 w-3.5" /> Add segment
                        </Button>
                    </header>

                    <form
                        v-if="showSegmentForm"
                        class="space-y-3 border-b p-4"
                        @submit.prevent="addSegment"
                    >
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="seg_type">Type</Label>
                                <select
                                    id="seg_type"
                                    v-model="segmentForm.type"
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="flight">Flight</option>
                                    <option value="train">Train</option>
                                    <option value="drive">Drive</option>
                                    <option value="hotel">Hotel</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <Label for="seg_ref">Reference</Label>
                                <Input id="seg_ref" v-model="segmentForm.reference" placeholder="KQ 102" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="seg_from">From</Label>
                                <Input id="seg_from" v-model="segmentForm.from_location" />
                            </div>
                            <div>
                                <Label for="seg_to">To</Label>
                                <Input id="seg_to" v-model="segmentForm.to_location" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <Label for="seg_starts">Starts</Label>
                                <Input id="seg_starts" v-model="segmentForm.starts_at" type="datetime-local" required />
                            </div>
                            <div>
                                <Label for="seg_ends">Ends</Label>
                                <Input id="seg_ends" v-model="segmentForm.ends_at" type="datetime-local" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="ghost" @click="showSegmentForm = false">Cancel</Button>
                            <Button type="submit" :disabled="segmentForm.processing">Add</Button>
                        </div>
                    </form>

                    <div v-if="segments.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                        No segments yet.
                    </div>
                    <div
                        v-for="seg in segments"
                        :key="seg.id"
                        class="group flex items-start gap-3 border-b px-4 py-3 last:border-b-0"
                    >
                        <component :is="segmentIcon(seg.type)" class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                {{ seg.reference || seg.type }}
                                <span v-if="seg.from_location || seg.to_location" class="text-muted-foreground">
                                    · {{ seg.from_location }} → {{ seg.to_location }}
                                </span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ new Date(seg.starts_at).toLocaleString() }}
                                <span v-if="seg.ends_at"> — {{ new Date(seg.ends_at).toLocaleString() }}</span>
                            </p>
                            <p v-if="seg.details" class="mt-1 text-xs text-muted-foreground">{{ seg.details }}</p>
                        </div>
                        <button
                            class="opacity-0 transition group-hover:opacity-100"
                            @click="removeSegment(seg.id)"
                        >
                            <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                        </button>
                    </div>
                </section>

                <!-- Checklist -->
                <section class="rounded-xl border bg-card">
                    <header class="border-b px-4 py-3 font-semibold">Packing &amp; prep checklist</header>
                    <form class="flex gap-2 border-b p-4" @submit.prevent="addItem">
                        <Input
                            v-model="itemForm.title"
                            placeholder="Passport, malaria pills…"
                            required
                            class="flex-1"
                        />
                        <Button type="submit" :disabled="itemForm.processing">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </form>

                    <div v-if="checklist.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                        Empty list. Add what you don't want to forget.
                    </div>

                    <div
                        v-for="item in checklist"
                        :key="item.id"
                        class="group flex items-center gap-3 border-b px-4 py-2.5 last:border-b-0"
                    >
                        <input
                            type="checkbox"
                            :checked="item.checked"
                            class="h-4 w-4"
                            @change="toggleItem(item.id)"
                        />
                        <span
                            class="flex-1 text-sm"
                            :class="{ 'line-through text-muted-foreground': item.checked }"
                        >
                            {{ item.title }}
                        </span>
                        <button
                            class="opacity-0 transition group-hover:opacity-100"
                            @click="removeItem(item.id)"
                        >
                            <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
