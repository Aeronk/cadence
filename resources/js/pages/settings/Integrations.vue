<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Link2, Unlink, AlertTriangle, CheckCircle2 } from 'lucide-vue-next';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Button } from '@/components/ui/button';
import integrations from '@/routes/integrations';

type Account = {
    id: number;
    provider: string;
    provider_label: string;
    display_name: string | null;
    status: string;
    last_synced_at: string | null;
    last_error: string | null;
    token_expired: boolean;
};

type AvailableProvider = {
    value: string;
    label: string;
    channel: string;
};

defineProps<{
    accounts: Account[];
    available_providers: AvailableProvider[];
}>();

function connect(providerValue: string) {
    // OAuth/connect endpoint is added in M8 — link to it.
    window.location.href = `/integrations/${providerValue}/connect`;
}

function disconnect(account: Account) {
    if (!confirm(`Disconnect ${account.provider_label}?`)) return;
    router.delete(integrations.destroy(account.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Integrations" />

    <SettingsLayout>
        <div class="space-y-8">
            <header>
                <h2 class="text-lg font-semibold">Integrations</h2>
                <p class="text-sm text-muted-foreground">
                    Connect email, calendar and messaging to sync activity into Cadence.
                </p>
            </header>

            <section>
                <h3 class="mb-3 text-sm font-medium">Connected</h3>
                <div v-if="accounts.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Nothing connected yet.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="account in accounts"
                        :key="account.id"
                        class="flex items-center justify-between rounded-lg border p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div class="grid h-10 w-10 place-items-center rounded-md bg-muted">
                                <CheckCircle2 v-if="!account.token_expired && !account.last_error" class="h-5 w-5 text-green-600" />
                                <AlertTriangle v-else class="h-5 w-5 text-orange-500" />
                            </div>
                            <div>
                                <p class="font-medium">{{ account.provider_label }}</p>
                                <p class="text-xs text-muted-foreground">{{ account.display_name }}</p>
                                <p v-if="account.last_error" class="mt-1 text-xs text-orange-500">{{ account.last_error }}</p>
                                <p v-if="account.last_synced_at" class="text-xs text-muted-foreground">
                                    Last synced {{ new Date(account.last_synced_at).toLocaleString() }}
                                </p>
                            </div>
                        </div>
                        <Button variant="ghost" size="sm" @click="disconnect(account)">
                            <Unlink class="mr-2 h-4 w-4" /> Disconnect
                        </Button>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="mb-3 text-sm font-medium">Available</h3>
                <div class="grid gap-3 md:grid-cols-2">
                    <button
                        v-for="provider in available_providers"
                        :key="provider.value"
                        type="button"
                        @click="connect(provider.value)"
                        class="flex items-center justify-between rounded-lg border p-4 text-left hover:border-primary"
                    >
                        <div>
                            <p class="font-medium">{{ provider.label }}</p>
                            <p class="text-xs text-muted-foreground capitalize">{{ provider.channel }}</p>
                        </div>
                        <Link2 class="h-4 w-4 text-muted-foreground" />
                    </button>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>
