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
 * The new one-click "Connect" flow (spec: "the user should not have to
 * configure Fleet and Production separately" -- confirmed in
 * docs/superpowers/plans/2026-08-11-integration-connect-unification.md
 * that this was never actually true; this is the real gap the spec's
 * acceptance criteria still point at: one action does validate + save +
 * enable + sync, and partial capability is reported honestly, not as a
 * blanket failure).
 */
class IntegrationConnectFlowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($owner, $team, 'admin');

        return $owner;
    }

    public function test_connecting_with_valid_credentials_saves_tests_and_syncs_in_one_action(): void
    {
        Http::fake(['*' => Http::response(['machines' => [['id' => 'H1', 'model' => 'ZX350']]], 200)]);

        $owner = $this->actingAdmin();

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->set('formData.provider', 'hitachi')
            ->set('formData.name', 'Hitachi Fleet')
            ->set('formData.connection_type', 'api')
            ->set('formData.sync_frequency', 'manual')
            ->set('formData.credentials.api_key', 'real-key')
            ->set('formData.credentials.api_secret', 'real-secret')
            ->call('connectIntegration')
            ->assertSet('testResult.success', true)
            ->assertSet('showTestModal', true);

        $integration = Integration::firstWhere('name', 'Hitachi Fleet');
        $this->assertNotNull($integration);
        $this->assertSame('connected', $integration->status);
        $this->assertContains('fleet', $integration->capabilities);
    }

    public function test_connecting_with_invalid_credentials_reports_failure_without_leaving_a_connected_row(): void
    {
        Http::fake(['*' => Http::response('', 401)]);

        $owner = $this->actingAdmin();

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->set('formData.provider', 'hitachi')
            ->set('formData.name', 'Bad Creds')
            ->set('formData.connection_type', 'api')
            ->set('formData.sync_frequency', 'manual')
            ->set('formData.credentials.api_key', 'wrong')
            ->set('formData.credentials.api_secret', 'wrong')
            ->call('connectIntegration')
            ->assertSet('testResult.success', false)
            ->assertSet('testResult.message', 'Connection failed — API credentials could not be verified.');

        $integration = Integration::firstWhere('name', 'Bad Creds');
        $this->assertNotNull($integration);
        $this->assertNotSame('connected', $integration->status);
    }

    public function test_retest_connection_re_verifies_an_existing_integration_without_new_credentials(): void
    {
        Http::fake(['*' => Http::response(['machines' => [['id' => 'H1', 'model' => 'ZX350']]], 200)]);

        $owner = $this->actingAdmin();
        $integration = Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $owner->current_team_id,
            'status' => 'error',
            'credentials' => ['api_key' => 'real-key', 'base_url' => 'https://api.example.test'],
        ]);

        Livewire::actingAs($owner)
            ->test(IntegrationManager::class)
            ->call('retestConnection', $integration->id)
            ->assertSet('testResult.success', true);

        $this->assertSame('connected', $integration->fresh()->status);
        // The credentials already on the row are exactly what got re-used --
        // nothing in this call path accepts new credential input.
        $this->assertSame('real-key', $integration->fresh()->credentials['api_key']);
    }

    public function test_credentials_never_appear_in_the_integrations_array_state(): void
    {
        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $owner = $this->actingAdmin();
        Integration::factory()->forProvider('hitachi')->create([
            'team_id' => $owner->current_team_id,
            'credentials' => ['api_key' => 'super-secret-key', 'api_secret' => 'super-secret-value'],
        ]);

        $component = Livewire::actingAs($owner)->test(IntegrationManager::class);

        $this->assertStringNotContainsString('super-secret-key', json_encode($component->get('integrations')));
        $this->assertStringNotContainsString('super-secret-value', json_encode($component->get('integrations')));
    }
}
