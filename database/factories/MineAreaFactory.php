<?php

namespace Database\Factories;

use App\Models\MineArea;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * MineArea previously had NO factory even though other factories
 * (FuelTankFactory) referenced MineArea::factory() -- any test touching
 * those factories fataled. Tests worked around it with MineArea::create().
 *
 * @extends Factory<MineArea>
 */
class MineAreaFactory extends Factory
{
    protected $model = MineArea::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Pit '.$this->faker->unique()->lexify('??'),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'center_latitude' => $this->faker->latitude(-27, -25),
            'center_longitude' => $this->faker->longitude(28, 30),
            'area_size_hectares' => $this->faker->randomFloat(1, 5, 500),
        ];
    }
}
