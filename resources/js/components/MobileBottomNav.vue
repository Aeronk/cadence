<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckSquare,
    LayoutGrid,
    ListTodo,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';

const items = [
    { title: 'Home', href: '/dashboard', icon: LayoutGrid },
    { title: 'Tasks', href: '/tasks', icon: CheckSquare },
    { title: 'Todos', href: '/todos', icon: ListTodo },
    { title: 'Calendar', href: '/calendar', icon: CalendarDays },
    { title: 'Briefing', href: '/briefing', icon: Sparkles },
];

const page = usePage();
const currentPath = computed(() => {
    try {
        return new URL(page.url, 'http://x').pathname;
    } catch {
        return page.url;
    }
});

const isActive = (href: string) =>
    href === '/dashboard'
        ? currentPath.value === '/' || currentPath.value === '/dashboard'
        : currentPath.value.startsWith(href);
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 flex items-stretch justify-around border-t border-border bg-background/95 backdrop-blur md:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <Link
            v-for="item in items"
            :key="item.title"
            :href="item.href"
            class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[10px]"
            :class="isActive(item.href) ? 'text-primary' : 'text-muted-foreground'"
        >
            <component :is="item.icon" class="h-5 w-5" />
            {{ item.title }}
        </Link>
    </nav>
</template>
