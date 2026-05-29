<?php

namespace Tests\Feature\Todos;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_todo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('todos.store'), ['title' => 'Buy milk', 'priority' => 'high'])
            ->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'user_id' => $user->id,
            'workspace_id' => $user->currentWorkspace()->id,
            'title' => 'Buy milk',
            'priority' => 'high',
        ]);
    }

    public function test_user_only_sees_own_todos(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Todo::factory()->for($alice)->for($alice->currentWorkspace())->count(2)->create();
        Todo::factory()->for($bob)->for($bob->currentWorkspace())->create();

        $this->actingAs($alice)
            ->get(route('todos.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Todos/Index')
                ->has('todos', 2)
            );
    }

    public function test_user_can_mark_todo_completed(): void
    {
        $user = User::factory()->create();
        $todo = Todo::factory()->for($user)->for($user->currentWorkspace())->create();

        $this->actingAs($user)
            ->patch(route('todos.update', $todo), ['completed' => true])
            ->assertRedirect();

        $this->assertNotNull($todo->fresh()->completed_at);
    }

    public function test_other_user_cannot_update_someone_elses_todo(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $todo = Todo::factory()->for($alice)->for($alice->currentWorkspace())->create();

        $this->actingAs($bob)
            ->patch(route('todos.update', $todo), ['title' => 'hacked'])
            ->assertForbidden();
    }
}
