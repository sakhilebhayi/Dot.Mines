<?php

namespace App\Events;

use App\Models\FeedPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedPostLiked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly FeedPost $post) {}

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
            'like_count' => $this->post->like_count,
        ];
    }
}
