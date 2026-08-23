<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Livewire\WebhookManager;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\TeamRoleProvisioner;
use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setting up a webhook without writing code.
 *
 * The API can do all of this, but needing an API token to configure the thing
 * that saves you from calling the API is a chicken-and-egg the person setting
 * up a pager should not have to solve.
 */
class WebhookManagerTest extends TestCase
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
        // admin.2fa guards the whole authenticated web group: an admin
        // without confirmed 2FA is redirected before reaching any page.
        $user->forceFill(['current_team_id' => $team->id, 'two_factor_confirmed_at' => now()])->save();
        TeamRoleProvisioner::assignRole($user, $team, 'admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    public function test_the_page_loads_for_someone_who_manages_integrations(): void
    {
        $this->actingAdmin();

        $this->get(route('integrations.webhooks'))
            ->assertOk()
            ->assertSeeLivewire(WebhookManager::class);
    }

    public function test_an_endpoint_can_be_created_and_the_secret_is_shown_once(): void
    {
        $user = $this->actingAdmin();

        $component = Livewire::test(WebhookManager::class)
            ->set('url', 'https://hooks.example.com/mines')
            ->set('description', 'Ops pager')
            ->set('subscribeToAll', true)
            ->call('create');

        $endpoint = WebhookEndpoint::where('team_id', $user->current_team_id)->firstOrFail();
        $this->assertSame(['*'], $endpoint->events);

        // Shown once, in the response to creating it, and never read back.
        $secret = $component->get('newSecret');
        $this->assertNotEmpty($secret);
        $component->call('dismissSecret')->assertSet('newSecret', null);
    }

    public function test_specific_events_can_be_chosen(): void
    {
        $user = $this->actingAdmin();

        Livewire::test(WebhookManager::class)
            ->set('url', 'https://hooks.example.com/mines')
            ->set('subscribeToAll', false)
            ->set('selectedEvents', [WebhookEvent::ALERT_TRIGGERED])
            ->call('create');

        $this->assertSame(
            [WebhookEvent::ALERT_TRIGGERED],
            WebhookEndpoint::where('team_id', $user->current_team_id)->firstOrFail()->events
        );
    }

    public function test_choosing_no_events_is_refused_rather_than_creating_a_silent_endpoint(): void
    {
        $this->actingAdmin();

        Livewire::test(WebhookManager::class)
            ->set('url', 'https://hooks.example.com/mines')
            ->set('subscribeToAll', false)
            ->set('selectedEvents', [])
            ->call('create')
            ->assertHasErrors('selectedEvents');

        $this->assertSame(0, WebhookEndpoint::withoutTeamFilter()->count());
    }

    public function test_an_internal_url_is_refused_with_the_reason(): void
    {
        $this->actingAdmin();

        $this->app->bind(HostResolver::class, fn (): HostResolver => new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['169.254.169.254'];
            }
        });

        Livewire::test(WebhookManager::class)
            ->set('url', 'https://metadata.example.com/hook')
            ->call('create')
            ->assertHasErrors('url');

        $this->assertSame(0, WebhookEndpoint::withoutTeamFilter()->count());
    }

    public function test_re_enabling_an_auto_disabled_endpoint_clears_its_failure_history(): void
    {
        $user = $this->actingAdmin();

        $endpoint = WebhookEndpoint::factory()->create([
            'team_id' => $user->current_team_id,
            'is_active' => false,
            'auto_disabled_at' => now(),
            'consecutive_failures' => WebhookEndpoint::FAILURES_BEFORE_AUTO_DISABLE,
        ]);

        Livewire::test(WebhookManager::class)->call('toggleActive', $endpoint->id);

        $endpoint->refresh();
        $this->assertTrue($endpoint->is_active);
        $this->assertSame(0, $endpoint->consecutive_failures);
        $this->assertNull($endpoint->auto_disabled_at);
    }

    public function test_a_test_delivery_can_be_sent_from_the_page(): void
    {
        Queue::fake();
        $user = $this->actingAdmin();

        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $user->current_team_id]);

        Livewire::test(WebhookManager::class)
            ->call('sendTest', $endpoint->id)
            ->assertSet('viewingDeliveriesFor', $endpoint->id);

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_the_page_only_lists_this_teams_endpoints(): void
    {
        $this->actingAdmin();

        $otherTeam = Team::factory()->create();
        WebhookEndpoint::factory()->create(['team_id' => $otherTeam->id, 'url' => 'https://not-ours.example.com/hook']);

        Livewire::test(WebhookManager::class)->assertDontSee('not-ours.example.com');
    }

    public function test_someone_without_the_integrations_permission_cannot_open_the_page(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);
        TeamRoleProvisioner::assignRole($user, $team, 'operator');
        $this->actingAs($user->fresh());

        $this->get(route('integrations.webhooks'))->assertForbidden();
    }
}
