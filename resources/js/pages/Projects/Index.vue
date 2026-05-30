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

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="project in projects"
                    :key="project.id"
                    :href="projectsRoutes.show(project.id).url"
                    class="block rounded-lg border border-border p-4 transition hover:border-primary"
                >
                    <h3 class="mb-1 font-semibold">{{ project.title }}</h3>
                    <p v-if="project.description" class="line-clamp-2 text-sm text-muted-foreground">
                        {{ project.description }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span v-if="project.status" class="rounded-full bg-muted px-2 py-0.5">
                            {{ project.status.name }}
                        </span>
                        <span
                            v-for="tag in project.tags"
                            :key="tag.id"
                            class="rounded-full bg-muted px-2 py-0.5"
                        >
                            #{{ tag.name }}
                        </span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
