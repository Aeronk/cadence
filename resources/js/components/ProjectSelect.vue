<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

/**
 * Files a record under a project.
 *
 * One component for meetings, notes, to-dos and trips, so the wording and the
 * "no project" behaviour cannot drift between them. Filing is always optional:
 * a note about nothing in particular is a perfectly good note.
 */
export type ProjectOption = { id: number; title: string };

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        projects: ProjectOption[];
        id?: string;
        label?: string;
        /** Shown under the field. Omit for the generic line. */
        hint?: string;
        error?: string;
        disabled?: boolean;
        /** Bare select for inline toolbars, with no label or hint around it. */
        compact?: boolean;
    }>(),
    {
        id: 'project-select',
        label: 'Project',
        hint: undefined,
        error: undefined,
        disabled: false,
        compact: false,
    },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: number | null): void }>();

// A select posts strings; the server wants an id or a genuine null.
const selected = computed({
    get: () => (props.modelValue === null ? '' : String(props.modelValue)),
    set: (value: string) => emit('update:modelValue', value === '' ? null : Number(value)),
});

const empty = computed(() => props.projects.length === 0);
</script>

<template>
    <!-- Inline toolbars sit the control beside its siblings, so the label and
         hint would break the row; the title attribute carries the meaning. -->
    <select
        v-if="compact"
        :id="id"
        v-model="selected"
        :disabled="disabled || empty"
        :title="label"
        class="rounded-md border border-input bg-background px-2 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-60"
    >
        <option value="">No project</option>
        <option v-for="p in projects" :key="p.id" :value="String(p.id)">
            {{ p.title }}
        </option>
    </select>

    <div v-else>
        <Label :for="id">{{ label }}</Label>
        <select
            :id="id"
            v-model="selected"
            :disabled="disabled || empty"
            class="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
        >
            <option value="">— No project —</option>
            <option v-for="p in projects" :key="p.id" :value="String(p.id)">
                {{ p.title }}
            </option>
        </select>
        <p v-if="empty" class="mt-1 text-[11px] text-muted-foreground">
            No projects in this workspace yet.
        </p>
        <p v-else-if="hint" class="mt-1 text-[11px] text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>
