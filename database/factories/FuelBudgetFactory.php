<?php

namespace Database\Factories;

use App\Models\FuelBudget;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelBudget>
 */
class FuelBudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'team_id' => Team::factory(),
            'mine_area_id' => null,
            'period_type' => 'monthly',
            'start_date' => $start,
            'end_date' => $start->copy()->endOfMonth(),
            'budgeted_amount' => $this->faker->randomFloat(2, 50000, 500000),
            'budgeted_liters' => $this->faker->randomFloat(2, 5000, 50000),
            'actual_spent' => 0,
            'actual_liters' => 0,
            'status' => 'active',
            'notes' => null,
        ];
    }
}
