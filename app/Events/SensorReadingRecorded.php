<?php

namespace App\Events;

use App\Models\IoTSensor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorReadingRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sensor;

    public $reading;

    public $teamId;

    public function __construct(IoTSensor $sensor, array $reading, $teamId)
    {
        $this->sensor = $sensor;
        $this->reading = $reading;
        $this->teamId = $teamId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("team.{$this->teamId}.sensors");
    }

    public function broadcastAs()
    {
        return 'sensor.reading';
    }

    public function broadcastWith()
    {
        return [
            'sensor_id' => $this->sensor->id,
            'sensor_name' => $this->sensor->name,
            'sensor_type' => $this->sensor->sensor_type,
            'value' => $this->reading['value'],
            'unit' => $this->reading['unit'],
            'timestamp' => now()->toIso8601String(),
            'is_anomaly' => $this->reading['is_anomaly'] ?? false,
        ];
    }
}
