<?php

namespace App\Events;

use App\Models\Machine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MachineStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Machine $machine;

    public string $oldStatus;

    public string $newStatus;

    public function __construct(Machine $machine, string $oldStatus, string $newStatus)
    {
        $this->machine = $machine;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * @return array<mixed>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.'.$this->machine->team_id),
            new PrivateChannel('machine.'.$this->machine->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'machine.status_changed';
    }

    /**
     * @return array<mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'machine_id' => $this->machine->id,
            'machine_name' => $this->machine->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
