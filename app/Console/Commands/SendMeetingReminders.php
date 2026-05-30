<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Notifications\MeetingReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Notify hosts + attendees N minutes before a meeting starts.';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        // Window: meetings whose (starts_at - reminder_minutes_before) falls in [now, now + 1m).
        // Plus a 5-minute backstop for queues that ran late.
        $candidates = Meeting::query()
            ->whereNotNull('reminder_minutes_before')
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<', $now->copy()->addDays(7))
            ->with(['host', 'attendees'])
            ->get();

        foreach ($candidates as $meeting) {
            $fireAt = $meeting->starts_at->subMinutes((int) $meeting->reminder_minutes_before);

            if ($fireAt->lessThan($now->copy()->subMinutes(5))) {
                continue; // too late — skip silently
            }
            if ($fireAt->greaterThan($now)) {
                continue; // not yet
            }

            // Idempotency guard inside a row-lock so two concurrent runs don't both fire.
            DB::transaction(function () use ($meeting, &$sent) {
                /** @var Meeting $locked */
                $locked = Meeting::query()->lockForUpdate()->find($meeting->id);
                if (! $locked || $locked->reminder_sent_at !== null) {
                    return;
                }

                $recipients = $locked->attendees()->get()->push($locked->host)->unique('id');

                Notification::send($recipients, new MeetingReminder($locked));

                $locked->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            });
        }

        $this->info("Sent {$sent} meeting reminder batch(es).");

        return self::SUCCESS;
    }
}
