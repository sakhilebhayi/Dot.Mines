<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sensor;

    public $oldStatus;

    public $newStatus;

    public $teamId;

    public function __construct($sensor, $oldStatus, $newStatus, $teamId)
    {
        $this->sensor = $sensor;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->teamId = $teamId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("team.{$this->teamId}.alerts");
    }

    public function broadcastAs()
    {
        return 'sensor.status_changed';
    }

    public function broadcastWith()
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
