<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
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
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(['fuel', 'maintenance', 'production', 'fleet_utilization']),
            'status' => 'pending',
            'format' => fake()->randomElement(['csv', 'xlsx', 'pdf']),
            'filters' => [],
            'generated_by' => null,
            'generated_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'generated_at' => now(),
            'file_path' => 'reports/test-report.csv',
            'file_size' => 1024,
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed']);
    }
}
