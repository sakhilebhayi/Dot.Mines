<?php

namespace Database\Factories;

use App\Models\IoTSensor;
use App\Models\SensorReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SensorReading>
 */
class SensorReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iot_sensor_id' => IoTSensor::factory(),
            'sensor_type' => $this->faker->randomElement(['temperature', 'pressure', 'vibration', 'fuel_level', 'humidity']),
            'value' => $this->faker->randomFloat(2, 0, 100),
            'unit' => $this->faker->randomElement(['°C', 'bar', 'mm/s', 'L', '%']),
            'timestamp' => now(),
            'quality_score' => $this->faker->randomFloat(2, 0.7, 1.0),
        ];
    }
}
