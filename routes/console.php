<?php

use App\Console\Commands\GenerateDailyBriefings;
use App\Console\Commands\GenerateRecurringOccurrences;
use App\Console\Commands\RenewCalendarWatches;
use App\Console\Commands\SendDueReminders;
use App\Console\Commands\SendMeetingReminders;
use App\Console\Commands\SyncCalendars;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Meeting reminders — runs every minute; idempotent via reminder_sent_at column.
Schedule::command(SendMeetingReminders::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Recurring tasks / todos — spawn the next occurrence nightly.
Schedule::command(GenerateRecurringOccurrences::class)
    ->dailyAt('00:05')
    ->withoutOverlapping();

// Generic reminders — fire every minute, idempotent via sent_at.
Schedule::command(SendDueReminders::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Daily briefings — composed once per morning, idempotent via unique(user,workspace,date).
Schedule::command(GenerateDailyBriefings::class)
    ->dailyAt('06:30')
    ->withoutOverlapping();

// Calendar sync — the pull half of two-way sync. Push notifications are optional
// (they need a verified HTTPS domain), so polling is what guarantees events show
// up at all.
Schedule::command(SyncCalendars::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Which calendars an account owns changes rarely, so the list is re-read once a
// day rather than on every poll.
Schedule::command(SyncCalendars::class, ['--refresh-list'])
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->runInBackground();

// Push channels expire — Google caps a calendar channel at about a month, Graph
// at roughly three days. Renewing hourly means a lapse is measured in minutes
// rather than leaving push silently off until someone notices.
Schedule::command(RenewCalendarWatches::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
