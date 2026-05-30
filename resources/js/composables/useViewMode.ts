import { ref, watch } from 'vue';

export type ViewMode = 'cards' | 'table';

export function useViewMode(key: string, defaultMode: ViewMode = 'cards') {
    const storageKey = `viewmode:${key}`;
    let initial: ViewMode = defaultMode;
    try {
        const stored = typeof localStorage !== 'undefined' ? localStorage.getItem(storageKey) : null;
        if (stored === 'cards' || stored === 'table') initial = stored;
    } catch {
        // ignore (SSR or blocked storage)
    }
    const mode = ref<ViewMode>(initial);
    watch(mode, (v) => {
        try {
            localStorage.setItem(storageKey, v);
        } catch {
            // ignore
        }
    });
    return mode;
}
