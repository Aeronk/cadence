<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import todosRoutes from '@/routes/todos';

type Todo = {
    id: number;
    title: string;
    priority: string;
    due_date: string | null;
    completed_at: string | null;
};

defineProps<{ todos: Todo[] }>();

const form = useForm({ title: '', priority: 'medium' });

function add() {
    form.post(todosRoutes.store().url, { onSuccess: () => form.reset('title') });
}

function toggle(todo: Todo) {
    router.patch(todosRoutes.update(todo.id).url, { completed: !todo.completed_at }, { preserveScroll: true });
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

            <form class="flex gap-2" @submit.prevent="add">
                <Input v-model="form.title" placeholder="What needs doing?" required />
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                </Button>
            </form>

            <div class="rounded-lg border">
                <div v-if="todos.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    Nothing to do — well done.
                </div>
                <div
                    v-for="todo in todos"
                    :key="todo.id"
                    class="flex items-center justify-between border-b px-4 py-3 last:border-b-0"
                >
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :checked="todo.completed_at !== null"
                            @change="toggle(todo)"
                            class="h-4 w-4"
                        />
                        <span :class="{ 'line-through text-muted-foreground': todo.completed_at }">
                            {{ todo.title }}
                        </span>
                    </label>
                    <button
                        @click="remove(todo)"
                        class="text-xs text-muted-foreground hover:text-foreground"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
