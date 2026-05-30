import { onBeforeUnmount, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { getEcho } from '@/lib/echo';

export function useLiveNotifications() {
    const page = usePage<{ auth: { unreadNotificationsCount: number; user?: { id: number } } }>();
    const unreadCount = ref<number>(page.props.auth.unreadNotificationsCount ?? 0);

    let cleanup: (() => void) | null = null;
    const userId = page.props.auth.user?.id;

    if (userId) {
        getEcho().then((echo) => {
            if (!echo) return;
            const e = echo as any;
            const ch = e.private(`App.Models.User.${userId}`);
            const handler = () => {
                unreadCount.value++;
                router.reload({ only: ['auth', 'notifications'], preserveScroll: true, preserveState: true });
            };
            ch.notification(handler);
            cleanup = () => {
                e.leave(`private-App.Models.User.${userId}`);
            };
        });
    }

    onBeforeUnmount(() => {
        cleanup?.();
    });

    return { unreadCount };
}
