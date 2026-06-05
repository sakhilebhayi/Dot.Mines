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

    public IoTSensor $sensor;

    /** @var array<string, mixed> */
    public array $reading;

    public int $teamId;

    /**
     * @param  array<string, mixed>  $reading
     */
    public function __construct(IoTSensor $sensor, array $reading, int $teamId)
    {
        $this->sensor = $sensor;
        $this->reading = $reading;
        $this->teamId = $teamId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("team.{$this->teamId}.sensors");
    }

    public function broadcastAs(): mixed
    {
        return 'sensor.reading';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
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
