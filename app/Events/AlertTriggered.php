<?php

namespace App\Events;

use App\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The alert instance.
     */
    public Alert $alert;

    /**
     * Create a new event instance.
     */
    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    #[\Override]
    public function broadcastOn(): array
    {
        return [
            // Broadcast to the team's alert channel
            new PrivateChannel('alerts.team.'.$this->alert->team_id),

            // Also broadcast to the specific machine channel
            new PrivateChannel('machine.'.$this->alert->machine_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $machine = $this->alert->machine;

        return [
            'id' => $this->alert->id,
            'type' => $this->alert->type,
            'priority' => $this->alert->priority,
            'message' => $this->alert->message,
            'machine_id' => $this->alert->machine_id,
            'machine_name' => $machine ? $machine->name : 'Unknown',
            'description' => $this->alert->description,
            'status' => $this->alert->status,
            'triggered_at' => $this->alert->triggered_at,
            'acknowledged_at' => $this->alert->acknowledged_at,
            'resolved_at' => $this->alert->resolved_at,
            'team_id' => $this->alert->team_id,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'alert.triggered';
    }
}
