<?php

namespace Database\Factories;

use App\Models\IoTSensor;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IoTSensor>
 */
class IoTSensorFactory extends Factory
{
    protected $model = IoTSensor::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'mine_area_id' => null,  // Can be null or set via a method
            'name' => $this->faker->words(2, true),
            'sensor_type' => $this->faker->randomElement(['temperature', 'humidity', 'dust', 'vibration', 'noise', 'air_quality', 'pressure', 'custom', 'accelerometer']),
            'device_id' => $this->faker->unique()->uuid(),
            'status' => $this->faker->randomElement(['online', 'offline', 'error']),
            'last_reading' => $this->faker->numberBetween(0, 100),
            'last_reading_at' => now(),
            'location_latitude' => $this->faker->latitude(),
            'location_longitude' => $this->faker->longitude(),
            'metadata' => [
                'brand' => $this->faker->company(),
                'model' => $this->faker->word(),
            ],
        ];
    }
}
