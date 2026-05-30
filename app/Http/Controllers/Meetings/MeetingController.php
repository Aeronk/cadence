<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingRequest;
use App\Models\ActivityLog;
use App\Models\Meeting;
use App\Notifications\MeetingInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Meeting::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $meetings = Meeting::query()
            ->forWorkspace($workspace)
            ->with(['host', 'attendees', 'project'])
            ->when(! $workspace->roleFor($user)?->canManageWorkspace(), function ($q) use ($user) {
                $q->where(function ($q) use ($user) {
                    $q->where('host_id', $user->id)
                        ->orWhereHas('attendees', fn ($q) => $q->where('users.id', $user->id));
                });
            })
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Meetings/Index', ['meetings' => $meetings]);
    }

    public function show(Meeting $meeting): Response
    {
        $this->authorize('view', $meeting);

        return Inertia::render('Meetings/Show', [
            'meeting' => $meeting->load(['host', 'attendees', 'project']),
        ]);
    }

    public function store(StoreMeetingRequest $request): RedirectResponse
    {
        $this->authorize('create', Meeting::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $meeting = Meeting::create([
            'workspace_id' => $workspace->id,
            'host_id' => $user->id,
            'project_id' => $request->input('project_id'),
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'location' => $request->input('location'),
            'meeting_url' => $request->input('meeting_url'),
            'starts_at' => $request->date('starts_at'),
            'ends_at' => $request->date('ends_at'),
            'meeting_type' => $request->input('meeting_type', 'online'),
            'channel' => $request->input('channel'),
            'reminder_minutes_before' => $request->input('reminder_minutes_before'),
        ]);

        $attendeeIds = (array) $request->input('attendee_ids', []);
        if ($attendeeIds) {
            $meeting->attendees()->sync(
                collect($attendeeIds)->mapWithKeys(fn ($id) => [(int) $id => ['rsvp_status' => 'pending']])->all()
            );

            Notification::send(
                $meeting->attendees()->whereKeyNot($user->id)->get(),
                new MeetingInvited($meeting, $user)
            );
        }

        ActivityLog::record(
            $workspace,
            $user,
            'created',
            "{$user->name} scheduled meeting \"{$meeting->title}\"",
            $meeting,
        );

        return to_route('meetings.show', $meeting)->with('flash.success', 'Meeting scheduled.');
    }

    public function update(StoreMeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $meeting->fill($request->only([
            'title', 'description', 'project_id', 'location', 'meeting_url', 'starts_at', 'ends_at',
            'meeting_type', 'channel', 'reminder_minutes_before',
        ]));
        // Reset the reminder if time changed so it can fire again.
        if ($meeting->isDirty(['starts_at', 'reminder_minutes_before'])) {
            $meeting->reminder_sent_at = null;
        }
        $meeting->save();

        if ($request->has('attendee_ids')) {
            $meeting->attendees()->sync(
                collect((array) $request->input('attendee_ids', []))
                    ->mapWithKeys(fn ($id) => [(int) $id => ['rsvp_status' => 'pending']])
                    ->all()
            );
        }

        return back()->with('flash.success', 'Meeting updated.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('delete', $meeting);

        $meeting->delete();

        return to_route('meetings.index')->with('flash.success', 'Meeting cancelled.');
    }
}
