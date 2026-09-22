<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    Link2,
    PenLine,
    RefreshCw,
    Unlink,
    Zap,
} from 'lucide-vue-next';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import integrations from '@/routes/integrations';

type CalendarSource = {
    id: number;
    name: string;
    color: string | null;
    is_primary: boolean;
    is_selected: boolean;
    is_write_target: boolean;
    is_writable: boolean;
    access_role: string | null;
    last_synced_at: string | null;
    last_error: string | null;
    push_active: boolean;
};

type Account = {
    id: number;
    provider: string;
    provider_label: string;
    display_name: string | null;
    status: string;
    last_synced_at: string | null;
    last_error: string | null;
    token_expired: boolean;
    calendars: CalendarSource[];
};

type AvailableProvider = {
    value: string;
    label: string;
    channel: string;
    /** Only a provider with its own OAuth flow can be clicked. */
    connectable: boolean;
    unavailable_reason: string | null;
};

defineProps<{
    accounts: Account[];
    available_providers: AvailableProvider[];
}>();

function connect(providerValue: string) {
    window.location.href = `/integrations/${providerValue}/connect`;
}

function disconnect(account: Account) {
    if (!confirm(`Disconnect ${account.provider_label}?`)) return;
    router.delete(integrations.destroy(account.id).url, { preserveScroll: true });
}

/** Re-read the provider's list of calendars, picking up any newly shared ones. */
function refreshCalendars(account: Account) {
    router.post(
        `/settings/integrations/${account.id}/calendars/refresh`,
        {},
        { preserveScroll: true },
    );
}

function toggleSync(source: CalendarSource) {
    router.patch(
        `/settings/calendars/${source.id}`,
        { is_selected: !source.is_selected },
        { preserveScroll: true },
    );
}

function makeWriteTarget(source: CalendarSource) {
    router.patch(
        `/settings/calendars/${source.id}`,
        { is_write_target: true },
        { preserveScroll: true },
    );
}

const formatTime = (iso: string | null) => (iso ? new Date(iso).toLocaleString() : null);
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
                <div
                    v-if="accounts.length === 0"
                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Nothing connected yet.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="account in accounts" :key="account.id" class="rounded-lg border">
                        <div class="flex items-center justify-between p-4">
                            <div class="flex items-center gap-3">
                                <div class="grid h-10 w-10 place-items-center rounded-md bg-muted">
                                    <CheckCircle2
                                        v-if="!account.token_expired && !account.last_error"
                                        class="h-5 w-5 text-green-600"
                                    />
                                    <AlertTriangle v-else class="h-5 w-5 text-orange-500" />
                                </div>
                                <div>
                                    <p class="font-medium">{{ account.provider_label }}</p>
                                    <p class="text-xs text-muted-foreground">{{ account.display_name }}</p>
                                    <p v-if="account.last_error" class="mt-1 text-xs text-orange-500">
                                        {{ account.last_error }}
                                    </p>
                                    <p v-if="account.last_synced_at" class="text-xs text-muted-foreground">
                                        Last synced {{ formatTime(account.last_synced_at) }}
                                    </p>
                                </div>
                            </div>
                            <Button variant="ghost" size="sm" @click="disconnect(account)">
                                <Unlink class="mr-2 h-4 w-4" /> Disconnect
                            </Button>
                        </div>

                        <!-- Calendars. Cadence used to read only the primary one,
                             so anything shared with this account was invisible. -->
                        <div v-if="account.calendars.length" class="border-t bg-muted/30 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    <CalendarDays class="h-3.5 w-3.5" /> Calendars
                                </h4>
                                <Button variant="ghost" size="sm" @click="refreshCalendars(account)">
                                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" /> Refresh list
                                </Button>
                            </div>

                            <div class="space-y-1">
                                <div
                                    v-for="cal in account.calendars"
                                    :key="cal.id"
                                    class="group flex items-center gap-3 rounded-md px-2 py-2 hover:bg-background"
                                >
                                    <Checkbox
                                        :model-value="cal.is_selected"
                                        :aria-label="`Sync ${cal.name}`"
                                        @update:model-value="toggleSync(cal)"
                                    />
                                    <span
                                        class="h-3 w-3 shrink-0 rounded-full border"
                                        :style="cal.color ? { backgroundColor: cal.color } : undefined"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm">
                                            {{ cal.name }}
                                            <span
                                                v-if="cal.is_primary"
                                                class="ml-1 text-[10px] uppercase tracking-wide text-muted-foreground"
                                            >
                                                primary
                                            </span>
                                        </p>
                                        <p class="text-[11px] text-muted-foreground">
                                            <span v-if="!cal.is_writable">Read-only</span>
                                            <span v-else-if="cal.is_write_target">
                                                New meetings are created here
                                            </span>
                                            <span v-else-if="cal.last_synced_at">
                                                Synced {{ formatTime(cal.last_synced_at) }}
                                            </span>
                                            <span v-else>Not synced yet</span>
                                        </p>
                                        <p v-if="cal.last_error" class="text-[11px] text-orange-500">
                                            {{ cal.last_error }}
                                        </p>
                                    </div>

                                    <span
                                        v-if="cal.push_active"
                                        class="hidden items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-800 sm:inline-flex dark:bg-emerald-900/40 dark:text-emerald-300"
                                        title="Changes arrive by push rather than waiting for the next poll"
                                    >
                                        <Zap class="h-2.5 w-2.5" /> Live
                                    </span>

                                    <span
                                        v-if="cal.is_write_target"
                                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary"
                                    >
                                        <PenLine class="h-2.5 w-2.5" /> Default
                                    </span>
                                    <button
                                        v-else-if="cal.is_writable"
                                        class="text-[11px] text-muted-foreground opacity-0 transition hover:text-foreground hover:underline group-hover:opacity-100"
                                        @click="makeWriteTarget(cal)"
                                    >
                                        Write here
                                    </button>
                                </div>
                            </div>
                        </div>
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
                        :disabled="!provider.connectable"
                        class="flex items-center justify-between gap-3 rounded-lg border p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-60"
                        :class="provider.connectable && 'hover:border-primary'"
                        @click="provider.connectable && connect(provider.value)"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ provider.label }}</p>
                            <p class="text-xs capitalize text-muted-foreground">{{ provider.channel }}</p>
                        </div>
                        <!-- Says why, rather than looking clickable and then
                             leading to a route that cannot serve it. -->
                        <span
                            v-if="provider.unavailable_reason"
                            class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            {{ provider.unavailable_reason }}
                        </span>
                        <Link2 v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                </div>
            </section>
        </div>
    </SettingsLayout>
</template>
