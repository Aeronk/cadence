<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
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
import tasksRoutes from '@/routes/tasks';

type Task = {
    id: number;
    title: string;
    completed_at: string | null;
    due_date: string | null;
    status: { name: string } | null;
    priority: { name: string } | null;
    assignees: { id: number; name: string }[];
};

const props = defineProps<{
    tasks: Task[];
    filters: { project_id: number | null };
    projects_for_select: { id: number; title: string }[];
}>();

const dialogOpen = ref(false);
const form = useForm({
    project_id: props.filters.project_id ?? props.projects_for_select[0]?.id ?? null,
    title: '',
    description: '',
    due_date: '',
});

function submit() {
    form.post(tasksRoutes.store().url, {
        onSuccess: () => {
            form.reset('title', 'description', 'due_date');
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
                                <Label for="due_date">Due date</Label>
                                <Input id="due_date" v-model="form.due_date" type="date" />
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

            <div v-if="tasks.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                No tasks.
            </div>

            <div v-else class="rounded-lg border">
                <Link
                    v-for="task in tasks"
                    :key="task.id"
                    :href="tasksRoutes.show(task.id).url"
                    class="flex items-center justify-between border-b px-4 py-3 last:border-b-0 hover:bg-muted/50"
                >
                    <div class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :checked="task.completed_at !== null"
                            class="h-4 w-4"
                            @click.stop
                        />
                        <span :class="{ 'line-through text-muted-foreground': task.completed_at }">
                            {{ task.title }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <span v-if="task.priority">{{ task.priority.name }}</span>
                        <span v-if="task.due_date">{{ task.due_date }}</span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
