<?php

namespace Database\Factories;

use App\Models\FeedPost;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedPost>
 */
class FeedPostFactory extends Factory
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
            'author_id' => User::factory(),
            'mine_area_id' => null,
            'shift' => $this->faker->randomElement(['A', 'B', 'C']),
            'category' => $this->faker->randomElement(['breakdown', 'shift_update', 'safety_alert', 'production', 'general']),
            'priority' => $this->faker->randomElement(['normal', 'high', 'critical']),
            'body' => $this->faker->paragraph(),
            'meta' => null,
            'like_count' => 0,
            'comment_count' => 0,
            'acknowledgement_count' => 0,
            'is_pinned' => false,
        ];
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => 'critical']);
    }
}
