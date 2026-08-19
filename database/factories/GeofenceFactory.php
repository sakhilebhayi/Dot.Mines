<?php

namespace Database\Factories;

use App\Models\Geofence;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Geofence>
 */
class GeofenceFactory extends Factory
{
    protected $model = Geofence::class;

    public function definition(): array
    {
        // Generate a simple polygon (rectangle) for testing
        $lat = $this->faker->latitude(-30, -25); // South Africa region
        $lng = $this->faker->longitude(25, 30);

        // Create a small rectangle around the point
        $coordinates = [
            [$lng, $lat],
            [$lng + 0.01, $lat],
            [$lng + 0.01, $lat + 0.01],
            [$lng, $lat + 0.01],
            [$lng, $lat], // Close the polygon
        ];

        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->words(2, true).' Zone',
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['pit', 'stockpile', 'dump', 'facility']),
            'coordinates' => $coordinates,
            'center_latitude' => $lat,
            'center_longitude' => $lng,
            'area_sqm' => $this->faker->randomFloat(2, 1000, 100000),
            'perimeter_m' => $this->faker->randomFloat(2, 100, 2000),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the geofence is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the geofence is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Set a specific fence type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'fence_type' => $type,
        ]);
    }
}
