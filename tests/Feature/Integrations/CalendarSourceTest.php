<?php

namespace Tests\Feature\Integrations;

use App\Enums\IntegrationProvider;
use App\Integrations\IntegrationManager;
use App\Integrations\Providers\Gmail\GmailProvider;
use App\Jobs\SyncIntegrationAccountCalendar;
use App\Models\CalendarEvent;
use App\Models\CalendarSource;
use App\Models\IntegrationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Choosing which calendars Cadence reads and writes, and keeping the push
 * channels that make changes arrive promptly from quietly lapsing.
 */
class CalendarSourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected IntegrationAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        app(IntegrationManager::class)->bind(IntegrationProvider::Gmail, GmailProvider::class);

        $this->user = User::factory()->create();
        $this->account = IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create([
                'user_id' => $this->user->id,
                'workspace_id' => $this->user->currentWorkspace()->id,
            ]);
    }

    protected function source(array $attributes = []): CalendarSource
    {
        return CalendarSource::create(array_merge([
            'integration_account_id' => $this->account->id,
            'workspace_id' => $this->account->workspace_id,
            'external_id' => 'primary',
            'name' => 'Personal',
            'access_role' => 'owner',
            'is_selected' => true,
        ], $attributes));
    }

    public function test_the_integrations_page_lists_the_calendars_on_an_account(): void
    {
        $this->source(['is_primary' => true, 'is_write_target' => true]);
        $this->source(['external_id' => 'team@example.com', 'name' => 'Team', 'access_role' => 'reader']);

        $this->actingAs($this->user)
            ->get(route('integrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Integrations')
                ->has('accounts.0.calendars', 2)
                ->where('accounts.0.calendars.0.is_write_target', true)
                ->where('accounts.0.calendars.1.is_writable', false));
    }

    public function test_a_calendar_can_be_switched_off_and_on(): void
    {
        Bus::fake();
        $source = $this->source();

        $this->actingAs($this->user)
            ->patch(route('integrations.calendars.update', $source), ['is_selected' => false])
            ->assertRedirect();

        $this->assertFalse($source->fresh()->is_selected);
        // Nothing to pull, so nothing is queued.
        Bus::assertNotDispatched(SyncIntegrationAccountCalendar::class);

        $this->actingAs($this->user)
            ->patch(route('integrations.calendars.update', $source), ['is_selected' => true])
            ->assertRedirect();

        // A calendar just switched on has no events yet; waiting a quarter of an
        // hour for the poll makes the toggle look broken.
        Bus::assertDispatched(SyncIntegrationAccountCalendar::class);
    }

    public function test_switching_a_calendar_off_takes_its_events_off_the_calendar_page(): void
    {
        $source = $this->source();

        CalendarEvent::create([
            'workspace_id' => $this->account->workspace_id,
            'integration_account_id' => $this->account->id,
            'calendar_source_id' => $source->id,
            'external_id' => 'evt-hidden',
            'title' => 'Book club',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'events',
                fn ($events) => collect($events)->contains(fn ($e) => $e['title'] === 'Book club'),
            ));

        $source->forceFill(['is_selected' => false])->save();

        // Unticking it has to mean the events go, not merely that no new ones
        // arrive.
        $this->actingAs($this->user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'events',
                fn ($events) => ! collect($events)->contains(fn ($e) => $e['title'] === 'Book club'),
            ));
    }

    public function test_choosing_a_write_target_moves_it_off_the_previous_one(): void
    {
        $first = $this->source(['is_write_target' => true]);
        $second = $this->source(['external_id' => 'work@example.com', 'name' => 'Work', 'access_role' => 'writer']);

        $this->actingAs($this->user)
            ->patch(route('integrations.calendars.update', $second), ['is_write_target' => true])
            ->assertRedirect();

        // Two write targets would leave a push having to guess.
        $this->assertFalse($first->fresh()->is_write_target);
        $this->assertTrue($second->fresh()->is_write_target);
        // Meetings must land somewhere Cadence also reads back.
        $this->assertTrue($second->fresh()->is_selected);
    }

    public function test_a_read_only_calendar_cannot_be_made_the_write_target(): void
    {
        $readOnly = $this->source([
            'external_id' => 'holidays@example.com',
            'name' => 'Holidays',
            'access_role' => 'reader',
        ]);

        $this->actingAs($this->user)
            ->patch(route('integrations.calendars.update', $readOnly), ['is_write_target' => true])
            ->assertRedirect();

        // Better to say so than to let every push fail with a provider error.
        $this->assertFalse($readOnly->fresh()->is_write_target);
    }

    public function test_another_user_cannot_touch_your_calendars(): void
    {
        $source = $this->source();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->patch(route('integrations.calendars.update', $source), ['is_selected' => false])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('integrations.calendars.refresh', $this->account))
            ->assertForbidden();
    }

    public function test_refreshing_re_reads_the_list_from_the_provider(): void
    {
        Http::fake([
            'googleapis.com/calendar/v3/users/me/calendarList*' => Http::response([
                'items' => [
                    ['id' => 'primary', 'summary' => 'Personal', 'accessRole' => 'owner', 'primary' => true, 'selected' => true],
                    ['id' => 'new@example.com', 'summary' => 'Newly shared', 'accessRole' => 'writer', 'selected' => true],
                ],
            ]),
        ]);

        $this->source(['is_primary' => true]);

        $this->actingAs($this->user)
            ->post(route('integrations.calendars.refresh', $this->account))
            ->assertRedirect();

        $this->assertDatabaseHas('calendar_sources', ['external_id' => 'new@example.com', 'name' => 'Newly shared']);
    }

    public function test_the_renewal_command_registers_a_channel_that_has_never_been_set_up(): void
    {
        config([
            'integrations.google.calendar_push_enabled' => true,
            'integrations.google.calendar_webhook_url' => 'https://example.test/hook',
        ]);

        Http::fake([
            'googleapis.com/calendar/v3/calendars/primary/events/watch*' => Http::response([
                'resourceId' => 'res-1',
                'expiration' => (string) (now()->addDays(7)->getTimestamp() * 1000),
            ]),
        ]);

        // watchCalendar existed but nothing ever called it, so push notifications
        // were never registered and the webhook could not fire.
        $source = $this->source();
        $this->assertNull($source->watch_expires_at);

        $this->artisan('integrations:renew-calendar-watches')->assertSuccessful();

        $this->assertNotNull($source->fresh()->watch_expires_at);
        $this->assertNotNull($source->fresh()->watch_channel_token_hash);
    }

    public function test_the_renewal_command_leaves_a_healthy_channel_alone(): void
    {
        config(['integrations.google.calendar_push_enabled' => true]);

        Http::fake();

        $this->source(['external_id' => 'primary'])
            ->forceFill(['watch_expires_at' => now()->addDays(20)])
            ->save();

        $this->artisan('integrations:renew-calendar-watches')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_channel_close_to_expiry_is_renewed_before_it_lapses(): void
    {
        config([
            'integrations.google.calendar_push_enabled' => true,
            'integrations.google.calendar_webhook_url' => 'https://example.test/hook',
        ]);

        Http::fake([
            'googleapis.com/calendar/v3/calendars/primary/events/watch*' => Http::response([
                'resourceId' => 'res-2',
                'expiration' => (string) (now()->addDays(7)->getTimestamp() * 1000),
            ]),
        ]);

        // Waiting for it to actually lapse leaves a window where changes arrive
        // nowhere at all.
        $source = $this->source();
        $source->forceFill(['watch_expires_at' => now()->addHours(2)])->save();

        $this->artisan('integrations:renew-calendar-watches')->assertSuccessful();

        $this->assertTrue($source->fresh()->watch_expires_at->isAfter(now()->addDays(6)));
    }

    public function test_one_calendar_failing_to_renew_does_not_stop_the_rest(): void
    {
        config([
            'integrations.google.calendar_push_enabled' => true,
            'integrations.google.calendar_webhook_url' => 'https://example.test/hook',
        ]);

        Http::fake([
            'googleapis.com/calendar/v3/calendars/revoked%40example.com/events/watch*' => Http::response(
                ['error' => ['code' => 403]],
                403,
            ),
            'googleapis.com/calendar/v3/calendars/primary/events/watch*' => Http::response([
                'resourceId' => 'res-3',
                'expiration' => (string) (now()->addDays(7)->getTimestamp() * 1000),
            ]),
        ]);

        $revoked = $this->source(['external_id' => 'revoked@example.com', 'name' => 'Revoked']);
        $healthy = $this->source();

        $this->artisan('integrations:renew-calendar-watches')->assertFailed();

        $this->assertNotNull($revoked->fresh()->last_error);
        $this->assertNotNull($healthy->fresh()->watch_expires_at);
    }

    public function test_unselected_calendars_are_not_watched(): void
    {
        config(['integrations.google.calendar_push_enabled' => true]);

        Http::fake();

        $this->source(['is_selected' => false]);

        $this->artisan('integrations:renew-calendar-watches')->assertSuccessful();

        // No point paying for a channel on a calendar nobody is reading.
        Http::assertNothingSent();
    }
}
