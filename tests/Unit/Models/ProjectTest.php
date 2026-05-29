<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_belongs_to_workspace_and_creator(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();
        $project = Project::factory()->for($workspace)->create(['created_by' => $user->id]);

        $this->assertTrue($project->workspace->is($workspace));
        $this->assertTrue($project->creator->is($user));
    }

    public function test_project_has_members(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();

        $project->members()->attach($member, ['role' => 'member']);

        $this->assertTrue($project->members()->where('users.id', $member->id)->exists());
    }

    public function test_archived_helper(): void
    {
        $project = Project::factory()->archived()->create();

        $this->assertTrue($project->isArchived());
    }

    public function test_project_can_have_tags(): void
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $tag = Tag::factory()->for($workspace)->create();

        $project->tags()->attach($tag);

        $this->assertTrue($project->tags->contains($tag));
    }

    public function test_for_workspace_scope_filters(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();
        Project::factory()->for($a)->count(2)->create();
        Project::factory()->for($b)->count(1)->create();

        $this->assertSame(2, Project::query()->forWorkspace($a)->count());
        $this->assertSame(1, Project::query()->forWorkspace($b)->count());
    }
}
