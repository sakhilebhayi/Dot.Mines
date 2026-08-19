<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineMetric>
 */
class MachineMetricFactory extends Factory
{
    protected $model = MachineMetric::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'machine_id' => Machine::factory(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'speed' => $this->faker->randomFloat(2, 0, 80),
            'heading' => $this->faker->randomFloat(2, 0, 360),
            'altitude' => $this->faker->randomFloat(2, 0, 2000),
            'engine_rpm' => $this->faker->randomFloat(2, 800, 2500),
            'engine_temperature' => $this->faker->randomFloat(2, 80, 110),
            'coolant_temperature' => $this->faker->randomFloat(2, 70, 100),
            'oil_pressure' => $this->faker->randomFloat(2, 20, 60),
            'fuel_level' => $this->faker->randomFloat(2, 10, 100),
            'fuel_consumption_rate' => $this->faker->randomFloat(2, 5, 30),
            'throttle_position' => $this->faker->randomFloat(2, 0, 100),
            'battery_voltage' => $this->faker->randomFloat(2, 12, 14),
            'total_hours' => $this->faker->randomFloat(2, 0, 10000),
            'idle_hours' => $this->faker->randomFloat(2, 0, 1000),
            'operating_hours' => $this->faker->randomFloat(2, 0, 24),
            'load_weight' => $this->faker->randomFloat(2, 0, 50),
            'payload_capacity_used' => $this->faker->randomFloat(2, 0, 100),
            'tire_pressure_front_left' => $this->faker->randomFloat(2, 80, 120),
            'tire_pressure_front_right' => $this->faker->randomFloat(2, 80, 120),
            'tire_pressure_rear_left' => $this->faker->randomFloat(2, 80, 120),
            'tire_pressure_rear_right' => $this->faker->randomFloat(2, 80, 120),
            'recorded_at' => now(),
        ];
    }
}
