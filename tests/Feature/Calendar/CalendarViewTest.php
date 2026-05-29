<?php

namespace Tests\Feature\Calendar;

use App\Enums\IntegrationProvider;
use App\Models\CalendarEvent;
use App\Models\IntegrationAccount;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_returns_both_internal_meetings_and_external_events(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace();

        $meeting = Meeting::factory()->for($workspace)->create([
            'host_id' => $user->id,
            'title' => 'Standup',
            'starts_at' => now()->startOfMonth()->addDays(5)->setTime(9, 0),
            'ends_at' => now()->startOfMonth()->addDays(5)->setTime(9, 15),
        ]);

        $account = IntegrationAccount::factory()
            ->provider(IntegrationProvider::Gmail)
            ->create(['user_id' => $user->id, 'workspace_id' => $workspace->id]);

        CalendarEvent::create([
            'workspace_id' => $workspace->id,
            'integration_account_id' => $account->id,
            'external_id' => 'gcal-1',
            'title' => 'External lunch',
            'starts_at' => now()->startOfMonth()->addDays(10)->setTime(12, 0),
            'ends_at' => now()->startOfMonth()->addDays(10)->setTime(13, 0),
        ]);

        $this->actingAs($user)
            ->get(route('calendar.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calendar/Index')
                ->has('events', 2)
                ->where('events.0.title', 'Standup')
                ->where('events.1.title', 'External lunch')
            );
    }

    public function test_calendar_filters_by_requested_month(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace();

        Meeting::factory()->for($workspace)->create([
            'host_id' => $user->id,
            'starts_at' => '2026-08-15 10:00:00',
            'ends_at' => '2026-08-15 11:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('calendar.index', ['month' => '2026-08']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cursor_label', 'August 2026')
                ->has('events', 1)
            );

        $this->actingAs($user)
            ->get(route('calendar.index', ['month' => '2026-09']))
            ->assertInertia(fn (Assert $page) => $page->has('events', 0));
    }
}
