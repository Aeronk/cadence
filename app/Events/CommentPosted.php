<?php

namespace App\Events;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    /**
     * Broadcast on the commentable's channel — task or project.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $commentable = $this->comment->commentable;

        return match (true) {
            $commentable instanceof Task => [new PrivateChannel("task.{$commentable->id}")],
            $commentable instanceof Project => [new PrivateChannel("project.{$commentable->id}")],
            default => [],
        };
    }

    public function broadcastAs(): string
    {
        return 'comment.posted';
    }

    public function broadcastWith(): array
    {
        $this->comment->loadMissing('user:id,name');

        return [
            'comment' => [
                'id' => $this->comment->id,
                'body' => $this->comment->body,
                'created_at' => $this->comment->created_at->toIso8601String(),
                'user' => [
                    'id' => $this->comment->user->id,
                    'name' => $this->comment->user->name,
                ],
                'commentable_type' => $this->comment->commentable instanceof Task ? 'task' : 'project',
                'commentable_id' => $this->comment->commentable->id,
            ],
        ];
    }
}
