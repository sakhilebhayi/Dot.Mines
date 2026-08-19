<?php

namespace Database\Factories;

use App\Models\FuelTank;
use App\Models\FuelTransaction;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelTransaction>
 */
class FuelTransactionFactory extends Factory
{
    protected $model = FuelTransaction::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'fuel_tank_id' => FuelTank::factory(),
            'machine_id' => Machine::factory(),
            'user_id' => User::factory(),
            'transaction_type' => $this->faker->randomElement(['refill', 'dispensing', 'delivery', 'transfer', 'adjustment']),
            'quantity_liters' => $this->faker->randomFloat(2, 50, 500),
            'unit_price' => $this->faker->randomFloat(2, 15, 25),
            'total_cost' => function (array $attributes) {
                return $attributes['quantity_liters'] * $attributes['unit_price'];
            },
            'fuel_type' => $this->faker->randomElement(['diesel', 'petrol', 'biodiesel']),
            'transaction_date' => now(),
            'odometer_reading' => $this->faker->randomFloat(2, 10000, 100000),
            'machine_hours' => $this->faker->randomFloat(2, 1000, 10000),
            'supplier' => $this->faker->company(),
            'invoice_number' => $this->faker->bothify('INV-####-????'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
