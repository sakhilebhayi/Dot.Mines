<?php

namespace Database\Factories;

use App\Models\ProductionRecord;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionRecord>
 */
class ProductionRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'mine_area_id' => null,
            'machine_id' => null,
            'record_date' => now()->toDateString(),
            'shift' => $this->faker->randomElement(['day', 'night', 'afternoon']),
            'quantity_produced' => $this->faker->randomFloat(2, 100, 5000),
            'system_quantity' => null,
            'unit' => 'tonnes',
            'target_quantity' => $this->faker->randomFloat(2, 1000, 6000),
            'notes' => null,
            'status' => 'completed',
            'metadata' => null,
        ];
    }
}
