<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarPlus,
    CheckCircle2,
    Circle,
    Clock,
    Pencil,
    Repeat,
    Trash2,
    Users,
    Video,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CommentThread from '@/components/CommentThread.vue';
import RichEditor from '@/components/RichEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import tasksRoutes from '@/routes/tasks';

type Option = { value: string; label: string; color?: string };
type Named = { id: number; name: string };
type Person = { id: number; name: string; email: string };

type Meeting = {
    id: number;
    title: string;
    starts_at: string;
    ends_at: string;
    location: string | null;
    meeting_url: string | null;
    meeting_type: string;
    reminder_minutes_before: number | null;
    recurrence_rule: string | null;
    conference_requested: boolean;
    attendees: Person[];
};

type Task = {
    id: number;
    title: string;
    description: string | null;
    category: string | null;
    status: Named | null;
    priority: Named | null;
    status_id: number | null;
    priority_id: number | null;
    milestone_id: number | null;
    milestone: { id: number; title: string } | null;
    creator: { name: string };
    assignees: Named[];
    subtasks: { id: number; title: string; completed_at: string | null }[];
    start_date: string | null;
    due_date: string | null;
    recurrence_rule: string | null;
    recurrence_ends_on: string | null;
    completed_at: string | null;
    meeting_id: number | null;
    meeting: Meeting | null;
};

type Comment = { id: number; body: string; user: { id: number; name: string }; created_at: string };

const props = defineProps<{
    task: Task;
    comments: Comment[];
    categories: Option[];
    milestones_for_select: { id: number; title: string }[];
    statuses: { id: number; name: string; color: string }[];
    priorities: { id: number; name: string; color: string; level: number }[];
    assignable_users: Person[];
    recurrence_options: { value: string; label: string }[];
}>();

const page = usePage<{ auth: { user: { id: number } } }>();

const selectClass =
    'mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';

/** `YYYY-MM-DDTHH:mm`, the only shape a datetime-local input accepts. */
function toLocalInput(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const categoryLabel = computed(
    () => props.categories.find((c) => c.value === props.task.category)?.label ?? null,
);

const repeatLabel = computed(
    () => props.recurrence_options.find((o) => o.value === props.task.recurrence_rule)?.label ?? null,
);

/* ------------------------------------------------------------------ editing */

const editOpen = ref(false);
const editForm = useForm({
    title: props.task.title,
    description: props.task.description ?? '',
    category: props.task.category ?? '',
    status_id: props.task.status_id ?? '',
    priority_id: props.task.priority_id ?? '',
    milestone_id: props.task.milestone_id ?? '',
    start_date: props.task.start_date ?? '',
    due_date: props.task.due_date ?? '',
    recurrence_rule: props.task.recurrence_rule ?? '',
    recurrence_ends_on: props.task.recurrence_ends_on ?? '',
    assignee_ids: props.task.assignees.map((a) => a.id),
});

function save() {
    editForm.patch(tasksRoutes.update(props.task.id).url, {
        preserveScroll: true,
        onSuccess: () => (editOpen.value = false),
    });
}

function toggleAssignee(id: number) {
    const at = editForm.assignee_ids.indexOf(id);
    if (at === -1) editForm.assignee_ids.push(id);
    else editForm.assignee_ids.splice(at, 1);
}

/* ---------------------------------------------------------------- scheduling */

const scheduleOpen = ref(false);

/** An hour from the top of the next hour is the least surprising default. */
function defaultStart(): string {
    const start = new Date();
    start.setMinutes(0, 0, 0);
    start.setHours(start.getHours() + 1);
    return toLocalInput(start.toISOString());
}

function defaultEnd(startLocal: string): string {
    const start = new Date(startLocal || Date.now());
    start.setHours(start.getHours() + 1);
    return toLocalInput(start.toISOString());
}

const existing = props.task.meeting;
const initialStart = existing ? toLocalInput(existing.starts_at) : defaultStart();

const scheduleForm = useForm({
    starts_at: initialStart,
    ends_at: existing ? toLocalInput(existing.ends_at) : defaultEnd(initialStart),
    meeting_type: existing?.meeting_type ?? 'online',
    location: existing?.location ?? '',
    meeting_url: existing?.meeting_url ?? '',
    create_conference: existing?.conference_requested ?? false,
    reminder_minutes_before: existing?.reminder_minutes_before ?? 15,
    attendee_ids: existing
        ? existing.attendees.map((a) => a.id)
        : props.task.assignees.map((a) => a.id),
    repeat: existing ? existing.recurrence_rule !== null : Boolean(props.task.recurrence_rule),
});

/** Keep the end time an hour ahead while the user has not touched it. */
function onStartChange() {
    if (!scheduleForm.ends_at || scheduleForm.ends_at <= scheduleForm.starts_at) {
        scheduleForm.ends_at = defaultEnd(scheduleForm.starts_at);
    }
}

function toggleAttendee(id: number) {
    const at = scheduleForm.attendee_ids.indexOf(id);
    if (at === -1) scheduleForm.attendee_ids.push(id);
    else scheduleForm.attendee_ids.splice(at, 1);
}

function saveSchedule() {
    scheduleForm.post(`/tasks/${props.task.id}/schedule`, {
        preserveScroll: true,
        onSuccess: () => (scheduleOpen.value = false),
    });
}

function unschedule() {
    if (!confirm('Remove this task from the calendar? Attendees will be told it is cancelled.')) return;
    router.delete(`/tasks/${props.task.id}/schedule`, { preserveScroll: true });
}

/* ------------------------------------------------------------------- actions */

function toggleComplete() {
    router.patch(
        tasksRoutes.update(props.task.id).url,
        { completed: !props.task.completed_at },
        { preserveScroll: true },
    );
}

function remove() {
    if (!confirm(`Delete "${props.task.title}"?`)) return;
    router.delete(tasksRoutes.destroy(props.task.id).url);
}

const meetingWhen = computed(() => {
    if (!props.task.meeting) return null;
    const start = new Date(props.task.meeting.starts_at);
    const end = new Date(props.task.meeting.ends_at);
    return `${start.toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })} – ${end.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
});
</script>

<template>
    <Head :title="task.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Tasks', href: tasksRoutes.index().url },
            { title: task.title, href: tasksRoutes.show(task.id).url },
        ]"
    >
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <button
                            :title="task.completed_at ? 'Mark incomplete' : 'Mark complete'"
                            @click="toggleComplete"
                        >
                            <CheckCircle2
                                v-if="task.completed_at"
                                class="h-6 w-6 text-emerald-500"
                            />
                            <Circle v-else class="h-6 w-6 text-muted-foreground hover:text-foreground" />
                        </button>
                        <h1
                            class="text-2xl font-bold"
                            :class="{ 'line-through text-muted-foreground': task.completed_at }"
                        >
                            {{ task.title }}
                        </h1>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span
                            v-if="categoryLabel"
                            class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
                        >
                            {{ categoryLabel }}
                        </span>
                        <span
                            v-if="task.milestone"
                            class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
                        >
                            {{ task.milestone.title }}
                        </span>
                        <span
                            v-if="repeatLabel && task.recurrence_rule"
                            class="flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
                        >
                            <Repeat class="h-3 w-3" /> {{ repeatLabel }}
                        </span>
                    </div>

                    <div
                        v-if="task.description"
                        class="prose prose-sm mt-2 max-w-none text-muted-foreground dark:prose-invert"
                        v-html="task.description"
                    ></div>
                </div>

                <div class="flex shrink-0 gap-2">
                    <Button variant="outline" size="sm" @click="scheduleOpen = true">
                        <CalendarPlus class="mr-1.5 h-3.5 w-3.5" />
                        {{ task.meeting ? 'Reschedule' : 'Schedule' }}
                    </Button>
                    <Button variant="outline" size="sm" @click="editOpen = true">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit
                    </Button>
                    <Button variant="outline" size="sm" class="text-destructive" @click="remove">
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Delete
                    </Button>
                </div>
            </div>

            <!-- Scheduled as a meeting: one entry on the calendar, not two. -->
            <div v-if="task.meeting" class="rounded-lg border border-primary/30 bg-primary/5 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h2 class="flex items-center gap-1.5 font-semibold">
                            <Clock class="h-4 w-4" /> On the calendar
                        </h2>
                        <p class="text-sm">{{ meetingWhen }}</p>
                        <p v-if="task.meeting.location" class="text-sm text-muted-foreground">
                            {{ task.meeting.location }}
                        </p>
                        <a
                            v-if="task.meeting.meeting_url"
                            :href="task.meeting.meeting_url"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center gap-1.5 text-sm text-primary hover:underline"
                        >
                            <Video class="h-3.5 w-3.5" /> Join
                        </a>
                        <p
                            v-if="task.meeting.recurrence_rule"
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Repeat class="h-3 w-3" />
                            Repeats — one recurring calendar entry, not one per occurrence
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <a :href="`/meetings/${task.meeting.id}`">
                            <Button variant="outline" size="sm">Open meeting</Button>
                        </a>
                        <Button variant="outline" size="sm" class="text-destructive" @click="unschedule">
                            Remove
                        </Button>
                    </div>
                </div>

                <div v-if="task.meeting.attendees.length" class="mt-3 border-t pt-3">
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Users class="h-3.5 w-3.5" /> Attendees
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="a in task.meeting.attendees"
                            :key="a.id"
                            class="rounded-full bg-background px-3 py-1 text-sm"
                        >
                            {{ a.name }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Edit ------------------------------------------------------- -->
            <Dialog v-model:open="editOpen">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit task</DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="save">
                        <div>
                            <Label for="edit-title">Title</Label>
                            <Input id="edit-title" v-model="editForm.title" required />
                            <p v-if="editForm.errors.title" class="mt-1 text-xs text-destructive">
                                {{ editForm.errors.title }}
                            </p>
                        </div>

                        <div>
                            <Label>Description</Label>
                            <RichEditor v-model="editForm.description" placeholder="Details, acceptance criteria…" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="edit-category">Category</Label>
                                <select id="edit-category" v-model="editForm.category" :class="selectClass">
                                    <option value="">— None —</option>
                                    <option v-for="c in categories" :key="c.value" :value="c.value">
                                        {{ c.label }}
                                    </option>
                                </select>
                                <p v-if="editForm.errors.category" class="mt-1 text-xs text-destructive">
                                    {{ editForm.errors.category }}
                                </p>
                            </div>

                            <div>
                                <Label for="edit-milestone">Milestone</Label>
                                <select id="edit-milestone" v-model="editForm.milestone_id" :class="selectClass">
                                    <option value="">— None —</option>
                                    <option v-for="m in milestones_for_select" :key="m.id" :value="m.id">
                                        {{ m.title }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="edit-status">Status</Label>
                                <select id="edit-status" v-model="editForm.status_id" :class="selectClass">
                                    <option value="">— None —</option>
                                    <option v-for="s in statuses" :key="s.id" :value="s.id">
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="edit-priority">Priority</Label>
                                <select id="edit-priority" v-model="editForm.priority_id" :class="selectClass">
                                    <option value="">— None —</option>
                                    <option v-for="p in priorities" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label for="edit-start">Start date</Label>
                                <Input id="edit-start" v-model="editForm.start_date" type="date" />
                            </div>

                            <div>
                                <Label for="edit-due">Due date</Label>
                                <Input id="edit-due" v-model="editForm.due_date" type="date" />
                                <p v-if="editForm.errors.due_date" class="mt-1 text-xs text-destructive">
                                    {{ editForm.errors.due_date }}
                                </p>
                            </div>

                            <div>
                                <Label for="edit-repeat">Repeats</Label>
                                <select id="edit-repeat" v-model="editForm.recurrence_rule" :class="selectClass">
                                    <option v-for="o in recurrence_options" :key="o.value" :value="o.value">
                                        {{ o.label }}
                                    </option>
                                </select>
                            </div>

                            <div v-if="editForm.recurrence_rule">
                                <Label for="edit-repeat-until">Repeat until</Label>
                                <Input
                                    id="edit-repeat-until"
                                    v-model="editForm.recurrence_ends_on"
                                    type="date"
                                />
                            </div>
                        </div>

                        <div v-if="assignable_users.length">
                            <Label>Assignees</Label>
                            <div class="mt-2 grid max-h-40 gap-1.5 overflow-y-auto rounded-md border p-2">
                                <label
                                    v-for="u in assignable_users"
                                    :key="u.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="editForm.assignee_ids.includes(u.id)"
                                        @change="toggleAssignee(u.id)"
                                    />
                                    <span>{{ u.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ u.email }}</span>
                                </label>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="submit" :disabled="editForm.processing">Save</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Schedule --------------------------------------------------- -->
            <Dialog v-model:open="scheduleOpen">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {{ task.meeting ? 'Reschedule task' : 'Schedule task' }}
                        </DialogTitle>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="saveSchedule">
                        <p class="text-sm text-muted-foreground">
                            This puts the task on the calendar as a meeting. It appears once —
                            the task itself is not drawn separately.
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="sched-start">Starts</Label>
                                <Input
                                    id="sched-start"
                                    v-model="scheduleForm.starts_at"
                                    type="datetime-local"
                                    required
                                    @change="onStartChange"
                                />
                                <p v-if="scheduleForm.errors.starts_at" class="mt-1 text-xs text-destructive">
                                    {{ scheduleForm.errors.starts_at }}
                                </p>
                            </div>

                            <div>
                                <Label for="sched-end">Ends</Label>
                                <Input
                                    id="sched-end"
                                    v-model="scheduleForm.ends_at"
                                    type="datetime-local"
                                    required
                                />
                                <p v-if="scheduleForm.errors.ends_at" class="mt-1 text-xs text-destructive">
                                    {{ scheduleForm.errors.ends_at }}
                                </p>
                            </div>

                            <div>
                                <Label for="sched-type">Type</Label>
                                <select id="sched-type" v-model="scheduleForm.meeting_type" :class="selectClass">
                                    <option value="online">Online</option>
                                    <option value="physical">In person</option>
                                    <option value="hybrid">Hybrid</option>
                                </select>
                            </div>

                            <div>
                                <Label for="sched-reminder">Remind (minutes before)</Label>
                                <Input
                                    id="sched-reminder"
                                    v-model="scheduleForm.reminder_minutes_before"
                                    type="number"
                                    min="0"
                                />
                            </div>

                            <div v-if="scheduleForm.meeting_type !== 'online'">
                                <Label for="sched-location">Location</Label>
                                <Input id="sched-location" v-model="scheduleForm.location" />
                            </div>

                            <div v-if="scheduleForm.meeting_type !== 'physical'">
                                <Label for="sched-url">Meeting link</Label>
                                <Input
                                    id="sched-url"
                                    v-model="scheduleForm.meeting_url"
                                    placeholder="https://…"
                                />
                                <p v-if="scheduleForm.errors.meeting_url" class="mt-1 text-xs text-destructive">
                                    {{ scheduleForm.errors.meeting_url }}
                                </p>
                            </div>
                        </div>

                        <label
                            v-if="scheduleForm.meeting_type !== 'physical' && !scheduleForm.meeting_url"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input v-model="scheduleForm.create_conference" type="checkbox" />
                            <span>Create a Google Meet link</span>
                        </label>

                        <label v-if="task.recurrence_rule" class="flex items-start gap-2 text-sm">
                            <input v-model="scheduleForm.repeat" type="checkbox" class="mt-1" />
                            <span>
                                Repeat on the calendar ({{ repeatLabel }})
                                <span class="block text-xs text-muted-foreground">
                                    Creates one recurring entry rather than a separate event per occurrence.
                                </span>
                            </span>
                        </label>

                        <div v-if="assignable_users.length">
                            <Label>Who is coming</Label>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Attendees get an invitation. Leaving this empty invites the
                                task's assignees.
                            </p>
                            <div class="mt-2 grid max-h-40 gap-1.5 overflow-y-auto rounded-md border p-2">
                                <label
                                    v-for="u in assignable_users"
                                    :key="u.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="scheduleForm.attendee_ids.includes(u.id)"
                                        @change="toggleAttendee(u.id)"
                                    />
                                    <span>{{ u.name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ u.email }}</span>
                                </label>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="submit" :disabled="scheduleForm.processing">
                                {{ task.meeting ? 'Update' : 'Add to calendar' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Status</p>
                    <p class="mt-1 font-medium">{{ task.status?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Priority</p>
                    <p class="mt-1 font-medium">{{ task.priority?.name ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Due</p>
                    <p class="mt-1 font-medium">{{ task.due_date ?? '—' }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">Created by</p>
                    <p class="mt-1 font-medium">{{ task.creator.name }}</p>
                </div>
            </div>

            <div v-if="task.assignees.length" class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Assignees</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="a in task.assignees"
                        :key="a.id"
                        class="rounded-full bg-muted px-3 py-1 text-sm"
                    >
                        {{ a.name }}
                    </span>
                </div>
            </div>

            <div v-if="task.subtasks.length" class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Subtasks</h2>
                <ul class="space-y-2">
                    <li v-for="s in task.subtasks" :key="s.id" class="flex items-center gap-2">
                        <input type="checkbox" :checked="s.completed_at !== null" />
                        <span :class="{ 'line-through text-muted-foreground': s.completed_at }">{{ s.title }}</span>
                    </li>
                </ul>
            </div>

            <CommentThread
                commentable-type="task"
                :commentable-id="task.id"
                :comments="comments"
                :current-user-id="page.props.auth.user.id"
            />
        </div>
    </AppLayout>
</template>
