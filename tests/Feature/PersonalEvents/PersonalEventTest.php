<?php

namespace Tests\Feature\PersonalEvents;

use App\Models\PersonalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_personal_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('personal-events.store'), [
            'title' => 'Mom\'s birthday',
            'category' => 'birthday',
            'event_date' => '2026-04-12',
            'recurs_yearly' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('personal_events', [
            'user_id' => $user->id,
            'title' => 'Mom\'s birthday',
            'recurs_yearly' => true,
        ]);
    }

    public function test_yearly_event_surfaces_on_calendar_in_each_year(): void
    {
        $user = User::factory()->create();
        PersonalEvent::create([
            'user_id' => $user->id,
            'title' => 'Mom birthday',
            'event_date' => '2020-04-12',
            'recurs_yearly' => true,
        ]);

        $this->actingAs($user)
            ->get(route('calendar.index', ['view' => 'month', 'date' => '2027-04-01']))
            ->assertInertia(fn ($p) => $p->has('personal_events', 1));
    }

    public function test_non_recurring_event_only_surfaces_on_its_year(): void
    {
        $user = User::factory()->create();
        PersonalEvent::create([
            'user_id' => $user->id,
            'title' => 'Parent meeting',
            'event_date' => '2026-04-12',
            'recurs_yearly' => false,
        ]);

        $this->actingAs($user)
            ->get(route('calendar.index', ['view' => 'month', 'date' => '2027-04-01']))
            ->assertInertia(fn ($p) => $p->has('personal_events', 0));
    }

    public function test_user_cannot_delete_another_users_event(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $ev = PersonalEvent::create([
            'user_id' => $alice->id,
            'title' => 'x',
            'event_date' => '2026-01-01',
        ]);

        $this->actingAs($bob)
            ->delete(route('personal-events.destroy', $ev))
            ->assertForbidden();
    }
}
