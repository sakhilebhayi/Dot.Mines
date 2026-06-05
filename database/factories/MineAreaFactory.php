<?php

namespace Database\Factories;

use App\Models\MineArea;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MineArea>
 */
class MineAreaFactory extends Factory
{
    protected $model = MineArea::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => implode(' ', (array) $this->faker->words(2)).' Mine Area',
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'center_latitude' => $this->faker->latitude(),
            'center_longitude' => $this->faker->longitude(),
        ];
    }
}
