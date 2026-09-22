<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MeetingInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Puts a task on the calendar by promoting it to a meeting.
 *
 * A task is not duplicated onto the calendar — the meeting it points at is the
 * single row that gets drawn, pushed to the connected provider, reminded on and
 * invited to. Scheduling twice updates the same meeting rather than making
 * another one.
 */
class TaskScheduleController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'meeting_type' => ['required', Rule::in([
                Meeting::TYPE_PHYSICAL,
                Meeting::TYPE_ONLINE,
                Meeting::TYPE_HYBRID,
            ])],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
            'create_conference' => ['nullable', 'boolean'],
            'reminder_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => ['integer', Rule::exists('users', 'id')],
            // A repeating task becomes one recurring provider event rather than
            // one event per occurrence, so the rule travels with the meeting.
            'repeat' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $attendeeIds = $this->resolveAttendees($task, (array) ($data['attendee_ids'] ?? []));
        $repeat = (bool) ($data['repeat'] ?? false);

        $attributes = [
            'workspace_id' => $task->workspace_id,
            'host_id' => $task->created_by ?? $user->id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'location' => $data['location'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'meeting_type' => $data['meeting_type'],
            'reminder_minutes_before' => $data['reminder_minutes_before'] ?? null,
            'conference_requested' => (bool) ($data['create_conference'] ?? false),
            'recurrence_rule' => $repeat ? $task->recurrence_rule : null,
            'recurrence_ends_on' => $repeat ? $task->recurrence_ends_on : null,
        ];

        $result = DB::transaction(function () use ($task, $attributes, $attendeeIds) {
            $meeting = $task->meeting;

            if ($meeting) {
                $meeting->fill($attributes)->save();
            } else {
                $meeting = Meeting::create($attributes);
                // Saved quietly: the only change is the link, and a task update
                // event here would log a second "updated task" entry.
                $task->meeting_id = $meeting->id;
                $task->saveQuietly();
            }

            $existing = $meeting->attendees()->pluck('users.id')->all();

            $meeting->attendees()->sync(
                collect($attendeeIds)
                    ->mapWithKeys(fn ($id) => [(int) $id => ['rsvp_status' => 'pending']])
                    ->all()
            );

            return [$meeting, array_values(array_diff($attendeeIds, $existing))];
        });

        [$meeting, $newAttendeeIds] = $result;

        $this->notifyAttendees($meeting, $newAttendeeIds, $user);

        ActivityLog::record(
            $task->workspace,
            $user,
            'scheduled',
            "{$user->name} scheduled \"{$task->title}\" for ".$meeting->starts_at->toDayDateTimeString(),
            $task,
        );

        return back()->with('flash.success', 'Task scheduled.');
    }

    /**
     * Take the task off the calendar. The meeting is deleted, which cancels the
     * provider event and tells the attendees.
     */
    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        if (! $task->meeting_id) {
            return back()->with('flash.error', 'That task is not scheduled.');
        }

        DB::transaction(function () use ($task) {
            $meeting = $task->meeting;

            $task->meeting_id = null;
            $task->saveQuietly();

            // Deleting the meeting is what removes the provider event, via
            // MeetingObserver — so it happens after the link is cleared.
            $meeting?->delete();
        });

        return back()->with('flash.success', 'Task removed from the calendar.');
    }

    /**
     * Attendees default to the task's assignees, so scheduling a task the team is
     * already on does not require re-picking everyone.
     *
     * @param  array<int, int|string>  $requested
     * @return array<int, int>
     */
    protected function resolveAttendees(Task $task, array $requested): array
    {
        $ids = $requested
            ? array_map('intval', $requested)
            : $task->assignees()->pluck('users.id')->all();

        // Only people who can already see the task may be added to its meeting.
        return User::query()
            ->whereIn('id', $ids)
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $task->workspace_id))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $attendeeIds
     */
    protected function notifyAttendees(Meeting $meeting, array $attendeeIds, User $actor): void
    {
        if (! $attendeeIds) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $attendeeIds)
            ->whereKeyNot($actor->id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MeetingInvited($meeting, $actor));
        }
    }
}
