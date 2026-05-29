<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_projects_in_their_current_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace();

        $mine = Project::factory()->for($workspace)->create(['created_by' => $user->id]);
        Project::factory()->create(); // other workspace — must not leak

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->has('projects', 1)
                ->where('projects.0.id', $mine->id)
            );
    }

    public function test_user_can_create_a_project(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace();
        $tag = Tag::factory()->for($workspace)->create();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'title' => 'Launch website',
                'description' => 'Marketing rebuild',
                'tag_ids' => [$tag->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id,
            'title' => 'Launch website',
            'created_by' => $user->id,
        ]);

        $project = Project::firstWhere('title', 'Launch website');
        $this->assertTrue($project->members()->where('users.id', $user->id)->exists());
        $this->assertTrue($project->tags->contains($tag));
    }

    public function test_outsider_cannot_show_project(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_admin_can_update_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create([
            'created_by' => $user->id,
            'title' => 'Old',
        ]);

        $this->actingAs($user)
            ->patch(route('projects.update', $project), ['title' => 'New Title'])
            ->assertRedirect();

        $this->assertSame('New Title', $project->fresh()->title);
    }

    public function test_creator_can_soft_delete_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }
}
