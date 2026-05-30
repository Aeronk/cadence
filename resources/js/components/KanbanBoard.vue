<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Flag, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

type Status = { id: number; name: string; color: string; position: number; is_completed: boolean };
type Priority = { id: number; name: string; color: string; level: number };
type Task = {
    id: number;
    title: string;
    status_id: number | null;
    priority: Priority | null;
    due_date: string | null;
    completed_at: string | null;
    position: number;
    assignees: { id: number; name: string }[];
};

const props = defineProps<{
    statuses: Status[];
    tasks: Task[];
    projectId?: number;
}>();

const dragging = ref<Task | null>(null);
const overStatusId = ref<number | null>(null);

const columns = computed(() =>
    [...props.statuses]
        .sort((a, b) => a.position - b.position)
        .map((status) => ({
            ...status,
            tasks: props.tasks
                .filter((t) => t.status_id === status.id)
                .sort((a, b) => a.position - b.position),
        })),
);

function onDragStart(task: Task) {
    dragging.value = task;
}

function onDragOver(statusId: number, e: DragEvent) {
    e.preventDefault();
    overStatusId.value = statusId;
}

function onDrop(statusId: number) {
    if (!dragging.value) return;
    if (dragging.value.status_id === statusId) {
        dragging.value = null;
        overStatusId.value = null;
        return;
    }
    const id = dragging.value.id;
    dragging.value = null;
    overStatusId.value = null;

    router.patch(`/tasks/${id}`, { status_id: statusId }, { preserveScroll: true, preserveState: true });
}

function priorityClass(p: Priority | null) {
    if (!p) return '';
    return ({
        red: 'text-red-500',
        orange: 'text-orange-500',
        blue: 'text-blue-500',
        gray: 'text-zinc-400',
        green: 'text-emerald-500',
    } as Record<string, string>)[p.color] ?? 'text-zinc-400';
}

// Inline "Add task" per column
const addingForStatus = ref<number | null>(null);
const addForm = useForm({
    project_id: props.projectId ?? 0,
    status_id: 0 as number,
    title: '',
});

function startAdd(statusId: number) {
    addingForStatus.value = statusId;
    addForm.status_id = statusId;
    addForm.title = '';
}

function cancelAdd() {
    addingForStatus.value = null;
    addForm.reset();
}

function submitAdd(e?: Event) {
    e?.preventDefault();
    if (!props.projectId || !addForm.title.trim()) return;
    addForm.post('/tasks', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => cancelAdd(),
    });
}
</script>

<template>
    <div class="flex gap-4 overflow-x-auto pb-4">
        <div
            v-for="col in columns"
            :key="col.id"
            class="flex w-72 shrink-0 flex-col rounded-lg bg-muted/40"
            :class="{ 'ring-2 ring-primary': overStatusId === col.id }"
            @dragover="onDragOver(col.id, $event)"
            @drop="onDrop(col.id)"
        >
            <header class="flex items-center justify-between px-3 py-2">
                <div class="flex items-center gap-2">
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="{
                            'bg-zinc-400': col.color === 'gray',
                            'bg-slate-400': col.color === 'slate',
                            'bg-blue-500': col.color === 'blue',
                            'bg-green-500': col.color === 'green',
                            'bg-orange-500': col.color === 'orange',
                            'bg-red-500': col.color === 'red',
                        }"
                    />
                    <span class="text-sm font-semibold">{{ col.name }}</span>
                    <span class="text-xs text-muted-foreground">{{ col.tasks.length }}</span>
                </div>
                <button
                    v-if="projectId"
                    title="Add task to this column"
                    class="rounded p-1 text-muted-foreground hover:bg-background hover:text-foreground"
                    @click="startAdd(col.id)"
                >
                    <Plus class="h-4 w-4" />
                </button>
            </header>

            <div class="flex flex-col gap-2 px-2 pb-2">
                <Link
                    v-for="task in col.tasks"
                    :key="task.id"
                    :href="`/tasks/${task.id}`"
                    draggable="true"
                    class="cursor-grab rounded-md border bg-card p-3 shadow-sm transition hover:border-primary"
                    :class="{ 'opacity-40': dragging?.id === task.id, 'opacity-60': task.completed_at }"
                    @dragstart="onDragStart(task)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p
                            class="text-sm leading-snug"
                            :class="{ 'line-through text-muted-foreground': task.completed_at }"
                        >
                            {{ task.title }}
                        </p>
                        <Flag
                            v-if="task.priority"
                            class="h-3.5 w-3.5 shrink-0"
                            :class="priorityClass(task.priority)"
                        />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span v-if="task.due_date">{{ task.due_date }}</span>
                        <div v-if="task.assignees.length" class="flex -space-x-1">
                            <span
                                v-for="a in task.assignees.slice(0, 3)"
                                :key="a.id"
                                class="grid h-5 w-5 place-items-center rounded-full bg-primary text-[10px] font-medium text-primary-foreground ring-2 ring-background"
                                :title="a.name"
                            >
                                {{ a.name.charAt(0) }}
                            </span>
                            <span
                                v-if="task.assignees.length > 3"
                                class="grid h-5 w-5 place-items-center rounded-full bg-muted text-[10px] ring-2 ring-background"
                            >
                                +{{ task.assignees.length - 3 }}
                            </span>
                        </div>
                    </div>
                </Link>

                <form
                    v-if="addingForStatus === col.id"
                    class="space-y-2 rounded-md border bg-card p-3 shadow-sm"
                    @submit="submitAdd"
                >
                    <textarea
                        v-model="addForm.title"
                        rows="2"
                        placeholder="Task title…"
                        class="w-full rounded border border-input bg-background px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        required
                        autofocus
                        @keydown.enter.exact.prevent="submitAdd"
                        @keydown.escape="cancelAdd"
                    />
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            class="rounded p-1 text-muted-foreground hover:text-foreground"
                            @click="cancelAdd"
                        >
                            <X class="h-4 w-4" />
                        </button>
                        <Button type="submit" size="sm" :disabled="addForm.processing">
                            Add task
                        </Button>
                    </div>
                </form>

                <button
                    v-else-if="projectId"
                    type="button"
                    class="rounded-md border border-dashed py-2 text-center text-xs text-muted-foreground hover:border-primary hover:text-foreground"
                    @click="startAdd(col.id)"
                >
                    + Add task
                </button>

                <p
                    v-if="col.tasks.length === 0 && addingForStatus !== col.id && !projectId"
                    class="rounded-md border border-dashed py-4 text-center text-xs text-muted-foreground"
                >
                    Drop tasks here
                </p>
            </div>
        </div>
    </div>
</template>
