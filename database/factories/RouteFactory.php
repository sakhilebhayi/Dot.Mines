<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'start_latitude' => $this->faker->latitude(-26.5, -25.5),
            'start_longitude' => $this->faker->longitude(27.5, 28.5),
            'end_latitude' => $this->faker->latitude(-26.5, -25.5),
            'end_longitude' => $this->faker->longitude(27.5, 28.5),
            'total_distance' => $this->faker->randomFloat(2, 5, 100),
            'estimated_time' => $this->faker->numberBetween(10, 180),
            'estimated_fuel' => $this->faker->randomFloat(2, 2, 50),
            'route_type' => 'optimal',
            'status' => 'draft',
        ];
    }
}
