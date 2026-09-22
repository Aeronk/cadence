<?php

namespace Tests\Feature\Integrations;

use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The shape of the uniqueness key on calendar_events.
 *
 * Note what these tests cannot do: the suite runs on SQLite, and SQLite let the
 * old index be dropped happily. MySQL refused, because that index was also what
 * satisfied the foreign key on integration_account_id, and the migration failed
 * in production having already committed the earlier steps. Only running the
 * migrations against MySQL catches that class of bug — these tests pin the
 * intended outcome, not the DDL path taken to reach it.
 */
class CalendarEventUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_uniqueness_is_scoped_to_the_calendar_not_just_the_account(): void
    {
        $names = collect(Schema::getIndexes('calendar_events'))
            ->pluck('name')
            ->map(fn ($n) => strtolower((string) $n))
            ->all();

        $this->assertContains('calendar_events_calendar_unique', $names);

        // The two-column key had to go: Google hands the same event id to every
        // attendee's copy of an invitation, so two synced calendars sharing an
        // event collided and one overwrote the other.
        $this->assertNotContains('calendar_events_unique', $names);

        $index = collect(Schema::getIndexes('calendar_events'))
            ->firstWhere(fn ($i) => strtolower((string) $i['name']) === 'calendar_events_calendar_unique');

        $this->assertSame(
            ['integration_account_id', 'calendar_source_id', 'external_id'],
            $index['columns'],
        );
        $this->assertTrue($index['unique']);
    }

    public function test_the_same_event_id_can_exist_on_two_calendars(): void
    {
        $account = IntegrationAccount::factory()->create();

        $calendars = collect(['primary', 'team@example.com'])->map(
            fn (string $externalId) => CalendarSource::create([
                'integration_account_id' => $account->id,
                'workspace_id' => $account->workspace_id,
                'external_id' => $externalId,
                'name' => $externalId,
                'is_selected' => true,
            ]),
        );

        // The invitation as it appears on each calendar. Before the key changed,
        // the second of these silently replaced the first.
        foreach ($calendars as $calendar) {
            CalendarEvent::create([
                'workspace_id' => $account->workspace_id,
                'integration_account_id' => $account->id,
                'calendar_source_id' => $calendar->id,
                'external_id' => 'shared-invitation',
                'title' => 'All hands',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addHour(),
            ]);
        }

        $this->assertSame(
            2,
            CalendarEvent::where('external_id', 'shared-invitation')->count(),
        );
    }
}
