<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MachineHealthStatus;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineHealthStatus>
 */
class MachineHealthStatusFactory extends Factory
{
    protected $model = MachineHealthStatus::class;

    public function definition(): array
    {
        $healthScore = $this->faker->numberBetween(40, 100);

        return [
            'team_id' => Team::factory(),
            'machine_id' => Machine::factory(),
            'overall_health_score' => $healthScore,
            'health_status' => $this->getHealthStatus($healthScore),
            'component_scores' => [
                'engine' => $this->faker->numberBetween(50, 100),
                'transmission' => $this->faker->numberBetween(50, 100),
                'hydraulics' => $this->faker->numberBetween(50, 100),
                'electrical' => $this->faker->numberBetween(50, 100),
                'brakes' => $this->faker->numberBetween(50, 100),
                'cooling_system' => $this->faker->numberBetween(50, 100),
            ],
            'engine_health' => $this->faker->numberBetween(50, 100),
            'transmission_health' => $this->faker->numberBetween(50, 100),
            'hydraulics_health' => $this->faker->numberBetween(50, 100),
            'electrical_health' => $this->faker->numberBetween(50, 100),
            'brakes_health' => $this->faker->numberBetween(50, 100),
            'cooling_system_health' => $this->faker->numberBetween(50, 100),
            'last_diagnostic_scan' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'active_fault_codes' => [],
            'fault_code_count' => 0,
            'recommendations' => null,
        ];
    }

    private function getHealthStatus(int $score): string
    {
        if ($score >= 80) {
            return 'excellent';
        } elseif ($score >= 60) {
            return 'good';
        } elseif ($score >= 40) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    public function poor(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'overall_health_score' => $this->faker->numberBetween(20, 39),
                'health_status' => 'poor',
                'fault_code_count' => $this->faker->numberBetween(3, 10),
                'active_fault_codes' => [
                    ['code' => 'P0301', 'description' => 'Cylinder 1 Misfire'],
                    ['code' => 'P0420', 'description' => 'Catalyst System Efficiency Below Threshold'],
                ],
            ];
        });
    }

    public function excellent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'overall_health_score' => $this->faker->numberBetween(90, 100),
                'health_status' => 'excellent',
            ];
        });
    }
}
