<?php

namespace App\Events;

use App\Models\HaulDispatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HaulDispatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly HaulDispatch $haulDispatch,
        public readonly string $eventType = 'updated'  // created | updated | completed
    ) {}

    /**
     * Broadcast on the team's private channel so only authorised users receive it.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.'.$this->haulDispatch->team_id),
        ];
    }

    /**
     * Custom event name used on the JavaScript side.
     */
    public function broadcastAs(): string
    {
        return 'haul-dispatch.updated';
    }

    /**
     * Payload sent to the browser – only the fields needed for live UI updates.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $dispatch = $this->haulDispatch;

        return [
            'id' => $dispatch->id,
            'machine_id' => $dispatch->machine_id,
            'machine_name' => $dispatch->machine?->name ?? 'Unknown',
            'event_type' => $this->eventType,
            'status' => $dispatch->status,
            'current_lat' => $dispatch->current_latitude,
            'current_lng' => $dispatch->current_longitude,
            'current_heading' => $dispatch->current_heading,
            'current_speed' => $dispatch->current_speed_kmh,
            'current_tonnage' => $dispatch->current_tonnage,
            'fuel_percentage' => $dispatch->fuel_percentage,
            'eta' => $dispatch->eta_formatted,
            'distance_remaining_km' => $dispatch->distance_remaining_km,
        ];
    }
}
