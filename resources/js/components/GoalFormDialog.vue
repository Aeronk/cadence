<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import {
    GOAL_HORIZONS,
    GOAL_STATUSES,
    GOAL_TYPES,
    type Goal,
    type GoalHorizon,
    type GoalStatus,
    type GoalType,
} from '@/lib/goals';

/**
 * One form for both creating and editing a goal. `goal` being null means create;
 * passing one loads its values and PATCHes instead, so the two paths cannot
 * offer different fields.
 */
const props = defineProps<{
    open: boolean;
    goal: Goal | null;
    parentOptions: { id: number; title: string; type: string }[];
    /** Preselected parent when creating a sub-goal from a specific goal. */
    defaultParentId?: number | null;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const form = useForm({
    type: 'goal' as GoalType,
    parent_id: null as number | null,
    title: '',
    description: '',
    horizon: 'year' as GoalHorizon,
    status: 'on_track' as GoalStatus,
    target_date: '',
    // Named `own_progress` rather than `progress`: useForm reserves `progress`
    // for upload state, so a field of that name silently never submits.
    own_progress: 0,
});

// Reloaded each time the dialog opens so a half-finished edit of one goal never
// leaks into the next one opened.
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();

        if (props.goal) {
            form.type = props.goal.type;
            form.parent_id = props.goal.parent_id;
            form.title = props.goal.title;
            form.description = props.goal.description ?? '';
            form.horizon = props.goal.horizon ?? 'year';
            form.status = props.goal.status;
            form.target_date = props.goal.target_date ?? '';
            form.own_progress = props.goal.own_progress;
        } else {
            form.reset();
            form.parent_id = props.defaultParentId ?? null;
        }
    },
);

function payload() {
    return {
        type: form.type,
        parent_id: form.parent_id,
        title: form.title,
        description: form.description,
        horizon: form.horizon,
        status: form.status,
        target_date: form.target_date || null,
        progress: form.own_progress,
    };
}

function submit() {
    const done = { preserveScroll: true, onSuccess: () => emit('update:open', false) };

    if (props.goal) {
        form.transform(payload).patch(`/goals/${props.goal.id}`, done);
    } else {
        form.transform(payload).post('/goals', done);
    }
}

// A goal may not be its own parent, nor sit under its own sub-goals. The server
// rejects it either way; hiding the options stops it being offered at all.
const availableParents = () =>
    props.parentOptions.filter((o) => o.id !== props.goal?.id);
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ goal ? 'Edit goal' : 'New goal' }}</DialogTitle>
                <DialogDescription>
                    A goal's progress is the average of the sub-goals and milestones under it.
                    Only a goal with nothing underneath uses the percentage you set here.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="goal-type">Type</Label>
                        <select
                            id="goal-type"
                            v-model="form.type"
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="t in GOAL_TYPES" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="goal-parent">Parent</Label>
                        <select
                            id="goal-parent"
                            v-model="form.parent_id"
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option :value="null">— Top level —</option>
                            <option v-for="p in availableParents()" :key="p.id" :value="p.id">
                                {{ p.title }}
                            </option>
                        </select>
                        <InputError :message="form.errors.parent_id" />
                    </div>
                </div>

                <div>
                    <Label for="goal-title">Title</Label>
                    <Input id="goal-title" v-model="form.title" required autocomplete="off" />
                    <InputError :message="form.errors.title" />
                </div>

                <div>
                    <Label for="goal-description">Description</Label>
                    <RichEditor
                        v-model="form.description"
                        placeholder="What does reaching this look like?"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="goal-horizon">Horizon</Label>
                        <select
                            id="goal-horizon"
                            v-model="form.horizon"
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="h in GOAL_HORIZONS" :key="h.value" :value="h.value">
                                {{ h.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="goal-target">Target date</Label>
                        <Input id="goal-target" v-model="form.target_date" type="date" />
                        <InputError :message="form.errors.target_date" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="goal-status">Status</Label>
                        <select
                            id="goal-status"
                            v-model="form.status"
                            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="s in GOAL_STATUSES" :key="s.value" :value="s.value">
                                {{ s.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="goal-progress">
                            Progress
                            <span class="text-xs font-normal text-muted-foreground">
                                {{ goal && !goal.is_leaf ? '(rolled up)' : '%' }}
                            </span>
                        </Label>
                        <Input
                            id="goal-progress"
                            v-model.number="form.own_progress"
                            type="number"
                            min="0"
                            max="100"
                            :disabled="!!goal && !goal.is_leaf"
                        />
                        <p v-if="goal && !goal.is_leaf" class="mt-1 text-[11px] text-muted-foreground">
                            Counted from the {{ goal.milestones_count }} milestone(s) and sub-goals below.
                        </p>
                        <InputError :message="form.errors.progress" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="emit('update:open', false)">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ goal ? 'Save' : 'Create' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
