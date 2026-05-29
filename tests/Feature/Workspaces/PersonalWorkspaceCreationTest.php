<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalWorkspaceCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_workspace_is_created_when_user_is_created(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $this->assertCount(1, $user->workspaces);
        $workspace = $user->workspaces->first();

        $this->assertTrue($workspace->is_personal);
        $this->assertTrue($workspace->owner->is($user));
        $this->assertSame(WorkspaceRole::Owner->value, $workspace->members()->where('users.id', $user->id)->first()->pivot->role);
    }

    public function test_current_workspace_defaults_to_personal_workspace(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->currentWorkspace());
        $this->assertTrue($user->currentWorkspace()->is($user->workspaces->first()));
    }
}
