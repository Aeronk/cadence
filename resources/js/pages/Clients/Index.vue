<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Pencil, Building2, Mail, Phone, Globe } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import clientsRoutes from '@/routes/clients';

type Client = {
    id: number;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    notes: string | null;
    projects_count: number;
};

defineProps<{ clients: Client[] }>();

const dialogOpen = ref(false);
const editing = ref<Client | null>(null);

const form = useForm({
    name: '',
    company: '',
    email: '',
    phone: '',
    website: '',
    notes: '',
});

function open(client: Client | null = null) {
    editing.value = client;
    form.name = client?.name ?? '';
    form.company = client?.company ?? '';
    form.email = client?.email ?? '';
    form.phone = client?.phone ?? '';
    form.website = client?.website ?? '';
    form.notes = client?.notes ?? '';
    dialogOpen.value = true;
}

function submit() {
    if (editing.value) {
        form.patch(clientsRoutes.update(editing.value.id).url, {
            onSuccess: () => {
                dialogOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post(clientsRoutes.store().url, {
            onSuccess: () => {
                dialogOpen.value = false;
                form.reset();
            },
        });
    }
}

function remove(client: Client) {
    if (!confirm(`Remove ${client.name}?`)) return;
    router.delete(clientsRoutes.destroy(client.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Clients" />

    <AppLayout :breadcrumbs="[{ title: 'Clients', href: clientsRoutes.index().url }]">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Clients</h1>
                    <p class="text-sm text-muted-foreground">
                        People and companies your team is delivering for.
                    </p>
                </div>

                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button @click="open(null)">
                            <Plus class="mr-2 h-4 w-4" /> New client
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{{ editing ? 'Edit client' : 'Add client' }}</DialogTitle>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="form.name" required />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="company">Company</Label>
                                    <Input id="company" v-model="form.company" />
                                </div>
                                <div>
                                    <Label for="email">Email</Label>
                                    <Input id="email" v-model="form.email" type="email" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label for="phone">Phone</Label>
                                    <Input id="phone" v-model="form.phone" />
                                </div>
                                <div>
                                    <Label for="website">Website</Label>
                                    <Input id="website" v-model="form.website" placeholder="https://…" />
                                </div>
                            </div>
                            <div>
                                <Label for="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    v-model="form.notes"
                                    rows="3"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="form.processing">
                                    {{ editing ? 'Save' : 'Add' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div
                v-if="clients.length === 0"
                class="rounded-lg border border-dashed p-12 text-center"
            >
                <Building2 class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">No clients yet.</p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="client in clients"
                    :key="client.id"
                    class="group rounded-lg border p-4 transition hover:border-primary"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold">{{ client.name }}</h3>
                            <p v-if="client.company" class="text-sm text-muted-foreground">
                                {{ client.company }}
                            </p>
                        </div>
                        <div class="flex gap-1 opacity-0 transition group-hover:opacity-100">
                            <button title="Edit" @click="open(client)">
                                <Pencil class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                            </button>
                            <button title="Delete" @click="remove(client)">
                                <Trash2 class="h-4 w-4 text-muted-foreground hover:text-foreground" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1 text-xs text-muted-foreground">
                        <a
                            v-if="client.email"
                            :href="`mailto:${client.email}`"
                            class="flex items-center gap-2 hover:text-foreground"
                        >
                            <Mail class="h-3.5 w-3.5" /> {{ client.email }}
                        </a>
                        <a
                            v-if="client.phone"
                            :href="`tel:${client.phone}`"
                            class="flex items-center gap-2 hover:text-foreground"
                        >
                            <Phone class="h-3.5 w-3.5" /> {{ client.phone }}
                        </a>
                        <a
                            v-if="client.website"
                            :href="client.website"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center gap-2 hover:text-foreground"
                        >
                            <Globe class="h-3.5 w-3.5" /> {{ client.website }}
                        </a>
                    </div>

                    <div class="mt-3 border-t pt-2 text-xs text-muted-foreground">
                        {{ client.projects_count }} project{{ client.projects_count === 1 ? '' : 's' }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
