<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    Bell,
    Briefcase,
    CalendarDays,
    CheckSquare,
    FileText,
    LayoutGrid,
    ListTodo,
    StickyNote,
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
import { dashboard } from '@/routes';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import todos from '@/routes/todos';
import notes from '@/routes/notes';
import meetings from '@/routes/meetings';
import calendar from '@/routes/calendar';
import clients from '@/routes/clients';
import activity from '@/routes/activity';
import notifications from '@/routes/notifications';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Projects', href: projects.index(), icon: FileText },
    { title: 'Tasks', href: tasks.index(), icon: CheckSquare },
    { title: 'Todos', href: todos.index(), icon: ListTodo },
    { title: 'Notes', href: notes.index(), icon: StickyNote },
    { title: 'Meetings', href: meetings.index(), icon: CalendarDays },
    { title: 'Calendar', href: calendar(), icon: CalendarDays },
    { title: 'Clients', href: clients.index(), icon: Briefcase },
    { title: 'Activity', href: activity.index(), icon: Activity },
    { title: 'Notifications', href: notifications.index(), icon: Bell },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
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
