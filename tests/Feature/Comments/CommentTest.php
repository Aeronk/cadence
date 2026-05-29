<?php

namespace Tests\Feature\Comments;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_comment_on_task_they_can_view(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('comments.store'), [
                'commentable_type' => 'task',
                'commentable_id' => $task->id,
                'body' => 'First comment',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'commentable_type' => Task::class,
            'commentable_id' => $task->id,
            'body' => 'First comment',
            'user_id' => $user->id,
        ]);
    }

    public function test_outsider_cannot_comment_on_task(): void
    {
        $task = Task::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('comments.store'), [
                'commentable_type' => 'task',
                'commentable_id' => $task->id,
                'body' => 'sneaky',
            ])
            ->assertForbidden();
    }

    public function test_comment_author_can_edit_and_delete(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create();
        $comment = $task->comments()->create(['user_id' => $user->id, 'body' => 'hi']);

        $this->actingAs($user)
            ->patch(route('comments.update', $comment), ['body' => 'edited'])
            ->assertRedirect();

        $this->assertSame('edited', $comment->fresh()->body);

        $this->actingAs($user)
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }
}
