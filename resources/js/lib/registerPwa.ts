// Service worker registration + install-prompt capture.
// `beforeinstallprompt` is stashed on the window so an in-app button can fire it later.

declare global {
    interface WindowEventMap {
        beforeinstallprompt: BeforeInstallPromptEvent;
    }
    interface Window {
        deferredInstallPrompt: BeforeInstallPromptEvent | null;
    }
    interface BeforeInstallPromptEvent extends Event {
        prompt(): Promise<void>;
        userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
    }
}

export function registerPwa() {
    if (typeof window === 'undefined') return;

    if ('serviceWorker' in navigator) {
        // Register after the page settles so we don't compete with the initial render.
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
                /* fail silently — PWA is progressive enhancement */
            });
        });
    }

    window.deferredInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('cadence:installable'));
    });

    window.addEventListener('appinstalled', () => {
        window.deferredInstallPrompt = null;
    });
}
