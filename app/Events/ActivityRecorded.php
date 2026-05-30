<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ActivityLog $activity) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->activity->workspace_id}")];
    }

    public function broadcastAs(): string
    {
        return 'activity.recorded';
    }

    public function broadcastWith(): array
    {
        $this->activity->loadMissing('actor:id,name');

        return [
            'activity' => [
                'id' => $this->activity->id,
                'action' => $this->activity->action,
                'description' => $this->activity->description,
                'created_at' => $this->activity->created_at->toIso8601String(),
                'actor' => $this->activity->actor
                    ? ['id' => $this->activity->actor->id, 'name' => $this->activity->actor->name]
                    : null,
            ],
        ];
    }
}
