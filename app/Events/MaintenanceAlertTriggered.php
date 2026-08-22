<?php

namespace App\Events;

use App\Models\Machine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MaintenanceAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Machine $machine,
        public float $probability,
        public Carbon $predictedDate,
        public int $teamId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("team.{$this->teamId}.alerts");
    }

    public function broadcastAs(): string
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
