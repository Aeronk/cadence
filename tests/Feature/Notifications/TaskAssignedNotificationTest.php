<?php

namespace Tests\Feature\Notifications;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_assignees_receive_task_assigned_notification(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace();
        $assignee = User::factory()->create();
        $workspace->members()->attach($assignee, ['role' => 'member']);

        $project = Project::factory()->for($workspace)->create(['created_by' => $owner->id]);

        $this->actingAs($owner)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => 'Do the thing',
            'assignee_ids' => [$assignee->id],
        ])->assertRedirect();

        Notification::assertSentTo($assignee, TaskAssigned::class);
    }

    public function test_assigner_is_not_notified(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $project = Project::factory()->for($owner->currentWorkspace())->create(['created_by' => $owner->id]);

        $this->actingAs($owner)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'title' => 'Do the thing',
            'assignee_ids' => [$owner->id],
        ])->assertRedirect();

        Notification::assertNothingSentTo($owner);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);
        $task = Task::factory()->for($project)->create();

        $user->notify(new TaskAssigned($task, $user));

        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        $this->actingAs($user)
            ->patch(route('notifications.read', ['id' => $notification->id]))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
