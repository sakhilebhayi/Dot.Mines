<?php

namespace Tests\Feature\Api;

use App\Jobs\DeliverWebhookJob;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\TeamRoleProvisioner;
use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Managing where events get pushed, over the API itself.
 */
class WebhookEndpointApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(HostResolver::class, fn (): HostResolver => new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['203.0.113.10'];
            }
        });
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

    public function test_creating_an_endpoint_returns_the_secret_exactly_once(): void
    {
        $this->actingAdmin();

        $response = $this->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.example.com/mines',
            'events' => [WebhookEvent::ALERT_TRIGGERED],
            'description' => 'Ops pager',
        ])->assertCreated();

        $secret = $response->json('secret');
        $this->assertNotEmpty($secret);
        $this->assertStringStartsWith('whsec_', $secret);

        // Every later read must not carry it.
        $id = $response->json('data.id');
        $this->assertNull($this->getJson("/api/v1/webhooks/{$id}")->assertOk()->json('data.secret'));
        $this->assertNull($this->getJson('/api/v1/webhooks')->assertOk()->json('data.0.secret'));
    }

    public function test_a_url_pointing_inside_the_network_is_refused_as_a_validation_error(): void
    {
        $this->actingAdmin();

        $this->app->bind(HostResolver::class, fn (): HostResolver => new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['127.0.0.1'];
            }
        });

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://localhost/hook',
            'events' => ['*'],
        ])->assertStatus(422)->assertJsonValidationErrors('url');

        $this->assertSame(0, WebhookEndpoint::withoutTeamFilter()->count());
    }

    public function test_an_unknown_event_name_is_refused(): void
    {
        $this->actingAdmin();

        // Catching this at creation is the difference between "you subscribed
        // to a typo" and a silent no-op integration.
        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.example.com/mines',
            'events' => ['alert.exploded'],
        ])->assertStatus(422)->assertJsonValidationErrors('events.0');
    }

    public function test_the_list_advertises_the_available_events(): void
    {
        $this->actingAdmin();

        $available = $this->getJson('/api/v1/webhooks')->assertOk()->json('events_available');

        $this->assertSame(WebhookEvent::CATALOGUE, $available);
    }

    public function test_endpoints_are_scoped_to_the_team(): void
    {
        $this->actingAdmin();

        $otherTeam = Team::factory()->create();
        $theirs = WebhookEndpoint::factory()->create(['team_id' => $otherTeam->id]);

        $this->assertSame([], $this->getJson('/api/v1/webhooks')->assertOk()->json('data'));

        // 404 rather than 403: the team scope means the record is not found
        // at all, so the response does not confirm that the id exists in
        // someone else's team.
        $this->getJson("/api/v1/webhooks/{$theirs->id}")->assertNotFound();
    }

    public function test_a_member_without_the_integrations_permission_cannot_manage_webhooks(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'operator');
        Sanctum::actingAs($user->fresh(), ['*']);

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://hooks.example.com/mines',
            'events' => ['*'],
        ])->assertForbidden();
    }

    public function test_reactivating_an_endpoint_clears_its_failure_history(): void
    {
        $user = $this->actingAdmin();

        $endpoint = WebhookEndpoint::factory()->create([
            'team_id' => $user->current_team_id,
            'is_active' => false,
            'consecutive_failures' => WebhookEndpoint::FAILURES_BEFORE_AUTO_DISABLE,
            'auto_disabled_at' => now(),
            'last_failure_reason' => 'Connection refused',
        ]);

        $this->putJson("/api/v1/webhooks/{$endpoint->id}", ['is_active' => true])->assertOk();

        $endpoint->refresh();
        $this->assertTrue($endpoint->is_active);
        $this->assertSame(0, $endpoint->consecutive_failures);
        $this->assertNull($endpoint->auto_disabled_at);
    }

    public function test_a_test_delivery_can_be_sent_on_demand(): void
    {
        Queue::fake();
        $user = $this->actingAdmin();

        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $user->current_team_id]);

        $this->postJson("/api/v1/webhooks/{$endpoint->id}/test")
            ->assertStatus(202)
            ->assertJsonPath('data.event', 'ping');

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_deliveries_can_be_listed_and_filtered(): void
    {
        $user = $this->actingAdmin();
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $user->current_team_id]);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => WebhookEvent::ALERT_TRIGGERED,
            'payload' => ['event' => WebhookEvent::ALERT_TRIGGERED],
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempts' => 5,
            'error' => 'The endpoint responded 500.',
        ]);
        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => WebhookEvent::MACHINE_OFFLINE,
            'payload' => ['event' => WebhookEvent::MACHINE_OFFLINE],
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'attempts' => 1,
            'delivered_at' => now(),
        ]);

        $this->assertCount(2, $this->getJson("/api/v1/webhooks/{$endpoint->id}/deliveries")->assertOk()->json('data'));

        $failed = $this->getJson("/api/v1/webhooks/{$endpoint->id}/deliveries?status=failed")->assertOk()->json('data');
        $this->assertCount(1, $failed);
        $this->assertSame('The endpoint responded 500.', $failed[0]['error']);
    }

    public function test_the_endpoints_are_reachable_unversioned_too(): void
    {
        $user = $this->actingAdmin();
        WebhookEndpoint::factory()->create(['team_id' => $user->current_team_id]);

        // New endpoints inherit both spellings from the shared route file.
        $this->assertSame(
            $this->getJson('/api/v1/webhooks')->assertOk()->json('data'),
            $this->getJson('/api/webhooks')->assertOk()->json('data'),
        );
    }
}
