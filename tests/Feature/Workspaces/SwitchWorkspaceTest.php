<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_workspace_they_belong_to(): void
    {
        $user = User::factory()->create();
        $other = Workspace::factory()->create();
        $other->members()->attach($user, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($user)
            ->from('/dashboard')
            ->put(route('workspaces.switch', $other))
            ->assertRedirect('/dashboard');

        $this->assertSame($other->id, session(User::SESSION_WORKSPACE_KEY));
    }

    public function test_user_cannot_switch_to_workspace_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $foreign = Workspace::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->put(route('workspaces.switch', $foreign))
            ->assertSessionHasErrors('workspace');
    }

    public function test_guests_cannot_switch_workspaces(): void
    {
        $workspace = Workspace::factory()->create();

        $this->put(route('workspaces.switch', $workspace))
            ->assertRedirect(route('login'));
    }
}
