<?php

namespace App\Events;

use App\Models\IoTSensor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public IoTSensor $sensor,
        public string $oldStatus,
        public string $newStatus,
        public int $teamId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("team.{$this->teamId}.alerts");
    }

    public function broadcastAs(): string
    {
        return 'sensor.status_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'sensor_id' => $this->sensor->id,
            'sensor_name' => $this->sensor->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'timestamp' => now()->toIso8601String(),
            'alert_level' => $this->newStatus === 'inactive' ? 'warning' : 'info',
        ];
    }
}
