<?php

namespace Database\Factories;

use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeofenceEntry>
 */
class GeofenceEntryFactory extends Factory
{
    protected $model = GeofenceEntry::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        $entryTime = $this->faker->dateTimeBetween('-1 month', 'now');
        $exitTime = $this->faker->optional(0.7)->dateTimeBetween($entryTime, 'now'); // 70% chance of having exit time

        return [
            'team_id' => Team::factory(),
            'geofence_id' => Geofence::factory(),
            'machine_id' => Machine::factory(),
            'entry_time' => $entryTime,
            'exit_time' => $exitTime,
            'entry_latitude' => $this->faker->latitude(-30, -25),
            'entry_longitude' => $this->faker->longitude(25, 30),
            'exit_latitude' => $exitTime ? $this->faker->latitude(-30, -25) : null,
            'exit_longitude' => $exitTime ? $this->faker->longitude(25, 30) : null,
            'tonnage_loaded' => $this->faker->randomFloat(2, 0, 100),
            'material_type' => $this->faker->optional()->randomElement(['ore', 'coal', 'waste', 'overburden']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the entry is still active (no exit time).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'exit_time' => null,
            'exit_latitude' => null,
            'exit_longitude' => null,
        ]);
    }

    /**
     * Indicate that the entry has been completed (has exit time).
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $entryTime = $attributes['entry_time'] ?? now()->subHours(2);
            $exitTime = $this->faker->dateTimeBetween($entryTime, 'now');

            return [
                'exit_time' => $exitTime,
                'exit_latitude' => $this->faker->latitude(-30, -25),
                'exit_longitude' => $this->faker->longitude(25, 30),
            ];
        });
    }
}
