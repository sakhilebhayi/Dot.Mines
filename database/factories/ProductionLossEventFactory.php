<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\ProductionLossEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionLossEvent>
 */
class ProductionLossEventFactory extends Factory
{
    protected $model = ProductionLossEvent::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-7 days', '-1 hour');
        $hours = $this->faker->randomFloat(2, 0.5, 8);

        return [
            'team_id' => Team::factory(),
            'machine_id' => Machine::factory(),
            'started_at' => $start,
            'ended_at' => (clone $start)->modify('+'.(int) ceil($hours * 60).' minutes'),
            'lost_hours' => $hours,
            'source' => ProductionLossEvent::SOURCE_USER,
            'status' => ProductionLossEvent::STATUS_CONFIRMED,
            'category' => 'mechanical',
            'reason' => 'breakdown',
        ];
    }

    public function detected(): static
    {
        return $this->state(fn () => [
            'source' => ProductionLossEvent::SOURCE_SYSTEM,
            'status' => ProductionLossEvent::STATUS_PENDING,
            'category' => null,
            'reason' => null,
            'detection_basis' => 'Telemetry window with no engine-hours accumulation.',
        ]);
    }
}
