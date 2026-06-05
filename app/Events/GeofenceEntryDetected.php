<?php

namespace App\Events;

use App\Models\GeofenceEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GeofenceEntryDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The geofence entry instance.
     */
    public GeofenceEntry $entry;

    /**
     * Create a new event instance.
     */
    public function __construct(GeofenceEntry $entry)
    {
        $this->entry = $entry;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $geofence = $this->entry->geofence;
        $machine = $this->entry->machine;

        return [
            // Broadcast to team channel
            new PrivateChannel('team.'.$geofence->team_id),

            // Broadcast to geofence channel
            new PrivateChannel('geofence.'.$geofence->id),

            // Broadcast to machine channel
            new PrivateChannel('machine.'.$machine->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<mixed>
     */
    public function broadcastWith(): array
    {
        $geofence = $this->entry->geofence;
        $machine = $this->entry->machine;

        return [
            'id' => $this->entry->id,
            'geofence_id' => $geofence->id,
            'geofence_name' => $geofence->name,
            'machine_id' => $machine->id,
            'machine_name' => $machine->name,
            'entry_type' => 'entry',
            'latitude' => $this->entry->latitude,
            'longitude' => $this->entry->longitude,
            'entry_time' => $this->entry->entry_time,
            'team_id' => $geofence->team_id,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'geofence.entry.detected';
    }
}
