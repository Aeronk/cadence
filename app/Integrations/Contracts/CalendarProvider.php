<?php

namespace App\Integrations\Contracts;

use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;

interface CalendarProvider
{
    public function syncEvents(IntegrationAccount $account): int;

    public function createEvent(IntegrationAccount $account, Meeting $meeting): CalendarEvent;

    public function updateEvent(IntegrationAccount $account, CalendarEvent $event, Meeting $meeting): CalendarEvent;

    public function deleteEvent(IntegrationAccount $account, CalendarEvent $event): void;

    public function watchCalendar(IntegrationAccount $account): void;
}
