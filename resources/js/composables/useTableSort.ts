import { computed, ref, type Ref } from 'vue';

export type SortDir = 'asc' | 'desc';

function getPath(obj: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>(
        (acc, key) => (acc && typeof acc === 'object' ? (acc as Record<string, unknown>)[key] : undefined),
        obj,
    );
}

export function useTableSort<T>(rows: Ref<T[]>, initialKey: string | null = null) {
    const sortKey = ref<string | null>(initialKey);
    const sortDir = ref<SortDir>('asc');

    function toggle(key: string) {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDir.value = 'asc';
        }
    }

    const sorted = computed<T[]>(() => {
        if (!sortKey.value) return rows.value;
        const key = sortKey.value;
        const dir = sortDir.value === 'asc' ? 1 : -1;
        return [...rows.value].sort((a, b) => {
            const av = getPath(a, key);
            const bv = getPath(b, key);
            if (av == null && bv == null) return 0;
            if (av == null) return 1;
            if (bv == null) return -1;
            if (typeof av === 'string' && typeof bv === 'string') return av.localeCompare(bv) * dir;
            if (av < bv) return -1 * dir;
            if (av > bv) return 1 * dir;
            return 0;
        });
    });

    return { sortKey, sortDir, toggle, sorted };
}
