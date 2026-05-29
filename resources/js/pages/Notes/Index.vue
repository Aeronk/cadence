<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Pin, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import notesRoutes from '@/routes/notes';

type Note = {
    id: number;
    title: string;
    body: string | null;
    color: string;
    is_pinned: boolean;
    updated_at: string;
};

defineProps<{ notes: Note[] }>();

const showForm = ref(false);
const form = useForm({ title: '', body: '', color: 'yellow' });

function add() {
    form.post(notesRoutes.store().url, {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function togglePin(note: Note) {
    router.patch(notesRoutes.update(note.id).url, { is_pinned: !note.is_pinned }, { preserveScroll: true });
}

function remove(note: Note) {
    router.delete(notesRoutes.destroy(note.id).url, { preserveScroll: true });
}

const colorClass = (color: string) =>
    ({
        yellow: 'bg-yellow-100 dark:bg-yellow-900/30',
        green: 'bg-green-100 dark:bg-green-900/30',
        blue: 'bg-blue-100 dark:bg-blue-900/30',
        pink: 'bg-pink-100 dark:bg-pink-900/30',
        gray: 'bg-gray-100 dark:bg-gray-900/30',
    })[color] ?? 'bg-yellow-100';
</script>

<template>
    <Head title="Notes" />

    <AppLayout :breadcrumbs="[{ title: 'Notes', href: notesRoutes.index().url }]">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Notes</h1>
                <Button @click="showForm = !showForm">
                    <Plus class="mr-2 h-4 w-4" /> New note
                </Button>
            </div>

            <form v-if="showForm" class="space-y-2 rounded-lg border p-4" @submit.prevent="add">
                <Input v-model="form.title" placeholder="Title" required />
                <textarea
                    v-model="form.body"
                    placeholder="Body…"
                    rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <div class="flex items-center justify-end gap-2">
                    <Button type="button" variant="ghost" @click="showForm = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">Save</Button>
                </div>
            </form>

            <div v-if="notes.length === 0" class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                No notes yet.
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="note in notes"
                    :key="note.id"
                    :class="['rounded-lg p-4 shadow-sm', colorClass(note.color)]"
                >
                    <div class="mb-2 flex items-start justify-between">
                        <h3 class="font-semibold">{{ note.title }}</h3>
                        <div class="flex gap-1">
                            <button @click="togglePin(note)" class="text-muted-foreground hover:text-foreground">
                                <Pin class="h-4 w-4" :class="{ 'fill-current': note.is_pinned }" />
                            </button>
                            <button @click="remove(note)" class="text-muted-foreground hover:text-foreground">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <p class="line-clamp-6 whitespace-pre-wrap text-sm">{{ note.body }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
