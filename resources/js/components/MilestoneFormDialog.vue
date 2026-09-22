<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import RichEditor from '@/components/RichEditor.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Adds a milestone under a goal. A project is optional: a milestone that names
 * one tracks that project's tasks, and one that does not is a checkpoint the
 * goal owns outright, with progress set by hand.
 */
const props = defineProps<{
    open: boolean;
    goalId: number | null;
    goalTitle: string;
    projects: { id: number; title: string }[];
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const form = useForm({
    goal_id: null as number | null,
    project_id: null as number | null,
    title: '',
    description: '',
    due_date: '',
    // Null hands progress to the task count; a number pins it.
    manual_progress: null as number | null,
    track_manually: true,
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.reset();
        form.clearErrors();
        form.goal_id = props.goalId;
    },
);

// Without a project there are no tasks to count, so progress has to be manual.
watch(
    () => form.project_id,
    (project) => {
        if (!project) form.track_manually = true;
    },
);

function submit() {
    form
        .transform((data) => ({
            goal_id: data.goal_id,
            project_id: data.project_id,
            title: data.title,
            description: data.description,
            due_date: data.due_date || null,
            manual_progress: data.track_manually ? (data.manual_progress ?? 0) : null,
        }))
        .post('/milestones', {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
        });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>New milestone</DialogTitle>
                <DialogDescription>
                    A checkpoint under <strong>{{ goalTitle }}</strong>. The goal's progress is the
                    average of its milestones.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <Label for="ms-title">Title</Label>
                    <Input id="ms-title" v-model="form.title" required autocomplete="off" />
                    <InputError :message="form.errors.title" />
                </div>

                <div>
                    <Label for="ms-project">Project</Label>
                    <select
                        id="ms-project"
                        v-model="form.project_id"
                        class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option :value="null">— None, track this milestone by hand —</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.title }}
                        </option>
                    </select>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Attaching a project lets progress be counted from the tasks assigned to
                        this milestone.
                    </p>
                    <InputError :message="form.errors.project_id" />
                </div>

                <div>
                    <Label for="ms-description">Definition of done</Label>
                    <RichEditor v-model="form.description" placeholder="Definition of done…" min-height="6rem" />
                </div>

                <div>
                    <Label for="ms-due">Due date</Label>
                    <Input id="ms-due" v-model="form.due_date" type="date" />
                    <InputError :message="form.errors.due_date" />
                </div>

                <div class="space-y-2 rounded-lg border p-3">
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.track_manually"
                            :disabled="!form.project_id"
                            @update:model-value="(v) => (form.track_manually = !!v)"
                        />
                        Set progress by hand
                    </label>
                    <div v-if="form.track_manually" class="flex items-center gap-2">
                        <Input
                            v-model.number="form.manual_progress"
                            type="number"
                            min="0"
                            max="100"
                            placeholder="0"
                            class="w-24"
                        />
                        <span class="text-sm text-muted-foreground">%</span>
                    </div>
                    <p v-else class="text-[11px] text-muted-foreground">
                        Progress will be the share of this milestone's tasks that are complete.
                    </p>
                    <InputError :message="form.errors.manual_progress" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emit('update:open', false)">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">Add milestone</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
