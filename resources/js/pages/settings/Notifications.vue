<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Bell, Mail, Radio } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

type Channel = 'database' | 'mail' | 'broadcast';
type Preferences = Record<string, Record<Channel, boolean>>;

const props = defineProps<{
    kinds: Record<string, string>;
    channels: Channel[];
    preferences: Preferences;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notification settings', href: '/settings/notifications' }],
    },
});

const form = useForm<{ preferences: Preferences }>({
    preferences: JSON.parse(JSON.stringify(props.preferences)),
});

const CHANNEL_META: Record<Channel, { label: string; icon: typeof Bell; help: string }> = {
    database: { label: 'In-app', icon: Bell, help: 'Bell icon + notifications page.' },
    mail: { label: 'Email', icon: Mail, help: 'Delivered to your account email.' },
    broadcast: { label: 'Real-time', icon: Radio, help: 'Push to the open browser tab via Reverb.' },
};

function save() {
    form.patch('/settings/notifications', { preserveScroll: true });
}
</script>

<template>
    <Head title="Notification preferences" />

    <div class="space-y-6 px-2 md:px-0">
        <Heading
            title="Notifications"
            description="Choose how you want to be alerted for each type of event."
        />

        <form @submit.prevent="save">
            <div class="overflow-hidden rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/40 text-left text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th
                                v-for="ch in channels"
                                :key="ch"
                                class="px-4 py-3 text-center font-medium"
                            >
                                <div class="flex flex-col items-center gap-0.5">
                                    <component :is="CHANNEL_META[ch].icon" class="h-4 w-4" />
                                    {{ CHANNEL_META[ch].label }}
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(label, kind) in kinds"
                            :key="kind"
                            class="border-b last:border-b-0"
                        >
                            <td class="px-4 py-3">{{ label }}</td>
                            <td
                                v-for="ch in channels"
                                :key="ch"
                                class="px-4 py-3 text-center"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.preferences[kind][ch]"
                                    class="h-4 w-4 accent-primary"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="border-t bg-muted/20 px-4 py-2 text-[11px] text-muted-foreground">
                    Tip: leave In-app on so you can always find a record in /notifications, then trim
                    Email or Real-time to taste.
                </p>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save preferences</Button>
                <span v-if="form.recentlySuccessful" class="text-xs text-emerald-600">
                    Saved.
                </span>
            </div>
        </form>
    </div>
</template>
