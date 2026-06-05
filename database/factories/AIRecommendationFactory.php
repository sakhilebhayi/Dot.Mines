<?php

namespace Database\Factories;

use App\Models\AIAgent;
use App\Models\AIRecommendation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AIRecommendationFactory extends Factory
{
    protected $model = AIRecommendation::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        $categories = ['fleet', 'fuel', 'maintenance', 'production', 'route', 'cost'];
        $priorities = ['critical', 'high', 'medium', 'low'];
        $statuses = ['pending', 'accepted', 'rejected', 'implemented'];

        return [
            'team_id' => Team::factory(),
            'ai_agent_id' => AIAgent::factory(),
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement($categories),
            'priority' => $this->faker->randomElement($priorities),
            'status' => 'pending',
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'data' => ['test' => 'data'],
            'impact_analysis' => ['impact' => 'high'],
            'confidence_score' => $this->faker->randomFloat(2, 0.7, 0.95),
            'estimated_savings' => $this->faker->randomFloat(2, 1000, 100000),
            'estimated_efficiency_gain' => $this->faker->randomFloat(2, 5, 50),
        ];
    }

    public function implemented(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'implemented',
            'implemented_at' => now(),
            'implemented_by' => User::factory(),
        ]);
    }

    public function critical(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'critical',
        ]);
    }

    public function highPriority(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }
}
