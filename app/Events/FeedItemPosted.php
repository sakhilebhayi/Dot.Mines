<?php

namespace App\Events;

use App\Models\FeedItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new item landed in the Mine Operations Feed.
 *
 * Broadcast on the team's existing private channel (already authorised in
 * routes/channels.php), carrying only the id and category: the page
 * re-fetches through Livewire, so the payload never becomes a second,
 * unauthorised way to read feed content.
 */
class FeedItemPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public FeedItem $item) {}

    /**
     * @return array<int, Channel>
     */
    #[\Override]
    public function broadcastOn(): array
    {
        return [new PrivateChannel('team.'.$this->item->team_id)];
    }

    public function broadcastAs(): string
    {
        return 'feed.item.posted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->item->id,
            'category' => $this->item->category,
            'source' => $this->item->source,
        ];
    }
}
