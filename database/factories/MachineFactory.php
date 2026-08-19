<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    protected $model = Machine::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(2, true),
            'machine_type' => $this->faker->randomElement(['excavator', 'dozer', 'loader', 'haul_truck', 'drill']),
            'year_of_manufacture' => $this->faker->numberBetween(2000, now()->year),
            'registration_number' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{6}'),
            'serial_number' => $this->faker->unique()->regexify('[A-Z0-9]{10}'),
            'status' => $this->faker->randomElement(['active', 'inactive', 'maintenance']),
            'last_location_latitude' => $this->faker->latitude(),
            'last_location_longitude' => $this->faker->longitude(),
            'last_location_update' => now(),
            'mine_area_id' => null, // Can be set explicitly in tests
        ];
    }
}
