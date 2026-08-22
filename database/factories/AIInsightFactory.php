<?php

namespace Database\Factories;

use App\Models\AIInsight;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AIInsight>
 */
class AIInsightFactory extends Factory
{
    protected $model = AIInsight::class;

    public function definition(): array
    {
        $types = ['trend', 'anomaly', 'prediction', 'optimization'];
        $categories = ['fleet', 'fuel', 'production', 'maintenance', 'cost'];
        $severities = ['critical', 'warning', 'info', 'success'];

        return [
            'team_id' => Team::factory(),
            'insight_type' => $this->faker->randomElement($types),
            'category' => $this->faker->randomElement($categories),
            'severity' => $this->faker->randomElement($severities),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'data' => ['metric' => $this->faker->numberBetween(1, 100)],
            'visualization_data' => ['chart_type' => 'line'],
            'is_read' => false,
            'valid_until' => now()->addDays(7),
        ];
    }

    public function read(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    public function critical(): self
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
        ]);
    }
}
