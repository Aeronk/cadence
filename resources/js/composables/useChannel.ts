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
    const echo = getEcho();
    if (!echo) return;

    const ch = echo.private(channel);
    ch.listen(`.${event}`, handler as (...args: unknown[]) => void);

    onBeforeUnmount(() => {
        ch.stopListening(`.${event}`);
        echo.leave(`private-${channel}`);
    });
}
