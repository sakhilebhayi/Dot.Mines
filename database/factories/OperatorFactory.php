<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\Team;
use App\Support\EquipmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'department' => 'Mining Operations',
            'job_title' => 'Machine Operator',
            'employment_type' => 'permanent',
            'employed_from' => now()->subYears(2)->toDateString(),
            'default_shift' => 'day',
            'employment_status' => Operator::STATUS_ACTIVE,
        ];
    }

    /**
     * Fully compliant to operate the given equipment: current licence,
     * current medical, current site induction.
     */
    public function compliantFor(string $equipmentType = EquipmentType::ADT): static
    {
        return $this->afterCreating(function (Operator $operator) use ($equipmentType): void {
            $operator->qualifications()->create([
                'team_id' => $operator->team_id,
                'title' => EquipmentType::label($equipmentType).' Operator',
                'licence_number' => strtoupper(fake()->bothify('??######')),
                'equipment_type' => $equipmentType,
                'issued_on' => now()->subYear()->toDateString(),
                'expires_on' => now()->addYear()->toDateString(),
            ]);

            $operator->medicals()->create([
                'team_id' => $operator->team_id,
                'certificate_number' => strtoupper(fake()->bothify('MED-#####')),
                'examined_on' => now()->subMonths(6)->toDateString(),
                'expires_on' => now()->addMonths(6)->toDateString(),
                'fitness' => 'fit',
            ]);

            $operator->trainings()->create([
                'team_id' => $operator->team_id,
                'course' => 'Site Induction',
                'category' => 'site_induction',
                'completed_on' => now()->subMonths(3)->toDateString(),
                'expires_on' => now()->addMonths(9)->toDateString(),
            ]);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['employment_status' => Operator::STATUS_SUSPENDED]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['employment_status' => Operator::STATUS_INACTIVE]);
    }
}
