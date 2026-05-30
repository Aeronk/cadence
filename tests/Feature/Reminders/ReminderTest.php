<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use App\Models\User;
use App\Notifications\GenericReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_reminder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Call mom',
            'fire_at' => now()->addHour()->toDateTimeString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $user->id,
            'title' => 'Call mom',
        ]);
    }

    public function test_due_reminders_fire_within_window(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $reminder = Reminder::create([
            'user_id' => $user->id,
            'title' => 'Drink water',
            'fire_at' => now()->subMinute(),
        ]);

        $this->artisan('reminders:send');

        Notification::assertSentTo($user, GenericReminder::class);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_reminder_is_idempotent_on_repeat_runs(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Reminder::create([
            'user_id' => $user->id,
            'title' => 'Stand up',
            'fire_at' => now()->subMinute(),
        ]);

        $this->artisan('reminders:send');
        $this->artisan('reminders:send');

        Notification::assertSentToTimes($user, GenericReminder::class, 1);
    }

    public function test_user_cannot_delete_someone_elses_reminder(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $r = Reminder::create(['user_id' => $alice->id, 'title' => 'x', 'fire_at' => now()]);

        $this->actingAs($bob)
            ->delete(route('reminders.destroy', $r))
            ->assertForbidden();
    }
}
