<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleAlert, Clock, MailCheck, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Standalone page: the invitee may not be signed in, so it cannot use AppLayout.
 * The controller only ever renders this for the states that need a reply — a
 * valid invitation opened by the right signed-in account is accepted and
 * redirected without reaching here.
 */
const props = defineProps<{
    state: 'invalid' | 'expired' | 'accepted' | 'needs_account' | 'wrong_account';
    message?: string;
    email?: string;
    workspace_name?: string;
    role?: string;
    invited_by?: string;
    register_url?: string;
    login_url?: string;
}>();

const heading = computed(() => {
    switch (props.state) {
        case 'needs_account':
            return `Join ${props.workspace_name}`;
        case 'wrong_account':
            return 'Wrong account';
        case 'expired':
            return 'Invitation expired';
        case 'accepted':
            return 'Already accepted';
        default:
            return 'Invitation not valid';
    }
});

const icon = computed(() => {
    switch (props.state) {
        case 'needs_account':
            return UserPlus;
        case 'accepted':
            return MailCheck;
        case 'expired':
            return Clock;
        default:
            return CircleAlert;
    }
});

const tone = computed(() =>
    props.state === 'needs_account'
        ? 'bg-primary/10 text-primary'
        : 'bg-muted text-muted-foreground',
);
</script>

<template>
    <Head :title="heading" />

    <div class="flex min-h-svh items-center justify-center bg-background p-6">
        <div class="w-full max-w-md rounded-xl border bg-card p-8 shadow-sm">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                :class="tone"
            >
                <component :is="icon" class="h-6 w-6" />
            </div>

            <h1 class="mt-5 text-center text-xl font-semibold">{{ heading }}</h1>

            <div v-if="state === 'needs_account'" class="mt-3 space-y-3 text-center">
                <p class="text-sm text-muted-foreground">
                    <template v-if="invited_by">{{ invited_by }} invited</template>
                    <template v-else>You have been invited</template>
                    <strong class="text-foreground"> {{ email }}</strong>
                    to join
                    <strong class="text-foreground">{{ workspace_name }}</strong>
                    <template v-if="role"> as {{ role }}</template>.
                </p>
                <p class="text-sm text-muted-foreground">
                    Create an account with that address, or sign in if you already have
                    one — you will be added to the workspace automatically.
                </p>

                <div class="flex flex-col gap-2 pt-2">
                    <a v-if="register_url" :href="register_url" class="w-full">
                        <Button class="w-full">Create an account</Button>
                    </a>
                    <a v-if="login_url" :href="login_url" class="w-full">
                        <Button variant="outline" class="w-full">I already have an account</Button>
                    </a>
                </div>
            </div>

            <div v-else class="mt-3 space-y-4 text-center">
                <p class="text-sm text-muted-foreground">{{ message }}</p>

                <p
                    v-if="state === 'expired' || state === 'invalid'"
                    class="text-xs text-muted-foreground"
                >
                    Whoever invited you can send a fresh link from their workspace settings.
                </p>

                <div class="pt-2">
                    <Link href="/">
                        <Button variant="outline" class="w-full">Go to Cadence</Button>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
