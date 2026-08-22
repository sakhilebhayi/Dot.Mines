<?php

namespace App\Events;

use App\Models\Machine;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MachineLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The machine instance.
     */
    public Machine $machine;

    /**
     * The location data (latitude, longitude, accuracy, etc.)
     *
     * @var array<string, mixed>
     */
    public array $location;

    /**
     * Timestamp of the location update.
     */
    public CarbonInterface $timestamp;

    /**
     * Create a new event instance.
     */
    /** @param array<string, mixed> $location */
    public function __construct(Machine $machine, array $location)
    {
        $this->machine = $machine;
        $this->location = $location;
        $this->timestamp = now();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Broadcast to the team channel
            new PrivateChannel('team.'.$this->machine->team_id),

            // Broadcast to the specific machine channel
            new PrivateChannel('machine.'.$this->machine->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->machine->id,
            'name' => $this->machine->name,
            'latitude' => $this->location['latitude'] ?? null,
            'longitude' => $this->location['longitude'] ?? null,
            'accuracy' => $this->location['accuracy'] ?? null,
            'speed' => $this->location['speed'] ?? null,
            'bearing' => $this->location['bearing'] ?? null,
            'altitude' => $this->location['altitude'] ?? null,
            'timestamp' => $this->timestamp,
            'status' => $this->machine->status,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'machine.location.updated';
    }
}
