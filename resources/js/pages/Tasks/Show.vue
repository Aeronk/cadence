<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Circle, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentThread from '@/components/CommentThread.vue';
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
import tasksRoutes from '@/routes/tasks';

type Task = {
    id: number;
    title: string;
    description: string | null;
    status: { name: string } | null;
    priority: { name: string } | null;
    creator: { name: string };
    assignees: { id: number; name: string }[];
    subtasks: { id: number; title: string; completed_at: string | null }[];
    due_date: string | null;
    completed_at: string | null;
};
type Comment = { id: number; body: string; user: { id: number; name: string }; created_at: string };

const props = defineProps<{ task: Task; comments: Comment[] }>();
const page = usePage<{ auth: { user: { id: number } } }>();

const editOpen = ref(false);
const editForm = useForm({
    title: props.task.title,
    description: props.task.description ?? '',
    due_date: props.task.due_date ?? '',
});

function save() {
    editForm.patch(tasksRoutes.update(props.task.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

function toggleComplete() {
    router.patch(
        tasksRoutes.update(props.task.id).url,
        { completed: !props.task.completed_at },
        { preserveScroll: true },
    );
}

function remove() {
    if (!confirm(`Delete "${props.task.title}"?`)) return;
    router.delete(tasksRoutes.destroy(props.task.id).url);
}
</script>

<template>
    <Head :title="task.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Tasks', href: tasksRoutes.index().url },
            { title: task.title, href: tasksRoutes.show(task.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <button
                            :title="task.completed_at ? 'Mark incomplete' : 'Mark complete'"
                            @click="toggleComplete"
                        >
                            <CheckCircle2
                                v-if="task.completed_at"
                                class="h-6 w-6 text-emerald-500"
                            />
                            <Circle v-else class="h-6 w-6 text-muted-foreground hover:text-foreground" />
                        </button>
                        <h1
                            class="text-2xl font-bold"
                            :class="{ 'line-through text-muted-foreground': task.completed_at }"
                        >
                            {{ task.title }}
                        </h1>
                    </div>
                    <div
                        v-if="task.description"
                        class="prose prose-sm mt-2 max-w-none text-muted-foreground dark:prose-invert"
                        v-html="task.description"
                    ></div>
                </div>

                <div class="flex shrink-0 gap-2">
                    <Button variant="outline" size="sm" @click="editOpen = true">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit
                    </Button>
                    <Button variant="outline" size="sm" class="text-destructive" @click="remove">
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Delete
                    </Button>
                </div>
            </div>

            <Dialog v-model:open="editOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit task</DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="save">
                        <div>
                            <Label for="edit-title">Title</Label>
                            <Input id="edit-title" v-model="editForm.title" required />
                        </div>
                        <div>
                            <Label>Description</Label>
                            <RichEditor v-model="editForm.description" placeholder="Details, acceptance criteria…" />
                        </div>
                        <div>
                            <Label for="edit-due">Due date</Label>
                            <Input id="edit-due" v-model="editForm.due_date" type="date" />
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Status</p>
                    <p class="mt-1 font-medium">{{ task.status?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Priority</p>
                    <p class="mt-1 font-medium">{{ task.priority?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Due</p>
                    <p class="mt-1 font-medium">{{ task.due_date ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Created by</p>
                    <p class="mt-1 font-medium">{{ task.creator.name }}</p>
                </div>
            </div>

            <div v-if="task.assignees.length" class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Assignees</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="a in task.assignees"
                        :key="a.id"
                        class="rounded-full bg-muted px-3 py-1 text-sm"
                    >
                        {{ a.name }}
                    </span>
                </div>
            </div>

            <div v-if="task.subtasks.length" class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Subtasks</h2>
                <ul class="space-y-2">
                    <li v-for="s in task.subtasks" :key="s.id" class="flex items-center gap-2">
                        <input type="checkbox" :checked="s.completed_at !== null" />
                        <span :class="{ 'line-through text-muted-foreground': s.completed_at }">{{ s.title }}</span>
                    </li>
                </ul>
            </div>

            <CommentThread
                commentable-type="task"
                :commentable-id="task.id"
                :comments="comments"
                :current-user-id="page.props.auth.user.id"
            />
        </div>
    </AppLayout>
</template>
