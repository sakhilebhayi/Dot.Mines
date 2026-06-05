<?php

namespace Database\Factories;

use App\Models\AIAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AIAgent> */
class AIAgentFactory extends Factory
{
    protected $model = AIAgent::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        $types = [
            AIAgent::TYPE_FLEET_OPTIMIZER,
            AIAgent::TYPE_ROUTE_ADVISOR,
            AIAgent::TYPE_FUEL_PREDICTOR,
            AIAgent::TYPE_MAINTENANCE_PREDICTOR,
            AIAgent::TYPE_PRODUCTION_OPTIMIZER,
            AIAgent::TYPE_COST_ANALYZER,
            AIAgent::TYPE_ANOMALY_DETECTOR,
        ];

        return [
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement($types),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'configuration' => ['test' => true],
            'capabilities' => ['analysis', 'prediction'],
            'accuracy_score' => $this->faker->randomFloat(2, 0.7, 0.95),
            'predictions_made' => $this->faker->numberBetween(0, 1000),
            'successful_predictions' => $this->faker->numberBetween(0, 800),
            'last_trained_at' => now(),
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function training(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'training',
        ]);
    }
}
