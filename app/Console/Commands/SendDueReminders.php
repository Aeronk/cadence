<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\User;
use App\Notifications\GenericReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Fire generic reminders whose fire_at falls in the [now-5m, now] window.';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        Reminder::query()
            ->whereNull('sent_at')
            ->where('fire_at', '<=', $now)
            ->where('fire_at', '>=', $now->copy()->subMinutes(5))
            ->orderBy('fire_at')
            ->limit(500)
            ->get()
            ->each(function (Reminder $r) use (&$sent) {
                DB::transaction(function () use ($r, &$sent) {
                    /** @var Reminder $locked */
                    $locked = Reminder::query()->lockForUpdate()->find($r->id);
                    if (! $locked || $locked->sent_at !== null) return;

                    $user = User::find($locked->user_id);
                    if (! $user) return;

                    Notification::send($user, new GenericReminder($locked));
                    $locked->forceFill(['sent_at' => now()])->save();
                    $sent++;
                });
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
