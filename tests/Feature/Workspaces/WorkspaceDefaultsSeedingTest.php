<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceDefaultsSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_workspace_has_default_statuses_and_priorities(): void
    {
        $user = User::factory()->create();
        $workspace = $user->workspaces->first();

        $this->assertGreaterThanOrEqual(3, $workspace->statuses()->count());
        $this->assertSame(4, $workspace->priorities()->count());

        $this->assertTrue($workspace->statuses()->where('is_default', true)->exists());
        $this->assertTrue($workspace->priorities()->where('is_default', true)->exists());
    }
}
