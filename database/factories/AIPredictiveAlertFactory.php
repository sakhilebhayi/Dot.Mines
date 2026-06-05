<?php

namespace Database\Factories;

use App\Models\AIAgent;
use App\Models\AIPredictiveAlert;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AIPredictiveAlert> */
class AIPredictiveAlertFactory extends Factory
{
    protected $model = AIPredictiveAlert::class;

    /**
     * @return array<mixed>
     */
    public function definition(): array
    {
        $alertTypes = ['breakdown_risk', 'fuel_shortage', 'production_delay', 'cost_overrun'];
        $severities = ['critical', 'high', 'medium', 'low'];

        return [
            'team_id' => Team::factory(),
            'ai_agent_id' => AIAgent::factory(),
            'alert_type' => $this->faker->randomElement($alertTypes),
            'severity' => $this->faker->randomElement($severities),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'predictions' => ['risk' => 0.8],
            'probability' => $this->faker->randomFloat(2, 0.5, 0.95),
            'predicted_occurrence' => now()->addDays($this->faker->numberBetween(1, 30)),
            'recommended_actions' => ['action1', 'action2'],
            'is_acknowledged' => false,
        ];
    }

    public function acknowledged(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
    }

    public function critical(): self
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'critical',
            'probability' => 0.95,
        ]);
    }
}
