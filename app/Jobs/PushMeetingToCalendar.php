<?php

namespace App\Jobs;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushMeetingToCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_DELETE = 'delete';

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $meetingId,
        public string $action,
    ) {}

    public function handle(IntegrationManager $manager): void
    {
        $meeting = Meeting::withTrashed()->find($this->meetingId);
        if (! $meeting) {
            return;
        }

        $account = IntegrationAccount::query()
            ->where('user_id', $meeting->host_id)
            ->where('status', 'active')
            ->whereIn('provider', [IntegrationProvider::Gmail->value, IntegrationProvider::Microsoft->value])
            ->first();

        if (! $account) {
            return;
        }

        $provider = $manager->calendar($account);

        $existing = CalendarEvent::query()
            ->where('integration_account_id', $account->id)
            ->where('meeting_id', $meeting->id)
            ->first();

        match ($this->action) {
            self::ACTION_CREATE => $existing
                ? $provider->updateEvent($account, $existing, $meeting)
                : $provider->createEvent($account, $meeting),
            self::ACTION_UPDATE => $existing
                ? $provider->updateEvent($account, $existing, $meeting)
                : $provider->createEvent($account, $meeting),
            self::ACTION_DELETE => $existing ? $provider->deleteEvent($account, $existing) : null,
            default => null,
        };
    }
}
