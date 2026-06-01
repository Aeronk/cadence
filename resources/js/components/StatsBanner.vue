<script setup lang="ts">
type Stat = {
    label: string;
    value: number | string;
    color?: 'blue' | 'emerald' | 'violet' | 'orange' | 'red' | 'pink' | 'amber' | 'sky';
};

defineProps<{ stats: Stat[] }>();

const valueColor = (color?: Stat['color']) =>
    ({
        blue: 'text-blue-600 dark:text-blue-400',
        emerald: 'text-emerald-600 dark:text-emerald-400',
        violet: 'text-violet-600 dark:text-violet-400',
        orange: 'text-orange-600 dark:text-orange-400',
        red: 'text-red-600 dark:text-red-400',
        pink: 'text-pink-600 dark:text-pink-400',
        amber: 'text-amber-600 dark:text-amber-400',
        sky: 'text-sky-600 dark:text-sky-400',
    } as Record<string, string>)[color ?? 'blue'] ?? 'text-foreground';
</script>

<template>
    <div class="grid divide-y rounded-xl border bg-card sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <div v-for="s in stats" :key="s.label" class="flex flex-col items-center justify-center px-4 py-3 text-center">
            <p class="text-2xl font-bold tabular-nums" :class="valueColor(s.color)">
                {{ s.value }}
            </p>
            <p class="text-xs text-muted-foreground">{{ s.label }}</p>
        </div>
    </div>
</template>
