<?php

namespace Tests\Feature\AI;

use App\Models\Meeting;
use App\Models\User;
use App\Services\AI\FakeProvider;
use App\Services\AI\Provider as AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtractActionItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_action_items_into_todos(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user->currentWorkspace())->create([
            'host_id' => $user->id,
            'description' => 'Discussed budget, venue, and next quarter targets.',
        ]);

        /** @var FakeProvider $fake */
        $fake = app(AIProvider::class);
        $fake->queue(implode("\n", [
            'Confirm budget cap with finance',
            '- Book venue by Friday',
            '2. Draft Q2 targets for review',
            '',
        ]));

        $this->actingAs($user)
            ->post(route('meetings.extract-action-items', $meeting))
            ->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'user_id' => $user->id,
            'title' => 'Confirm budget cap with finance',
        ]);
        $this->assertDatabaseHas('todos', ['title' => 'Book venue by Friday']);
        $this->assertDatabaseHas('todos', ['title' => 'Draft Q2 targets for review']);
    }

    public function test_non_attendee_cannot_extract(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $meeting = Meeting::factory()->for($alice->currentWorkspace())->create([
            'host_id' => $alice->id,
            'description' => 'private',
        ]);

        $this->actingAs($bob)
            ->post(route('meetings.extract-action-items', $meeting))
            ->assertForbidden();
    }
}
