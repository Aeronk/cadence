<?php

namespace App\Integrations\Contracts;

use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use App\Models\Meeting;

interface CalendarProvider
{
    /**
     * Refresh the set of calendars the account can see, writing one
     * `calendar_sources` row per calendar.
     *
     * A user's choice of which calendars to sync is theirs, so an existing row's
     * `is_selected` must survive a refresh.
     *
     * @return int Number of calendars discovered.
     */
    public function syncCalendarList(IntegrationAccount $account): int;

    /**
     * Pull events for every calendar the account has selected.
     *
     * @return int Number of events written.
     */
    public function syncEvents(IntegrationAccount $account): int;

    public function createEvent(IntegrationAccount $account, Meeting $meeting): CalendarEvent;

    public function updateEvent(IntegrationAccount $account, CalendarEvent $event, Meeting $meeting): CalendarEvent;

    public function deleteEvent(IntegrationAccount $account, CalendarEvent $event): void;

    /**
     * Register (or re-register) a push notification channel for one calendar.
     *
     * Channels expire — Google caps calendar channels at about a month, Graph at
     * a few days — so this is called repeatedly rather than once at connect time.
     */
    public function watchCalendar(IntegrationAccount $account, CalendarSource $source): void;
}
