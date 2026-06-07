<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', 'now');

        return [
            'team_id' => Team::factory(),
            'shift_type' => $this->faker->randomElement(['day', 'night']),
            'started_at' => $start,
            'ended_at' => (clone $start)->modify('+12 hours'),
            'previous_assignments' => [],
            'productivity_metrics' => [],
            'performance_summary' => null,
            'metadata' => null,
        ];
    }
}
