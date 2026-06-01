<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Flame, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import todosRoutes from '@/routes/todos';

type Priority = 'low' | 'medium' | 'high';

type Todo = {
    id: number;
    title: string;
    description: string | null;
    priority: Priority;
    category: string | null;
    due_date: string | null;
    completed_at: string | null;
};
type CategoryOption = { value: string; label: string; color: string };

const props = defineProps<{ todos: Todo[]; categories: CategoryOption[] }>();

const form = useForm<{
    title: string;
    description: string;
    priority: Priority;
    category: string;
    due_date: string;
}>({
    title: '',
    description: '',
    priority: 'medium',
    category: '',
    due_date: '',
});

const PRIORITY_ACCENT: Record<Priority, string> = {
    high: 'border-l-red-500 bg-red-50/30 dark:bg-red-950/10',
    medium: 'border-l-blue-500',
    low: 'border-l-zinc-300 dark:border-l-zinc-700',
};

function isOverdue(todo: Todo): boolean {
    if (!todo.due_date || todo.completed_at) return false;
    return new Date(todo.due_date) < new Date(new Date().toDateString());
}

function categoryPillClass(color: string) {
    return ({
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        purple: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        sky: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    } as Record<string, string>)[color] ?? 'bg-muted text-muted-foreground';
}

const PRIORITY_META: Record<Priority, { label: string; pill: string; flame: string }> = {
    low: {
        label: 'Low',
        pill: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        flame: 'text-zinc-400',
    },
    medium: {
        label: 'Medium',
        pill: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        flame: 'text-blue-500',
    },
    high: {
        label: 'High',
        pill: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        flame: 'text-red-500',
    },
};

const PRIORITY_ORDER: Record<Priority, number> = { high: 0, medium: 1, low: 2 };

const sorted = computed(() =>
    [...props.todos].sort((a, b) => {
        // Completed at bottom; within active sort by priority then due date.
        if (!!a.completed_at !== !!b.completed_at) {
            return a.completed_at ? 1 : -1;
        }
        const p = PRIORITY_ORDER[a.priority] - PRIORITY_ORDER[b.priority];
        if (p !== 0) return p;
        if (a.due_date && b.due_date) return a.due_date.localeCompare(b.due_date);
        return 0;
    }),
);

function add() {
    form.post(todosRoutes.store().url, {
        onSuccess: () => form.reset('title', 'description', 'due_date', 'category'),
    });
}

function toggle(todo: Todo) {
    router.patch(
        todosRoutes.update(todo.id).url,
        { completed: !todo.completed_at },
        { preserveScroll: true },
    );
}

function setPriority(todo: Todo, priority: Priority) {
    router.patch(todosRoutes.update(todo.id).url, { priority }, { preserveScroll: true });
}

function remove(todo: Todo) {
    router.delete(todosRoutes.destroy(todo.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Todos" />

    <AppLayout :breadcrumbs="[{ title: 'Todos', href: todosRoutes.index().url }]">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-6">
            <h1 class="text-2xl font-bold">My todos</h1>

            <form class="space-y-2 rounded-lg border p-3" @submit.prevent="add">
                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        v-model="form.title"
                        placeholder="What needs doing?"
                        required
                        class="min-w-[12rem] flex-1"
                    />
                    <select
                        v-model="form.priority"
                        class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        title="Priority"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                    <select
                        v-model="form.category"
                        class="rounded-md border border-input bg-background px-2 py-1 text-sm"
                        title="Category"
                    >
                        <option value="">No category</option>
                        <option v-for="c in categories" :key="c.value" :value="c.value">
                            {{ c.label }}
                        </option>
                    </select>
                    <Input v-model="form.due_date" type="date" class="w-[10rem]" title="Due date" />
                    <Button type="submit" :disabled="form.processing">
                        <Plus class="h-4 w-4" />
                    </Button>
                </div>
                <textarea
                    v-model="form.description"
                    rows="2"
                    placeholder="Optional details (notes, context, links)…"
                    class="block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                ></textarea>
            </form>

            <div
                v-if="sorted.length === 0"
                class="rounded-lg border px-4 py-8 text-center text-sm text-muted-foreground"
            >
                Nothing to do — well done.
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="todo in sorted"
                    :key="todo.id"
                    class="group rounded-lg border border-l-4 bg-card p-3 transition hover:border-primary"
                    :class="[PRIORITY_ACCENT[todo.priority], { 'opacity-60': todo.completed_at }]"
                >
                    <div class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            :checked="todo.completed_at !== null"
                            class="mt-1 h-4 w-4 shrink-0"
                            @change="toggle(todo)"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <Flame class="h-3.5 w-3.5 shrink-0" :class="PRIORITY_META[todo.priority].flame" />
                                <p
                                    class="text-sm font-medium leading-snug"
                                    :class="{ 'line-through text-muted-foreground': todo.completed_at }"
                                >
                                    {{ todo.title }}
                                </p>
                            </div>
                            <p
                                v-if="todo.description"
                                class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{ todo.description }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
                                <span
                                    v-if="todo.category"
                                    class="rounded-full px-2 py-0.5 text-[10px] capitalize"
                                    :class="categoryPillClass(
                                        categories.find((c) => c.value === todo.category)?.color ?? 'gray',
                                    )"
                                >
                                    {{ todo.category }}
                                </span>
                                <span
                                    v-if="todo.due_date"
                                    class="rounded-full px-2 py-0.5 text-[10px]"
                                    :class="
                                        isOverdue(todo)
                                            ? 'bg-red-100 font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ isOverdue(todo) ? 'Overdue · ' : 'Due ' }}{{ todo.due_date }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <select
                                :value="todo.priority"
                                class="rounded-md border border-input bg-background px-2 py-1 text-[10px] uppercase tracking-wide"
                                :class="PRIORITY_META[todo.priority].pill"
                                @change="(e) => setPriority(todo, (e.target as HTMLSelectElement).value as Priority)"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                            <button
                                title="Delete"
                                class="opacity-0 transition group-hover:opacity-100"
                                @click="remove(todo)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
