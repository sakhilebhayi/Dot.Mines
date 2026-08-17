<?php

namespace Tests\Feature;

use App\Livewire\IntegrationManager;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * IntegrationServiceTest covers the underlying service in isolation; this
 * proves the real user-facing path -- clicking "Test Connection" in
 * IntegrationManager -- works end to end through the same chain of bugs
 * (double-decoded credentials, undefined makeRequest()/logError(), wrong
 * Machine column names) that made every integration test/sync fail before.
 */
class IntegrationManagerConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_testing_a_real_manufacturer_connection_marks_it_connected(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $team->id,
            'status' => 'disconnected',
            'credentials' => ['api_key' => 'key', 'base_url' => 'https://api.example.test'],
        ]);

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->call('testConnection', $integration->id)
            ->assertSet('testResult.success', true);

        $this->assertSame('connected', $integration->fresh()->status);
    }

    public function test_admin_testing_an_unimplemented_manufacturer_gets_an_honest_failure_not_a_crash(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        $integration = Integration::factory()->forProvider('case')->create([
            'team_id' => $team->id,
            'status' => 'disconnected',
        ]);

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->call('testConnection', $integration->id)
            ->assertSet('testResult.success', false)
            ->assertOk();

        $this->assertSame('disconnected', $integration->fresh()->status);
    }

    /**
     * credentials/config are cast to json/encrypted:json on the model, which
     * already encodes on save -- createIntegration() used to pre-encode them
     * with json_encode() too, double-encoding so every ->credentials read
     * back a PHP string instead of an array and every manufacturer service's
     * $credentials['key'] access silently failed.
     */
    public function test_creating_an_integration_stores_credentials_as_a_real_array_not_double_encoded_json(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->set('formData.provider', 'hitachi')
            ->set('formData.name', 'Hitachi Fleet')
            ->set('formData.connection_type', 'api')
            ->set('formData.sync_frequency', 'manual')
            ->set('formData.credentials.api_key', 'real-key')
            ->set('formData.credentials.api_secret', 'real-secret')
            ->call('createIntegration');

        $integration = Integration::firstWhere('name', 'Hitachi Fleet');
        $this->assertNotNull($integration);
        $this->assertIsArray($integration->credentials);
        $this->assertSame('real-key', $integration->credentials['api_key']);
        $this->assertSame('real-secret', $integration->credentials['api_secret']);
        $this->assertIsArray($integration->config);
        $this->assertSame('manual', $integration->config['sync_frequency']);
    }

    /**
     * Bell uses OAuth2 Resource Owner Password Credentials (username/
     * password/client secret), not the api_key/api_secret pair every other
     * provider's form collects -- the shared validation used to require
     * api_key/api_secret unconditionally, which would have rejected every
     * real Bell submission before it ever reached the service.
     */
    public function test_creating_a_bell_integration_accepts_its_own_credential_fields_and_defaults_client_id(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->set('formData.provider', 'bell')
            ->set('formData.name', 'AfriCoal Bell Fleet')
            ->set('formData.connection_type', 'api')
            ->set('formData.sync_frequency', 'manual')
            ->set('formData.credentials.username', 'katisot-fleetauth@bell.co.za')
            ->set('formData.credentials.password', 'real-password')
            ->set('formData.credentials.client_secret', 'real-client-secret')
            ->call('createIntegration')
            ->assertHasNoErrors();

        $integration = Integration::firstWhere('name', 'AfriCoal Bell Fleet');
        $this->assertNotNull($integration);
        $this->assertSame('katisot-fleetauth@bell.co.za', $integration->credentials['username']);
        $this->assertSame('real-password', $integration->credentials['password']);
        $this->assertSame('real-client-secret', $integration->credentials['client_secret']);
        // Left blank in the form -- defaulted to Bell's own published client_id.
        $this->assertSame('ISO_Export_Service', $integration->credentials['client_id']);
    }

    /**
     * machines_count and last_error were real, already-tracked columns on
     * Integration that loadIntegrations() never mapped into the view data at
     * all -- an integration could have a real sync failure recorded and the
     * UI would only ever show a generic "Failed" badge with no detail.
     */
    public function test_machines_count_and_last_error_are_surfaced(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        Integration::factory()->forProvider('volvo')->create([
            'team_id' => $team->id,
            'status' => 'error',
            'last_sync_status' => 'failed',
            'machines_count' => 7,
            'last_error' => 'Sync failed. Please try again.',
        ]);

        $component = Livewire::actingAs($owner)->test(IntegrationManager::class);
        $integration = $component->get('integrations')[0];

        $this->assertSame(7, $integration['machines_count']);
        $this->assertSame('Sync failed. Please try again.', $integration['last_error']);
    }
}
