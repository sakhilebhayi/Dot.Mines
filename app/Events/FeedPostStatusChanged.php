<?php

namespace App\Events;

use App\Models\FeedApproval;
use App\Models\FeedPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedPostStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly FeedPost $post,
        public readonly FeedApproval $approval,
    ) {}

    /**
     * @return array<mixed>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('feed.team.'.$this->post->team_id),
        ];
    }

    /**
     * @return array<mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->post->id,
            'status' => $this->approval->status,   // approved | rejected
            'reason' => $this->approval->reason,
            'approver_id' => $this->approval->approver_id,
            'reviewed_at' => $this->approval->reviewed_at?->toISOString(),
        ];
    }
}
