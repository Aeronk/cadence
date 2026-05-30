import { onBeforeUnmount, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { getEcho } from '@/lib/echo';

/**
 * Live notification feed for the signed-in user.
 *
 * - Subscribes to the standard Laravel broadcast channel for the user
 *   ('App.Models.User.{id}') as soon as the composable is created.
 * - Exposes an `unreadCount` ref initialized from Inertia's shared props,
 *   bumped locally on each broadcast, and refreshed from the server on
 *   page navigations.
 * - Triggers a lightweight Inertia 'only' reload so the per-page
 *   notification list (if any) and the shared count stay coherent.
 */
export function useLiveNotifications() {
    const page = usePage<{ auth: { unreadNotificationsCount: number; user?: { id: number } } }>();

    const unreadCount = ref<number>(page.props.auth.unreadNotificationsCount ?? 0);

    const echo = getEcho();
    const userId = page.props.auth.user?.id;

    if (echo && userId) {
        const channel = echo.private(`App.Models.User.${userId}`);
        const handler = () => {
            unreadCount.value++;
            router.reload({ only: ['auth', 'notifications'], preserveScroll: true, preserveState: true });
        };

        // Laravel broadcasts notifications under the event 'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated'.
        channel.notification(handler);

        onBeforeUnmount(() => {
            echo.leave(`private-App.Models.User.${userId}`);
        });
    }

    return { unreadCount };
}
