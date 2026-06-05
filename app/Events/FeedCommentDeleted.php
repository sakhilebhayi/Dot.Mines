<?php

namespace App\Events;

use App\Models\FeedPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedCommentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $commentId,
        public readonly FeedPost $post,
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
            'comment_id' => $this->commentId,
            'comment_count' => $this->post->comment_count,
        ];
    }
}
