<?php

namespace Tests\Feature\Api;

use App\Models\FuelTank;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A rejected request looks the same everywhere.
 *
 * The API used to answer an invalid body in three different shapes depending
 * on which controller you hit: Laravel's own `{message, errors}`, a bare
 * `{errors}`, and `{success: false, errors}`. A client had to write three
 * error handlers to read the same failure, and would only find out which one
 * it needed by hitting the endpoint and looking.
 */
class ValidationErrorShapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Write endpoints, sent an empty body so validation is what rejects them.
     *
     * @return array<string, array{0: string}>
     */
    public static function writeEndpoints(): array
    {
        return [
            'create machine' => ['/api/v1/machines'],
            'create geofence' => ['/api/v1/geofences'],
            'create fuel tank' => ['/api/v1/fuel/tanks'],
            'create fuel transaction' => ['/api/v1/fuel/transactions'],
            'create integration' => ['/api/v1/integrations'],
            'create maintenance record' => ['/api/v1/maintenance/records'],
            'create maintenance schedule' => ['/api/v1/maintenance/schedules'],
            'create webhook' => ['/api/v1/webhooks'],
        ];
    }

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        Sanctum::actingAs($user->fresh(), ['*']);

        return $user->fresh();
    }

    /**
     * @dataProvider writeEndpoints
     */
    public function test_an_invalid_body_is_rejected_in_one_shape(string $endpoint): void
    {
        $this->actingAdmin();

        $response = $this->postJson($endpoint, [])->assertStatus(422);

        $response->assertJsonStructure(['message', 'errors']);

        $body = $response->json();

        $this->assertIsString($body['message'], "{$endpoint} must explain the failure in `message`.");
        $this->assertNotEmpty($body['errors'], "{$endpoint} must name the fields that failed.");

        // The old hand-rolled responses wrapped this in a success flag; the
        // status code already says it failed.
        $this->assertArrayNotHasKey('success', $body, "{$endpoint} must not add a second way to detect failure.");
    }

    /**
     * @dataProvider writeEndpoints
     */
    public function test_the_errors_are_keyed_by_field_name(string $endpoint): void
    {
        $this->actingAdmin();

        $errors = $this->postJson($endpoint, [])->assertStatus(422)->json('errors');

        foreach ($errors as $field => $messages) {
            $this->assertIsString($field);
            $this->assertIsArray($messages, "{$endpoint}: errors.{$field} must be a list of messages.");
        }
    }

    public function test_updates_are_rejected_in_the_same_shape_as_creates(): void
    {
        $user = $this->actingAdmin();

        $tank = FuelTank::factory()->create(['team_id' => $user->current_team_id]);

        // FuelTank update was one of the hand-rolled ones.
        $this->putJson("/api/v1/fuel/tanks/{$tank->id}", ['fuel_type' => 'plutonium'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors('fuel_type');
    }
}
