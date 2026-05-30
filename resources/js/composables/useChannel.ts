import { onBeforeUnmount } from 'vue';
import { getEcho } from '@/lib/echo';

/**
 * Subscribe to a Laravel Echo private channel and bind one event handler.
 * Unsubscribes automatically when the component unmounts.
 *
 * Pass the channel name without the "private-" prefix, e.g. "task.42".
 */
export function useChannel<T = unknown>(
    channel: string,
    event: string,
    handler: (payload: T) => void,
): void {
    let cleanup: (() => void) | null = null;

    getEcho().then((echo) => {
        if (!echo) return;
        const e = echo as any;
        const ch = e.private(channel);
        ch.listen(`.${event}`, handler as (...args: unknown[]) => void);
        cleanup = () => {
            ch.stopListening(`.${event}`);
            e.leave(`private-${channel}`);
        };
    });

    onBeforeUnmount(() => {
        cleanup?.();
    });
}
