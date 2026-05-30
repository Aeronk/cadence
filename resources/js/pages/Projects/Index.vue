<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, FolderOpen } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import RichEditor from '@/components/RichEditor.vue';
import projectsRoutes from '@/routes/projects';

type Project = {
    id: number;
    title: string;
    description: string | null;
    status: { id: number; name: string; color: string } | null;
    priority: { id: number; name: string; color: string } | null;
    tags: { id: number; name: string; color: string }[];
    creator: { id: number; name: string };
    created_at: string;
    due_date?: string | null;
    archived_at?: string | null;
};

const dotColor = (color: string | undefined) => {
    if (!color) return 'bg-zinc-400';
    return ({
        blue: 'bg-blue-500',
        green: 'bg-emerald-500',
        orange: 'bg-orange-500',
        red: 'bg-red-500',
        gray: 'bg-zinc-400',
        slate: 'bg-slate-400',
        purple: 'bg-violet-500',
    } as Record<string, string>)[color] ?? 'bg-zinc-400';
};

defineProps<{ projects: Project[] }>();

const dialogOpen = ref(false);
const form = useForm({ title: '', description: '' });

function submit() {
    form.post(projectsRoutes.store().url, {
        onSuccess: () => {
            form.reset();
            dialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Projects" />

    <AppLayout :breadcrumbs="[{ title: 'Projects', href: projectsRoutes.index().url }]">
        <div class="flex h-full flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Projects</h1>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" /> New project
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Create project</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <Label for="title">Title</Label>
                                <Input id="title" v-model="form.title" required />
                                <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                            </div>
                            <div>
                                <Label>Description</Label>
                                <RichEditor v-model="form.description" placeholder="What is this project about?" />
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">Create</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div v-if="projects.length === 0" class="rounded-lg border border-dashed p-12 text-center">
                <FolderOpen class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No projects yet. Create your first one.</p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="project in projects"
                    :key="project.id"
                    :href="projectsRoutes.show(project.id).url"
                    class="group flex flex-col rounded-xl border bg-card p-5 transition hover:border-primary hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-semibold leading-tight group-hover:text-primary">
                                {{ project.title }}
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Created by {{ project.creator.name }}
                            </p>
                        </div>
                        <span
                            v-if="project.archived_at"
                            class="shrink-0 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                        >
                            Archived
                        </span>
                    </div>

                    <div
                        v-if="project.description"
                        class="prose prose-sm line-clamp-2 mt-3 max-w-none text-sm text-muted-foreground dark:prose-invert"
                        v-html="project.description"
                    ></div>

                    <div class="mt-auto pt-4">
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            <span
                                v-if="project.status"
                                class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2 py-0.5"
                            >
                                <span :class="['h-1.5 w-1.5 rounded-full', dotColor(project.status.color)]" />
                                {{ project.status.name }}
                            </span>
                            <span
                                v-if="project.priority"
                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5"
                            >
                                <span :class="['h-1.5 w-1.5 rounded-full', dotColor(project.priority.color)]" />
                                {{ project.priority.name }}
                            </span>
                            <span
                                v-for="tag in project.tags.slice(0, 3)"
                                :key="tag.id"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-primary"
                            >
                                #{{ tag.name }}
                            </span>
                            <span
                                v-if="project.tags.length > 3"
                                class="rounded-full bg-muted px-2 py-0.5"
                            >
                                +{{ project.tags.length - 3 }}
                            </span>
                        </div>

                        <p
                            v-if="project.due_date"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Due {{ new Date(project.due_date).toLocaleDateString() }}
                        </p>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
