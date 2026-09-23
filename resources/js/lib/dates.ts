/**
 * One place that decides how a date reads.
 *
 * Before this, dates were formatted at 33 call sites with inline
 * `toLocaleString()` and rendered raw in a dozen more, so the same due date
 * appeared as "2026-11-03" on one page and "11/3/2026, 12:00:00 AM" on the
 * next.
 */

/** Anything the server sends: an ISO datetime, a bare date, or nothing. */
export type DateInput = string | Date | null | undefined;

const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Parse a value from the server into a Date, or null if there isn't one.
 *
 * A bare `YYYY-MM-DD` is deliberately not handed to `new Date()`: the spec says
 * to read it as UTC midnight, so west of Greenwich a due date renders as the
 * day before. Splitting the parts builds it in local time, where a date with no
 * time attached belongs.
 */
export function toDate(value: DateInput): Date | null {
    if (!value) return null;
    if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;

    if (DATE_ONLY.test(value)) {
        const [y, m, d] = value.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

/** Whether the value carries a time, or is only a calendar date. */
function isDateOnly(value: DateInput): boolean {
    return typeof value === 'string' && DATE_ONLY.test(value);
}

/** "3 Nov 2026" — and "3 Nov" when it is this year, which reads lighter. */
export function formatDate(value: DateInput, options: { alwaysYear?: boolean } = {}): string {
    const date = toDate(value);
    if (!date) return '';

    const sameYear = date.getFullYear() === new Date().getFullYear();

    return date.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: sameYear && !options.alwaysYear ? undefined : 'numeric',
    });
}

/** "14:30" */
export function formatTime(value: DateInput): string {
    const date = toDate(value);
    if (!date) return '';

    return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

/**
 * "3 Nov 2026, 14:30". A date with no time is never given a fake midnight —
 * "due 3 Nov, 00:00" implies a deadline nobody set.
 */
export function formatDateTime(value: DateInput): string {
    const date = toDate(value);
    if (!date) return '';
    if (isDateOnly(value)) return formatDate(value);

    return `${formatDate(value)}, ${formatTime(value)}`;
}

/** "Mon 3 Nov" — for headings where the weekday is the useful part. */
export function formatWeekday(value: DateInput): string {
    const date = toDate(value);
    if (!date) return '';

    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

/**
 * "2 – 5 Nov 2026", collapsing whatever the two ends share.
 *
 * Repeating the month and year on both sides of a three-day trip is noise.
 */
export function formatDateRange(from: DateInput, to: DateInput): string {
    const start = toDate(from);
    const end = toDate(to);

    if (!start) return formatDate(to);
    if (!end) return formatDate(from);

    const sameDay = start.toDateString() === end.toDateString();
    if (sameDay) return formatDate(from);

    const sameYear = start.getFullYear() === end.getFullYear();
    const sameMonth = sameYear && start.getMonth() === end.getMonth();

    const left = sameMonth
        ? String(start.getDate())
        : start.toLocaleDateString(undefined, {
              day: 'numeric',
              month: 'short',
              year: sameYear ? undefined : 'numeric',
          });

    return `${left} – ${formatDate(to, { alwaysYear: !sameYear })}`;
}

/**
 * "just now", "2 hours ago", "in 3 days".
 *
 * Useful for activity and sync times, where the exact moment matters less than
 * how stale it is. Falls back to an absolute date past a month, because
 * "7 months ago" is harder to act on than the date itself.
 */
export function formatRelative(value: DateInput): string {
    const date = toDate(value);
    if (!date) return '';

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 45) return 'just now';
    if (abs > 60 * 60 * 24 * 30) return formatDate(value);

    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (abs >= size) {
            return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(
                Math.round(seconds / size),
                unit,
            );
        }
    }

    return 'just now';
}

/** Midnight today, for comparing a due date without the clock interfering. */
function startOfToday(): Date {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

/** Past its date, ignoring the time of day. */
export function isOverdue(value: DateInput): boolean {
    const date = toDate(value);
    return date !== null && date < startOfToday();
}

export function isToday(value: DateInput): boolean {
    const date = toDate(value);
    return date !== null && date.toDateString() === new Date().toDateString();
}

/**
 * "Today", "Tomorrow", "Yesterday", or the date. What a due date should say
 * when it is close enough to matter.
 */
export function formatDueDate(value: DateInput): string {
    const date = toDate(value);
    if (!date) return '';

    const days = Math.round(
        (new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime() -
            startOfToday().getTime()) /
            86400000,
    );

    if (days === 0) return 'Today';
    if (days === 1) return 'Tomorrow';
    if (days === -1) return 'Yesterday';

    return formatDate(value);
}
