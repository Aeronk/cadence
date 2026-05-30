<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Crown, Trash2, UserPlus, LogOut } from 'lucide-vue-next';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RichEditor from '@/components/RichEditor.vue';
import workspaceRoutes from '@/routes/workspace';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_owner: boolean;
};

const props = defineProps<{
    workspace: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        is_personal: boolean;
        owner_id: number;
    };
    members: Member[];
    viewer_role: string;
    roles: string[];
}>();

const canManage = ['owner', 'admin'].includes(props.viewer_role);
const isOwner = props.viewer_role === 'owner';

const detailsForm = useForm({
    name: props.workspace.name,
    description: props.workspace.description ?? '',
});

const inviteForm = useForm({
    email: '',
    role: 'member',
});

function saveDetails() {
    detailsForm.patch(workspaceRoutes.update(props.workspace.id).url, {
        preserveScroll: true,
    });
}

function invite() {
    inviteForm.post(workspaceRoutes.members.invite(props.workspace.id).url, {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset('email'),
    });
}

function setRole(member: Member, role: string) {
    router.patch(
        workspaceRoutes.members.update([props.workspace.id, member.id]).url,
        { role },
        { preserveScroll: true },
    );
}

function remove(member: Member) {
    if (!confirm(`Remove ${member.name} from this workspace?`)) return;
    router.delete(
        workspaceRoutes.members.remove([props.workspace.id, member.id]).url,
        { preserveScroll: true },
    );
}

function deleteWorkspace() {
    if (!confirm(`Permanently delete "${props.workspace.name}"? This cannot be undone.`)) return;
    router.delete(workspaceRoutes.destroy(props.workspace.id).url);
}

function leaveWorkspace() {
    if (!confirm(`Leave "${props.workspace.name}"?`)) return;
    router.post(workspaceRoutes.leave(props.workspace.id).url);
}
</script>

<template>
    <Head title="Workspace settings" />

    <SettingsLayout>
        <div class="space-y-10">
            <!-- Details -->
            <section>
                <header class="mb-4">
                    <h2 class="text-lg font-semibold">Workspace</h2>
                    <p class="text-sm text-muted-foreground">
                        Name and description visible to all members.
                    </p>
                </header>

                <form class="space-y-4" @submit.prevent="saveDetails">
                    <div>
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="detailsForm.name"
                            :disabled="!canManage"
                            required
                        />
                        <p v-if="detailsForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ detailsForm.errors.name }}
                        </p>
                    </div>
                    <div>
                        <Label>Description</Label>
                        <RichEditor
                            v-model="detailsForm.description"
                            placeholder="What is this workspace for?"
                            :editable="canManage"
                        />
                    </div>
                    <div v-if="canManage" class="flex justify-end">
                        <Button type="submit" :disabled="detailsForm.processing">
                            Save changes
                        </Button>
                    </div>
                </form>
            </section>

            <!-- Members -->
            <section>
                <header class="mb-4 flex items-end justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Members ({{ members.length }})</h2>
                        <p class="text-sm text-muted-foreground">
                            Owners and admins can manage everyone.
                        </p>
                    </div>
                </header>

                <form
                    v-if="canManage"
                    class="mb-4 grid grid-cols-[1fr,auto,auto] gap-2 rounded-lg border p-3"
                    @submit.prevent="invite"
                >
                    <Input
                        v-model="inviteForm.email"
                        type="email"
                        placeholder="teammate@company.com"
                        required
                    />
                    <select
                        v-model="inviteForm.role"
                        class="rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option value="member">Member</option>
                        <option value="admin">Admin</option>
                    </select>
                    <Button type="submit" :disabled="inviteForm.processing">
                        <UserPlus class="mr-2 h-4 w-4" /> Invite
                    </Button>
                </form>
                <p v-if="inviteForm.errors.email" class="mb-2 text-xs text-red-500">
                    {{ inviteForm.errors.email }}
                </p>

                <div class="overflow-hidden rounded-lg border">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="flex items-center justify-between border-b px-4 py-3 last:border-b-0"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-9 w-9 place-items-center rounded-full bg-muted text-sm font-medium"
                            >
                                {{ member.name.charAt(0).toUpperCase() }}
                            </div>
                            <div>
                                <p class="flex items-center gap-1.5 text-sm font-medium">
                                    {{ member.name }}
                                    <Crown
                                        v-if="member.is_owner"
                                        class="h-3.5 w-3.5 text-amber-500"
                                    />
                                </p>
                                <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <select
                                v-if="canManage && !member.is_owner"
                                :value="member.role"
                                class="rounded-md border border-input bg-background px-2 py-1 text-xs"
                                @change="(e) => setRole(member, (e.target as HTMLSelectElement).value)"
                            >
                                <option value="member">Member</option>
                                <option value="admin">Admin</option>
                            </select>
                            <span v-else class="text-xs text-muted-foreground capitalize">
                                {{ member.role }}
                            </span>

                            <button
                                v-if="canManage && !member.is_owner"
                                title="Remove"
                                @click="remove(member)"
                            >
                                <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Danger zone -->
            <section v-if="!workspace.is_personal">
                <header class="mb-4">
                    <h2 class="text-lg font-semibold text-destructive">Danger zone</h2>
                </header>

                <div class="space-y-3 rounded-lg border border-destructive/40 p-4">
                    <div v-if="!isOwner" class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Leave workspace</p>
                            <p class="text-xs text-muted-foreground">
                                You'll lose access to projects and tasks here.
                            </p>
                        </div>
                        <Button variant="outline" @click="leaveWorkspace">
                            <LogOut class="mr-2 h-4 w-4" /> Leave
                        </Button>
                    </div>

                    <div v-if="isOwner" class="flex items-center justify-between">
                        <div>
                            <p class="font-medium">Delete workspace</p>
                            <p class="text-xs text-muted-foreground">
                                Removes all projects, tasks, meetings and members. Cannot be undone.
                            </p>
                        </div>
                        <Button variant="destructive" @click="deleteWorkspace">
                            <Trash2 class="mr-2 h-4 w-4" /> Delete
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>
