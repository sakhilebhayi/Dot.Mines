<?php

namespace Tests\Feature\Api;

use App\Models\Integration;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API's wire format is an explicit whitelist (App\Http\Resources), not
 * whatever columns happen to be on the table.
 *
 * Before the resource layer, controllers returned raw Eloquent models, so:
 *  - internal plumbing shipped to consumers (sync_version, allocation_state,
 *    integration_id, manufacturer_id, excavator_id, team_id);
 *  - reports exposed `file_path`, an internal storage location;
 *  - eager-loaded relations serialized whole User models -- email address,
 *    2FA confirmation timestamp, notification preferences -- on endpoints
 *    whose subject was a report, an alert, or a work order;
 *  - the API contract WAS the database schema, so any column rename broke
 *    every client.
 *
 * These tests pin the boundary. Adding a column must not silently add it to
 * the public API.
 */
class ResourceFieldExposureTest extends TestCase
{
    use RefreshDatabase;

    /** Internal plumbing that must never appear in an API payload. */
    private const FORBIDDEN_MACHINE_FIELDS = [
        'team_id',
        'sync_version',
        'allocation_state',
        'integration_id',
        'manufacturer_id',
        'excavator_id',
        'assigned_to_excavator_at',
    ];

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        Sanctum::actingAs($user->fresh(), ['*']);

        return $user->fresh();
    }

    private function seedMachine(User $user): Machine
    {
        $area = MineArea::factory()->create(['team_id' => $user->current_team_id]);

        return Machine::factory()->create([
            'team_id' => $user->current_team_id,
            'mine_area_id' => $area->id,
            'model' => 'B45E',
        ]);
    }

    public function test_machine_payload_exposes_domain_fields_and_hides_plumbing(): void
    {
        $user = $this->actingUser();
        $this->seedMachine($user);

        $row = $this->getJson('/api/machines')->assertOk()->json('data.0');

        foreach (self::FORBIDDEN_MACHINE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $row, "`{$field}` is internal plumbing and must not be exposed by the API.");
        }

        // The domain fields a fleet consumer actually needs are still there.
        foreach (['id', 'name', 'machine_type', 'model', 'status', 'location'] as $field) {
            $this->assertArrayHasKey($field, $row, "`{$field}` is part of the machine contract.");
        }

        $this->assertArrayHasKey('latitude', $row['location']);
        $this->assertArrayHasKey('longitude', $row['location']);
    }

    public function test_reports_do_not_expose_the_internal_storage_path(): void
    {
        $user = $this->actingUser();
        Report::create([
            'team_id' => $user->current_team_id,
            'title' => 'Quarterly production',
            'type' => 'production',
            'format' => 'pdf',
            'status' => 'completed',
            'generated_by' => $user->id,
            'file_path' => 'reports/9/internal-location.pdf',
        ]);

        $row = $this->getJson('/api/reports')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('file_path', $row, 'The internal storage path must not leak; downloads go through /reports/{id}/download.');
        $this->assertArrayNotHasKey('team_id', $row);
        $this->assertSame('Quarterly production', $row['title']);
    }

    public function test_related_users_are_reduced_to_a_summary_without_pii(): void
    {
        $user = $this->actingUser();
        Report::create([
            'team_id' => $user->current_team_id,
            'title' => 'Fuel burn',
            'type' => 'fuel',
            'format' => 'pdf',
            'status' => 'completed',
            'generated_by' => $user->id,
        ]);

        $row = $this->getJson('/api/reports')->assertOk()->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($row['generated_by_user']), 'A referenced user must be reduced to id and name.');

        // The whole payload must not carry the user's private fields anywhere.
        $body = $this->getJson('/api/reports')->getContent();
        foreach (['email', 'two_factor_confirmed_at', 'notification_preferences', 'profile_photo_path'] as $pii) {
            $this->assertStringNotContainsString($pii, (string) $body, "`{$pii}` is PII and must not appear in a report payload.");
        }
    }

    public function test_integration_payloads_never_carry_credentials(): void
    {
        $user = $this->actingUser();
        Integration::create([
            'team_id' => $user->current_team_id,
            'provider' => 'bell',
            'name' => 'Bell Telematics',
            'status' => 'connected',
            'credentials' => ['client_id' => 'ISO_Export_Service', 'client_secret' => 'super-secret-value'],
        ]);

        $body = (string) $this->getJson('/api/integrations')->assertOk()->getContent();

        $this->assertStringNotContainsString('super-secret-value', $body, 'Integration credentials decrypt on read -- they must never be serialized.');
        $this->assertStringNotContainsString('credentials', $body);
        $this->assertStringContainsString('Bell Telematics', $body);
    }

    public function test_timestamps_are_iso8601_across_resources(): void
    {
        $user = $this->actingUser();
        $this->seedMachine($user);

        $row = $this->getJson('/api/machines')->assertOk()->json('data.0');

        // e.g. 2026-08-23T10:48:58+00:00 -- one date format for every endpoint.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $row['created_at'],
            'Timestamps must be ISO-8601 so clients parse one format everywhere.'
        );
    }
}
