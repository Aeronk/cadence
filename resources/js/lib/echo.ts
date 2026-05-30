// Lazy Reverb/Echo bootstrap.
// The Echo + Pusher packages are imported dynamically so the rest of the app
// still loads if they haven't been installed yet (CI cold start, dev before
// `npm install`, etc.). No-op if VITE_REVERB_APP_KEY is unset.

type EchoInstance = unknown;

declare global {
    interface Window {
        Echo?: EchoInstance;
        Pusher?: unknown;
    }
}

let echoPromise: Promise<EchoInstance | null> | null = null;

export async function getEcho(): Promise<EchoInstance | null> {
    if (typeof window === 'undefined') return null;
    if (echoPromise) return echoPromise;

    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
    if (!key) return null;

    echoPromise = (async () => {
        try {
            const [{ default: Echo }, { default: Pusher }] = await Promise.all([
                import('laravel-echo'),
                import('pusher-js'),
            ]);

            window.Pusher = Pusher;

            const echo = new Echo({
                broadcaster: 'reverb',
                key,
                wsHost: (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? window.location.hostname,
                wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
                wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME as string | undefined) === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: '/broadcasting/auth',
            });

            window.Echo = echo;
            return echo as EchoInstance;
        } catch {
            // laravel-echo / pusher-js not installed — silently no-op.
            return null;
        }
    })();

    return echoPromise;
}
