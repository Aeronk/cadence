<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Briefcase } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';

type Workspace = {
    id: number;
    name: string;
    slug: string;
    is_personal: boolean;
};

const page = usePage<{
    auth: {
        workspaces: Workspace[];
        currentWorkspace: Workspace | null;
    };
}>();

function switchTo(workspace: Workspace) {
    if (workspace.id === page.props.auth.currentWorkspace?.id) return;
    router.put(`/workspaces/${workspace.id}/switch`, {}, { preserveScroll: true });
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton class="w-full justify-between gap-2">
                        <span class="flex min-w-0 items-center gap-2">
                            <Briefcase class="h-4 w-4 shrink-0" />
                            <span class="truncate text-sm font-medium">
                                {{ page.props.auth.currentWorkspace?.name ?? 'No workspace' }}
                            </span>
                        </span>
                        <ChevronsUpDown class="h-4 w-4 opacity-50" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="start" class="w-64">
                    <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="w in page.props.auth.workspaces"
                        :key="w.id"
                        @select="switchTo(w)"
                        class="flex items-center justify-between"
                    >
                        <span class="flex items-center gap-2">
                            <Briefcase class="h-4 w-4" />
                            <span class="truncate">{{ w.name }}</span>
                            <span v-if="w.is_personal" class="text-xs text-muted-foreground">(personal)</span>
                        </span>
                        <Check
                            v-if="w.id === page.props.auth.currentWorkspace?.id"
                            class="h-4 w-4"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
