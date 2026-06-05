<?php

namespace App\Services;

use App\Models\IoTSensor;
use App\Models\SensorReading;

class IoTSensorService
{
    /**
     * Get sensor readings with statistics
     *
     * @return array<mixed>
     */
    public function getReadingStats(IoTSensor $sensor, int $days = 7): array
    {
        $readings = $sensor->readings()
            ->whereDate('timestamp', '>=', now()->subDays($days)->startOfDay())
            ->orderBy('timestamp')
            ->get();

        if ($readings->isEmpty()) {
            return [
                'count' => 0,
                'average' => null,
                'min' => null,
                'max' => null,
                'trend' => 'no_data',
            ];
        }

        $values = $readings->pluck('value')->toArray();

        if (empty($values)) {
            return ['trend' => 'no_data'];
        }

        return [
            'count' => count($values),
            'average' => array_sum($values) / count($values),
            'min' => min($values),
            'max' => max($values),
            'latest' => $readings->last()->value,
            'unit' => $readings->first()->unit,
            'trend' => $this->calculateTrend($values),
            'readings' => $readings->map(fn ($r) => [
                'value' => $r->value,
                'timestamp' => $r->timestamp,
                'quality' => $r->quality_score,
            ])->toArray(),
        ];
    }

    /**
     * Check sensor health
     *
     * @return array<mixed>
     */
    public function checkSensorHealth(IoTSensor $sensor): array
    {
        $isOnline = $sensor->isOnline();
        $latestReading = $sensor->readings()->latest()->first();

        $lastReadingAge = $latestReading?->timestamp
            ? now()->diffInMinutes($latestReading->timestamp)
            : null;

        return [
            'status' => $sensor->status,
            'is_online' => $isOnline,
            'last_reading_age_minutes' => $lastReadingAge,
            'health' => $isOnline ? 'healthy' : 'offline',
            'last_value' => $latestReading?->value,
            'last_reading_at' => $latestReading?->timestamp,
        ];
    }

    /**
     * Calculate trend from values
     */
    /** @param array<float|int> $values */
    private function calculateTrend(array $values): string
    {
        if (count($values) < 2) {
            return 'insufficient_data';
        }

        $firstHalf = array_slice($values, 0, (int) (count($values) / 2));
        $secondHalf = array_slice($values, (int) (count($values) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;

        if ($change > 5) {
            return 'increasing';
        } elseif ($change < -5) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Record a new sensor reading
     */
    /** @param array<string, mixed> $data */
    public function recordReading(IoTSensor $sensor, array $data): SensorReading
    {
        /** @var SensorReading $reading */
        $reading = $sensor->readings()->create([
            'iot_sensor_id' => $sensor->id,
            'sensor_type' => $sensor->sensor_type,
            'value' => $data['value'],
            'unit' => $data['unit'],
            'timestamp' => $data['timestamp'] ?? now(),
            'quality_score' => $data['quality_score'] ?? null,
        ]);

        return $reading;
    }

    /**
     * Deactivate sensor
     */
    public function deactivateSensor(IoTSensor $sensor): bool
    {
        return $sensor->update(['status' => 'inactive']);
    }
}
