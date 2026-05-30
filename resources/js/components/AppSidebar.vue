<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount } from 'vue';
import {
    Activity,
    Bell,
    Briefcase,
    CalendarDays,
    CheckSquare,
    FileText,
    LayoutGrid,
    ListTodo,
    Heart,
    Plane,
    StickyNote,
    Target,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import InstallAppButton from '@/components/InstallAppButton.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar/utils';
import type { NavItem } from '@/types';

// Auto-close the mobile drawer when the user navigates to a new page.
const { isMobile, setOpenMobile } = useSidebar();
const stopNav = router.on('navigate', () => {
    if (isMobile.value) setOpenMobile(false);
});
onBeforeUnmount(() => stopNav());

// Use direct URLs instead of Wayfinder-generated helpers so the sidebar
// renders even before `npm run dev` has regenerated route modules for
// newer endpoints (calendar / clients / etc.).
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'Projects', href: '/projects', icon: FileText },
    { title: 'Tasks', href: '/tasks', icon: CheckSquare },
    { title: 'Todos', href: '/todos', icon: ListTodo },
    { title: 'Notes', href: '/notes', icon: StickyNote },
    { title: 'Meetings', href: '/meetings', icon: CalendarDays },
    { title: 'Calendar', href: '/calendar', icon: CalendarDays },
    { title: 'Trips', href: '/trips', icon: Plane },
    { title: 'Goals', href: '/goals', icon: Target },
    { title: 'Personal', href: '/personal-events', icon: Heart },
    { title: 'Clients', href: '/clients', icon: Briefcase },
    { title: 'Activity', href: '/activity', icon: Activity },
    { title: 'Notifications', href: '/notifications', icon: Bell },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <WorkspaceSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <div class="px-2">
                <InstallAppButton />
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
