<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckSquare, Flag, Plus, Tag as TagIcon } from 'lucide-vue-next';
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
import tasksRoutes from '@/routes/tasks';

type Task = {
    id: number;
    title: string;
    completed_at: string | null;
    due_date: string | null;
    category: string | null;
    status: { id: number; name: string; color: string } | null;
    priority: { id: number; name: string; color: string; level?: number } | null;
    assignees: { id: number; name: string }[];
};
type CategoryOption = { value: string; label: string; color: string };

const props = defineProps<{
    tasks: Task[];
    filters: { project_id: number | null; category: string | null };
    projects_for_select: { id: number; title: string }[];
    categories: CategoryOption[];
}>();

const dialogOpen = ref(false);
const form = useForm({
    project_id: props.filters.project_id ?? props.projects_for_select[0]?.id ?? null,
    title: '',
    description: '',
    due_date: '',
    category: '' as string,
});

function filterByCategory(value: string | null) {
    const query: Record<string, string | number> = {};
    if (props.filters.project_id) query.project_id = props.filters.project_id;
    if (value) query.category = value;
    router.get('/tasks', query, { preserveState: true, preserveScroll: true });
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

function priorityClass(color: string | undefined) {
    if (!color) return 'text-zinc-400';
    return ({
        red: 'text-red-500',
        orange: 'text-orange-500',
        blue: 'text-blue-500',
        green: 'text-emerald-500',
        gray: 'text-zinc-400',
    } as Record<string, string>)[color] ?? 'text-zinc-400';
}

function submit() {
    form.post(tasksRoutes.store().url, {
        onSuccess: () => {
            form.reset('title', 'description', 'due_date', 'category');
            dialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Tasks" />

    <AppLayout :breadcrumbs="[{ title: 'Tasks', href: tasksRoutes.index().url }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Tasks</h1>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button :disabled="projects_for_select.length === 0">
                            <Plus class="mr-2 h-4 w-4" /> New task
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Create task</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <Label for="project_id">Project</Label>
                                <select
                                    id="project_id"
                                    v-model="form.project_id"
                                    required
                                    class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option v-for="p in projects_for_select" :key="p.id" :value="p.id">
                                        {{ p.title }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <Label for="title">Title</Label>
                                <Input id="title" v-model="form.title" required />
                                <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                            </div>
                            <div>
                                <Label>Description</Label>
                                <RichEditor v-model="form.description" placeholder="Details, acceptance criteria…" />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="due_date">Due date</Label>
                                    <Input id="due_date" v-model="form.due_date" type="date" />
                                </div>
                                <div>
                                    <Label for="category">Category</Label>
                                    <select
                                        id="category"
                                        v-model="form.category"
                                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="c in categories"
                                            :key="c.value"
                                            :value="c.value"
                                        >
                                            {{ c.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <p v-if="projects_for_select.length === 0" class="text-sm text-muted-foreground">
                Create a project first, then tasks can hang off it.
            </p>

            <!-- Category filter chips -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs transition"
                    :class="!filters.category ? 'border-primary bg-primary/10 text-primary' : 'border-border hover:bg-muted'"
                    @click="filterByCategory(null)"
                >
                    All
                </button>
                <button
                    v-for="c in categories"
                    :key="c.value"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs transition"
                    :class="filters.category === c.value
                        ? 'border-primary bg-primary/10 text-primary'
                        : categoryPillClass(c.color) + ' border-transparent hover:opacity-80'"
                    @click="filterByCategory(c.value)"
                >
                    {{ c.label }}
                </button>
            </div>

            <div v-if="tasks.length === 0" class="rounded-xl border border-dashed p-12 text-center">
                <CheckSquare class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No tasks yet.</p>
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="task in tasks"
                    :key="task.id"
                    :href="tasksRoutes.show(task.id).url"
                    class="group flex flex-col rounded-xl border bg-card p-4 transition hover:border-primary hover:shadow-sm"
                    :class="{ 'opacity-60': task.completed_at }"
                >
                    <div class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            :checked="task.completed_at !== null"
                            class="mt-1 h-4 w-4 shrink-0"
                            @click.stop
                        />
                        <p
                            class="min-w-0 flex-1 text-sm font-medium leading-snug group-hover:text-primary"
                            :class="{ 'line-through text-muted-foreground': task.completed_at }"
                        >
                            {{ task.title }}
                        </p>
                        <Flag
                            v-if="task.priority"
                            class="h-3.5 w-3.5 shrink-0"
                            :class="priorityClass(task.priority.color)"
                        />
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                        <div class="flex flex-wrap items-center gap-1.5 text-muted-foreground">
                            <span v-if="task.status" class="rounded-full bg-muted px-2 py-0.5">
                                {{ task.status.name }}
                            </span>
                            <span
                                v-if="task.category"
                                class="flex items-center gap-1 rounded-full px-2 py-0.5 capitalize"
                                :class="categoryPillClass(
                                    categories.find((c) => c.value === task.category)?.color ?? 'gray',
                                )"
                            >
                                <TagIcon class="h-3 w-3" />
                                {{ task.category }}
                            </span>
                        </div>
                        <div v-if="task.assignees.length" class="flex -space-x-1">
                            <span
                                v-for="a in task.assignees.slice(0, 3)"
                                :key="a.id"
                                :title="a.name"
                                class="grid h-5 w-5 place-items-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground ring-2 ring-background"
                            >
                                {{ a.name.charAt(0).toUpperCase() }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="task.due_date"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Due {{ new Date(task.due_date).toLocaleDateString() }}
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
