<?php

namespace Tests\Feature\Trips;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_trip(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('trips.store'), [
            'name' => 'Nairobi field visit',
            'departs_at' => now()->addWeek()->toDateTimeString(),
            'returns_at' => now()->addWeek()->addDays(4)->toDateTimeString(),
            'purpose' => 'fieldwork',
            'destination_country' => 'KE',
            'destination_city' => 'Nairobi',
        ])->assertRedirect();

        $this->assertDatabaseHas('trips', [
            'user_id' => $user->id,
            'workspace_id' => $user->currentWorkspace()->id,
            'name' => 'Nairobi field visit',
            'destination_city' => 'Nairobi',
            'status' => Trip::STATUS_PLANNED,
        ]);
    }

    public function test_user_can_add_segment_and_checklist_to_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->for($user)->for($user->currentWorkspace())->create();

        $this->actingAs($user)->post(route('trips.segments.store', $trip), [
            'type' => 'flight',
            'reference' => 'KQ 102',
            'from_location' => 'HRE',
            'to_location' => 'NBO',
            'starts_at' => now()->addWeek()->toDateTimeString(),
        ])->assertRedirect();

        $this->actingAs($user)->post(route('trips.checklist.store', $trip), [
            'title' => 'Passport',
        ])->assertRedirect();

        $this->assertSame(1, $trip->segments()->count());
        $this->assertSame(1, $trip->checklist()->count());
    }

    public function test_other_user_cannot_view_trip(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->for($owner)->for($owner->currentWorkspace())->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('trips.show', $trip))
            ->assertForbidden();
    }

    public function test_trip_dates_appear_on_calendar_overlay(): void
    {
        $user = User::factory()->create();

        Trip::factory()->for($user)->for($user->currentWorkspace())->create([
            'departs_at' => '2026-06-15 06:00:00',
            'returns_at' => '2026-06-18 23:00:00',
            'destination_city' => 'Geneva',
        ]);

        $this->actingAs($user)
            ->get(route('calendar.index', ['view' => 'month', 'date' => '2026-06-01']))
            ->assertInertia(fn ($p) => $p
                ->where('cursor_label', 'June 2026')
                ->has('travel_days', 4)
            );
    }
}
