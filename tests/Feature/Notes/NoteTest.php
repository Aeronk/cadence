<?php

namespace Tests\Feature\Notes;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('notes.store'), ['title' => 'Idea', 'body' => 'Sketch the flow'])
            ->assertRedirect();

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'workspace_id' => $user->currentWorkspace()->id,
            'title' => 'Idea',
        ]);
    }

    public function test_user_only_sees_own_notes(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Note::factory()->for($alice)->for($alice->currentWorkspace())->count(2)->create();
        Note::factory()->for($bob)->for($bob->currentWorkspace())->create();

        $this->actingAs($alice)
            ->get(route('notes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notes/Index')
                ->has('notes', 2)
            );
    }

    public function test_user_cannot_update_other_users_note(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = Note::factory()->for($alice)->for($alice->currentWorkspace())->create();

        $this->actingAs($bob)
            ->patch(route('notes.update', $note), ['title' => 'hacked'])
            ->assertForbidden();
    }
}
