<script setup lang="ts">
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-vue-next';

defineProps<{
    columnKey: string;
    activeKey: string | null;
    direction: 'asc' | 'desc';
    align?: 'left' | 'right';
}>();

defineEmits<{ toggle: [string] }>();
</script>

<template>
    <th
        class="cursor-pointer select-none px-4 py-2 font-medium"
        :class="align === 'right' ? 'text-right' : 'text-left'"
        @click="$emit('toggle', columnKey)"
    >
        <span class="inline-flex items-center gap-1">
            <slot />
            <component
                :is="activeKey === columnKey ? (direction === 'asc' ? ArrowUp : ArrowDown) : ChevronsUpDown"
                class="h-3 w-3 opacity-60"
            />
        </span>
    </th>
</template>
