<?php

namespace Database\Factories;

use App\Models\ComplianceViolation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceViolation>
 */
class ComplianceViolationFactory extends Factory
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
            'violation_type' => fake()->randomElement([
                'Safety Inspection Overdue',
                'Equipment Certification Expired',
                'Operator License Expired',
                'Environmental Compliance',
                'Maintenance Schedule Violation',
            ]),
            'description' => fake()->sentence(),
            'severity' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'detected_at' => now()->subDays(rand(1, 30)),
            'remediation_deadline' => now()->addDays(rand(7, 30)),
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
            'metadata' => [],
        ];
    }
}
