<?php

namespace App\Events;

use App\Models\MapEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MapEventRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly MapEvent $mapEvent
    ) {}

    /**
     * Broadcast on the team's private channel.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.' . $this->mapEvent->team_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'map-event.recorded';
    }

    /**
     * Full event payload sent to the browser so the map can render the marker
     * immediately without a round-trip to the server.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $e    = $this->mapEvent;
        $cfg  = \App\Models\MapEvent::TYPE_CONFIG[$e->event_type] ?? ['label' => 'Event', 'color' => '#94a3b8', 'emoji' => '📍'];

        return [
            'id'           => $e->id,
            'event_type'   => $e->event_type,
            'type_label'   => $cfg['label'],
            'color'        => $cfg['color'],
            'emoji'        => $cfg['emoji'],
            'title'        => $e->title,
            'notes'        => $e->notes,
            'latitude'     => $e->latitude,
            'longitude'    => $e->longitude,
            'occurred_at'  => $e->occurred_at->toIso8601String(),
            'machine_id'   => $e->machine_id,
            'machine_name' => $e->machine?->name,
            'mine_area'    => $e->mineArea?->name,
            'metadata'     => $e->metadata ?? [],
        ];
    }
}
