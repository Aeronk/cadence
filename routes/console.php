<?php

use App\Console\Commands\GenerateRecurringOccurrences;
use App\Console\Commands\SendMeetingReminders;
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
