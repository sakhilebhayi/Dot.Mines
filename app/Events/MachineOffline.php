<?php

namespace App\Events;

use App\Models\Machine;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MachineOffline implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The machine instance.
     */
    public Machine $machine;

    /**
     * The reason for going offline.
     */
    public ?string $reason;

    /**
     * Last known location.
     */
    /** @var array<string, mixed>|null */
    public ?array $lastLocation;

    /**
     * Create a new event instance.
     */
    public function __construct(Machine $machine, ?string $reason = null, ?array $lastLocation = null)
    {
        $this->machine = $machine;
        $this->reason = $reason;
        $this->lastLocation = $lastLocation;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
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
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->machine->id,
            'name' => $this->machine->name,
            'status' => 'offline',
            'reason' => $this->reason ?? 'Connection lost',
            'went_offline_at' => now(),
            'last_location' => $this->lastLocation,
            'last_seen' => $this->machine->last_seen_at,
            'team_id' => $this->machine->team_id,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'machine.offline';
    }
}
