<?php

namespace Database\Factories;

use App\Models\MineArea;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MineArea>
 */
class MineAreaFactory extends Factory
{
    protected $model = MineArea::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(2, true).' Mine Area',
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'center_latitude' => $this->faker->latitude(),
            'center_longitude' => $this->faker->longitude(),
        ];
    }
}
