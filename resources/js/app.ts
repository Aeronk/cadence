import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { getEcho } from '@/lib/echo';
import { registerPwa } from '@/lib/registerPwa';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                // Pages already wrap themselves with <AppLayout> in their
                // own templates; declaring it as a persistent layout here
                // too would render the whole shell (sidebar + header) twice.
                return null;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Open the Echo (Reverb) WebSocket connection — no-op if VITE_REVERB_APP_KEY
// is unset, so dev runs without a Reverb server still work fine.
getEcho();

// Register PWA service worker (production) + capture install prompt.
if (import.meta.env.PROD) {
    registerPwa();
}
