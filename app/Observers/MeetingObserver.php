<?php

namespace App\Observers;

use App\Jobs\PushMeetingToCalendar;
use App\Models\Meeting;

class MeetingObserver
{
    public function created(Meeting $meeting): void
    {
        PushMeetingToCalendar::dispatch($meeting->id, PushMeetingToCalendar::ACTION_CREATE)
            ->afterCommit();
    }

    public function updated(Meeting $meeting): void
    {
        $relevant = ['title', 'description', 'location', 'meeting_url', 'starts_at', 'ends_at'];

        if (! collect($meeting->getChanges())->keys()->intersect($relevant)->isNotEmpty()) {
            return;
        }

        PushMeetingToCalendar::dispatch($meeting->id, PushMeetingToCalendar::ACTION_UPDATE)
            ->afterCommit();
    }

    public function deleted(Meeting $meeting): void
    {
        PushMeetingToCalendar::dispatch($meeting->id, PushMeetingToCalendar::ACTION_DELETE)
            ->afterCommit();
    }
}
