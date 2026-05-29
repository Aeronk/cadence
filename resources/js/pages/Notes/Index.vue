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

const COLORS = ['yellow', 'green', 'blue', 'pink', 'purple', 'orange', 'gray'] as const;
type NoteColor = (typeof COLORS)[number];

const colorSwatch: Record<NoteColor, string> = {
    yellow: 'bg-yellow-300',
    green: 'bg-emerald-300',
    blue: 'bg-sky-300',
    pink: 'bg-pink-300',
    purple: 'bg-violet-300',
    orange: 'bg-orange-300',
    gray: 'bg-zinc-300',
};

const colorCard: Record<NoteColor, string> = {
    yellow: 'bg-yellow-100 ring-yellow-200 dark:bg-yellow-900/30 dark:ring-yellow-800/40',
    green: 'bg-emerald-100 ring-emerald-200 dark:bg-emerald-900/30 dark:ring-emerald-800/40',
    blue: 'bg-sky-100 ring-sky-200 dark:bg-sky-900/30 dark:ring-sky-800/40',
    pink: 'bg-pink-100 ring-pink-200 dark:bg-pink-900/30 dark:ring-pink-800/40',
    purple: 'bg-violet-100 ring-violet-200 dark:bg-violet-900/30 dark:ring-violet-800/40',
    orange: 'bg-orange-100 ring-orange-200 dark:bg-orange-900/30 dark:ring-orange-800/40',
    gray: 'bg-zinc-100 ring-zinc-200 dark:bg-zinc-900/30 dark:ring-zinc-800/40',
};

const showForm = ref(false);
const form = useForm<{ title: string; body: string; color: NoteColor }>({
    title: '',
    body: '',
    color: 'yellow',
});

function add() {
    form.post(notesRoutes.store().url, {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function togglePin(note: Note) {
    router.patch(
        notesRoutes.update(note.id).url,
        { is_pinned: !note.is_pinned },
        { preserveScroll: true },
    );
}

function setColor(note: Note, color: NoteColor) {
    router.patch(notesRoutes.update(note.id).url, { color }, { preserveScroll: true });
}

function remove(note: Note) {
    if (!confirm('Delete this note?')) return;
    router.delete(notesRoutes.destroy(note.id).url, { preserveScroll: true });
}

const cardClass = (color: string) =>
    colorCard[color as NoteColor] ?? colorCard.yellow;
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

            <form
                v-if="showForm"
                class="space-y-3 rounded-lg border p-4"
                @submit.prevent="add"
            >
                <Input v-model="form.title" placeholder="Title" required />
                <textarea
                    v-model="form.body"
                    placeholder="Body…"
                    rows="4"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <div class="flex items-center justify-between">
                    <div class="flex gap-2">
                        <button
                            v-for="c in COLORS"
                            :key="c"
                            type="button"
                            :title="c"
                            :class="[
                                'h-6 w-6 rounded-full ring-2 transition',
                                colorSwatch[c],
                                form.color === c
                                    ? 'ring-foreground'
                                    : 'ring-transparent hover:ring-foreground/30',
                            ]"
                            @click="form.color = c"
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="ghost" @click="showForm = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </div>
                </div>
            </form>

            <div
                v-if="notes.length === 0"
                class="rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground"
            >
                No notes yet.
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <div
                    v-for="note in notes"
                    :key="note.id"
                    :class="['group flex flex-col rounded-lg p-4 shadow-sm ring-1', cardClass(note.color)]"
                >
                    <div class="mb-2 flex items-start justify-between">
                        <h3 class="font-semibold">{{ note.title }}</h3>
                        <div class="flex gap-1 opacity-60 transition group-hover:opacity-100">
                            <button
                                :title="note.is_pinned ? 'Unpin' : 'Pin'"
                                @click="togglePin(note)"
                            >
                                <Pin
                                    class="h-4 w-4"
                                    :class="{ 'fill-current': note.is_pinned }"
                                />
                            </button>
                            <button title="Delete" @click="remove(note)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <p class="line-clamp-6 flex-1 whitespace-pre-wrap text-sm">
                        {{ note.body }}
                    </p>

                    <div class="mt-3 flex items-center justify-between border-t border-current/10 pt-2">
                        <div class="flex gap-1">
                            <button
                                v-for="c in COLORS"
                                :key="c"
                                type="button"
                                :title="c"
                                :class="[
                                    'h-4 w-4 rounded-full ring-2 transition',
                                    colorSwatch[c],
                                    note.color === c ? 'ring-foreground' : 'ring-transparent hover:ring-foreground/40',
                                ]"
                                @click="setColor(note, c)"
                            />
                        </div>
                        <span class="text-[10px] uppercase tracking-wider text-muted-foreground/70">
                            {{ new Date(note.updated_at).toLocaleDateString() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
