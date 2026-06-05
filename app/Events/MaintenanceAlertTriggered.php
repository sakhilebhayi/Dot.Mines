<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public mixed $machine;

    public float $probability;

    public mixed $predictedDate;

    public int $teamId;

    public function __construct(mixed $machine, float $probability, mixed $predictedDate, int $teamId)
    {
        $this->machine = $machine;
        $this->probability = $probability;
        $this->predictedDate = $predictedDate;
        $this->teamId = $teamId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("team.{$this->teamId}.alerts");
    }

    public function broadcastAs(): mixed
    {
        return 'maintenance.alert';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'machine_id' => $this->machine->id,
            'machine_name' => $this->machine->name,
            'alert_type' => 'maintenance_prediction',
            'probability' => round($this->probability, 2),
            'predicted_date' => $this->predictedDate,
            'severity' => $this->probability >= 0.8 ? 'critical' : ($this->probability >= 0.6 ? 'high' : 'medium'),
            'timestamp' => now()->toIso8601String(),
            'action_required' => true,
        ];
    }
}
