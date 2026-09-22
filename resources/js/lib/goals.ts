/**
 * Shared shapes and presentation for goals, so the index, the row and the
 * detail page cannot drift into describing the same goal three different ways.
 */

export type GoalType = 'vision' | 'goal' | 'objective';
export type GoalStatus = 'on_track' | 'at_risk' | 'off_track';
export type GoalHorizon = 'year' | 'quarter' | 'month';

export type GoalMilestone = {
    id: number;
    title: string;
    description: string | null;
    progress: number;
    is_manual: boolean;
    due_date: string | null;
    completed_at: string | null;
    project: { id: number; title: string; url: string } | null;
};

export type Goal = {
    id: number;
    parent_id: number | null;
    type: GoalType;
    title: string;
    description: string | null;
    horizon: GoalHorizon | null;
    status: GoalStatus;
    target_date: string | null;
    progress: number;
    own_progress: number;
    is_leaf: boolean;
    overdue: boolean;
    completed_at: string | null;
    url: string;
    milestones_count: number;
    milestones: GoalMilestone[];
};

export type GoalNode = Goal & { children: GoalNode[] };

export const GOAL_TYPES: { value: GoalType; label: string }[] = [
    { value: 'vision', label: 'Vision' },
    { value: 'goal', label: 'Goal' },
    { value: 'objective', label: 'Objective' },
];

export const GOAL_HORIZONS: { value: GoalHorizon; label: string }[] = [
    { value: 'year', label: 'Year' },
    { value: 'quarter', label: 'Quarter' },
    { value: 'month', label: 'Month' },
];

export const GOAL_STATUSES: { value: GoalStatus; label: string }[] = [
    { value: 'on_track', label: 'On track' },
    { value: 'at_risk', label: 'At risk' },
    { value: 'off_track', label: 'Off track' },
];

const TYPE_BADGES: Record<GoalType, string> = {
    vision: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    goal: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    objective: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
};

const STATUS_BADGES: Record<GoalStatus, string> = {
    on_track: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    at_risk: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-300',
    off_track: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
};

/**
 * Progress bars are tinted by status rather than always using the brand colour:
 * a goal at 70% and off track should not look the same as one at 70% and fine.
 */
const STATUS_BARS: Record<GoalStatus, string> = {
    on_track: 'bg-emerald-500',
    at_risk: 'bg-amber-500',
    off_track: 'bg-rose-500',
};

export const typeBadge = (t: string): string => TYPE_BADGES[t as GoalType] ?? 'bg-muted text-foreground';

export const statusBadge = (s: string): string => STATUS_BADGES[s as GoalStatus] ?? 'bg-muted text-foreground';

export const statusLabel = (s: string): string =>
    GOAL_STATUSES.find((o) => o.value === s)?.label ?? 'On track';

export function progressBar(goal: Pick<Goal, 'status' | 'completed_at'>): string {
    if (goal.completed_at) return 'bg-primary';
    return STATUS_BARS[goal.status] ?? 'bg-primary';
}

/** Build the parent/child tree from the flat list the server sends. */
export function buildTree(goals: Goal[]): GoalNode[] {
    const map: Record<number, GoalNode> = {};
    const roots: GoalNode[] = [];

    for (const g of goals) map[g.id] = { ...g, children: [] };
    for (const g of goals) {
        const parent = g.parent_id !== null ? map[g.parent_id] : undefined;
        if (parent) parent.children.push(map[g.id]);
        else roots.push(map[g.id]);
    }

    return roots;
}

export function formatDate(iso: string | null): string {
    if (!iso) return '';
    // Parsed as a plain date: a bare YYYY-MM-DD run through the Date
    // constructor is read as UTC and can render as the day before.
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    if (!y || !m || !d) return iso;
    return new Date(y, m - 1, d).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
