<?php

namespace App\Events;

use App\Models\FeedPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedPostCreated implements ShouldBroadcast
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
            'post' => [
                'id' => $this->post->id,
                'author_id' => $this->post->author_id,
                'author_name' => $this->post->author->name,
                'mine_area_id' => $this->post->mine_area_id,
                'mine_area_name' => $this->post->mineArea?->name,
                'shift' => $this->post->shift,
                'category' => $this->post->category,
                'priority' => $this->post->priority,
                'body' => $this->post->body,
                'meta' => $this->post->meta,
                'like_count' => $this->post->like_count,
                'comment_count' => $this->post->comment_count,
                'acknowledgement_count' => $this->post->acknowledgement_count,
                'is_pinned' => $this->post->is_pinned,
                'created_at' => $this->post->created_at->toISOString(),
            ],
        ];
    }
}
