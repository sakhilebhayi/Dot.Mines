<?php

namespace App\Http\Resources;

use App\Models\FeedPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeedPost
 */
class FeedPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_id' => $this->author_id,
            'mine_area_id' => $this->mine_area_id,
            'shift' => $this->shift,
            'category' => $this->category,
            'priority' => $this->priority,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            'acknowledgement_count' => $this->acknowledgement_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
