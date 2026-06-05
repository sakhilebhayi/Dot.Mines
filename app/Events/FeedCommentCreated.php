<?php

namespace App\Events;

use App\Models\FeedComment;
use App\Models\FeedPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeedCommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly FeedComment $comment,
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
            'comment_count' => $this->post->comment_count,
            'comment' => [
                'id' => $this->comment->id,
                'post_id' => $this->comment->post_id,
                'parent_comment_id' => $this->comment->parent_comment_id,
                'author_id' => $this->comment->author_id,
                'author_name' => $this->comment->author->name,
                'body' => $this->comment->body,
                'is_edited' => $this->comment->is_edited,
                'created_at' => $this->comment->created_at->toISOString(),
            ],
        ];
    }
}
