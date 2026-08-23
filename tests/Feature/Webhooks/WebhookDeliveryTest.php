<?php

namespace Tests\Feature\Webhooks;

use App\Events\AlertTriggered;
use App\Jobs\DeliverWebhookJob;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\MineArea;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\HostResolver;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookEvent;
use App\Services\Webhooks\WebhookSignature;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The push side of the API: events reach the endpoints that asked for them,
 * signed, retried, and never the wrong team's.
 */
class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every URL in these tests resolves to a public address, so the SSRF
        // guard is exercised without a DNS lookup. WebhookUrlGuardTest covers
        // what happens when it does not.
        $this->app->bind(HostResolver::class, fn (): HostResolver => new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['203.0.113.10'];
            }
        });
    }

    private function team(): Team
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        return $team;
    }

    private function alertFor(Team $team): Alert
    {
        $area = MineArea::factory()->create(['team_id' => $team->id]);
        $machine = Machine::factory()->create(['team_id' => $team->id, 'mine_area_id' => $area->id]);

        return Alert::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
        ]);
    }

    public function test_an_event_queues_a_delivery_for_a_subscribed_endpoint(): void
    {
        Queue::fake();

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->subscribedToAlerts()->create(['team_id' => $team->id]);

        app(WebhookDispatcher::class)->dispatch(new AlertTriggered($this->alertFor($team)));

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->firstOrFail();

        $this->assertSame(WebhookEvent::ALERT_TRIGGERED, $delivery->event);
        $this->assertSame(WebhookDelivery::STATUS_PENDING, $delivery->status);
        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_the_payload_carries_the_event_a_timestamp_and_api_shaped_data(): void
    {
        Queue::fake();

        $team = $this->team();
        WebhookEndpoint::factory()->create(['team_id' => $team->id]);
        $alert = $this->alertFor($team);

        app(WebhookDispatcher::class)->dispatch(new AlertTriggered($alert));

        $payload = WebhookDelivery::firstOrFail()->payload;

        $this->assertSame(WebhookEvent::ALERT_TRIGGERED, $payload['event']);
        $this->assertNotEmpty($payload['occurred_at']);

        // The object is the same shape the REST API returns, so one parser
        // handles both.
        $this->assertSame($alert->id, $payload['data']['id']);
        $this->assertArrayHasKey('priority', $payload['data']);
        $this->assertArrayHasKey('triggered_at', $payload['data']);
    }

    public function test_an_endpoint_never_receives_another_teams_events(): void
    {
        Queue::fake();

        $mine = $this->team();
        $someoneElse = $this->team();

        $theirs = WebhookEndpoint::factory()->create(['team_id' => $someoneElse->id]);

        app(WebhookDispatcher::class)->dispatch(new AlertTriggered($this->alertFor($mine)));

        $this->assertSame(
            0,
            WebhookDelivery::where('webhook_endpoint_id', $theirs->id)->count(),
            'A webhook carrying another team\'s data is a breach, not a bug.'
        );
    }

    public function test_endpoints_only_get_the_events_they_subscribed_to(): void
    {
        Queue::fake();

        $team = $this->team();
        $wantsGeofences = WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::GEOFENCE_ENTERED])
            ->create(['team_id' => $team->id]);
        $wantsEverything = WebhookEndpoint::factory()->create(['team_id' => $team->id]);

        app(WebhookDispatcher::class)->dispatch(new AlertTriggered($this->alertFor($team)));

        $this->assertSame(0, WebhookDelivery::where('webhook_endpoint_id', $wantsGeofences->id)->count());
        $this->assertSame(1, WebhookDelivery::where('webhook_endpoint_id', $wantsEverything->id)->count());
    }

    public function test_an_inactive_endpoint_receives_nothing(): void
    {
        Queue::fake();

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->inactive()->create(['team_id' => $team->id]);

        app(WebhookDispatcher::class)->dispatch(new AlertTriggered($this->alertFor($team)));

        $this->assertSame(0, WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->count());
    }

    public function test_a_successful_delivery_is_signed_and_recorded(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame(1, $delivery->attempts);

        Http::assertSent(function ($request) use ($endpoint): bool {
            $signature = $request->header(WebhookSignature::HEADER)[0] ?? '';

            // Verified exactly the way the docs tell a receiver to do it.
            return $this->verifyLikeAReceiver($signature, $request->body(), $endpoint->secret)
                && ($request->header('X-Mines-Event')[0] ?? '') === WebhookEvent::ALERT_TRIGGERED;
        });
    }

    public function test_the_signature_does_not_verify_against_a_different_secret(): void
    {
        $body = '{"event":"alert.triggered"}';
        $header = WebhookSignature::header($body, 'whsec_real');

        $this->assertTrue($this->verifyLikeAReceiver($header, $body, 'whsec_real'));
        $this->assertFalse($this->verifyLikeAReceiver($header, $body, 'whsec_other'));

        // A tampered body must not verify either -- the signature covers it.
        $this->assertFalse($this->verifyLikeAReceiver($header, $body.'x', 'whsec_real'));
    }

    public function test_an_old_signature_is_rejected_as_a_replay(): void
    {
        $body = '{"event":"alert.triggered"}';
        $stale = WebhookSignature::header($body, 'whsec_real', time() - 3600);

        $this->assertFalse(
            $this->verifyLikeAReceiver($stale, $body, 'whsec_real'),
            'The timestamp is inside the signed string so a captured request cannot be replayed later.'
        );
    }

    public function test_a_failed_attempt_schedules_a_retry_rather_than_giving_up(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('nope', 500)]);

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_PENDING, $delivery->status, 'One 500 must not end the delivery.');
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertStringContainsString('500', (string) $delivery->error);

        // The worker runs --tries=1, so the retry has to be a job we queue
        // ourselves rather than a framework retry.
        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_a_delivery_gives_up_after_the_last_attempt(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);
        $delivery->update(['attempts' => WebhookDelivery::MAX_ATTEMPTS - 1]);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(WebhookDelivery::MAX_ATTEMPTS, $delivery->attempts);
        $this->assertSame(1, $endpoint->refresh()->consecutive_failures);
    }

    public function test_an_endpoint_that_keeps_failing_switches_itself_off(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->onLastLegs()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);
        $delivery->update(['attempts' => WebhookDelivery::MAX_ATTEMPTS - 1]);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        $endpoint->refresh();
        $this->assertFalse($endpoint->is_active, 'A receiver dead for hours must stop queueing doomed jobs.');
        $this->assertNotNull($endpoint->auto_disabled_at);
    }

    public function test_a_success_clears_the_failure_count(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->onLastLegs()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        $endpoint->refresh();
        $this->assertSame(0, $endpoint->consecutive_failures);
        $this->assertTrue($endpoint->is_active);
        $this->assertNotNull($endpoint->last_success_at);
    }

    public function test_a_url_that_turned_internal_since_it_was_saved_is_not_sent_to(): void
    {
        Http::fake();

        // DNS is not a promise. A host that resolved publicly when the
        // endpoint was created can point at 127.0.0.1 by the time the job
        // runs, so the guard runs again here rather than only at save time.
        $this->app->bind(HostResolver::class, fn (): HostResolver => new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return ['127.0.0.1'];
            }
        });

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        Http::assertNothingSent();
        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->refresh()->status);
    }

    public function test_a_redirect_is_not_treated_as_a_successful_delivery(): void
    {
        Queue::fake();

        // The client sends with allow_redirects => false, so a 302 pointing
        // at an internal address is never followed -- it comes back as a 302
        // and counts as a failed attempt. This asserts the second half; the
        // first is a client option a faked response cannot exercise.
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);

        $team = $this->team();
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $team->id]);
        $delivery = $this->pendingDelivery($endpoint);

        app(DeliverWebhookJob::class, ['deliveryId' => $delivery->id])->handle(app(WebhookUrlGuard::class));

        $delivery->refresh();
        $this->assertSame(302, $delivery->response_status);
        $this->assertNotSame(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        Http::assertSentCount(1);
    }

    public function test_the_secret_is_never_exposed_by_the_model(): void
    {
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $this->team()->id]);

        $this->assertArrayNotHasKey('secret', $endpoint->toArray());
        $this->assertStringNotContainsString($endpoint->secret, json_encode($endpoint) ?: '');
    }

    public function test_the_secret_is_encrypted_at_rest(): void
    {
        $endpoint = WebhookEndpoint::factory()->create(['team_id' => $this->team()->id]);

        $stored = (string) \DB::table('webhook_endpoints')->where('id', $endpoint->id)->value('secret');

        $this->assertNotSame($endpoint->secret, $stored);
        $this->assertStringNotContainsString('whsec_', $stored);
    }

    public function test_every_advertised_event_is_one_the_app_actually_dispatches(): void
    {
        // An event catalogue that lists something nothing fires is worse than
        // a short one: the integrator builds a handler, sees nothing, and
        // cannot tell whose fault it is.
        foreach (array_keys(WebhookEvent::SOURCES) as $eventClass) {
            $this->assertTrue(class_exists($eventClass));

            $dispatchedFrom = shell_exec(
                'grep -rl '.escapeshellarg(class_basename($eventClass)).' '
                .escapeshellarg(app_path()).' | grep -v Events/ | grep -v Webhooks/ || true'
            );

            $this->assertNotEmpty(
                trim((string) $dispatchedFrom),
                class_basename($eventClass).' is advertised as a webhook event but nothing dispatches it.'
            );
        }

        $this->assertSame(
            count(WebhookEvent::SOURCES),
            count(WebhookEvent::CATALOGUE),
            'Every source event needs a description, and every described event needs a source.'
        );
    }

    /**
     * Verify a signature the way a receiver has to: from the header, the raw
     * body and the shared secret, with no help from our code.
     *
     * This is the snippet the documentation publishes. Keeping the real
     * implementation out of it means a mistake in what we tell integrators
     * shows up here as a failure, instead of our helper agreeing with itself.
     */
    private function verifyLikeAReceiver(string $header, string $body, string $secret, int $tolerance = 300): bool
    {
        $parts = [];

        foreach (explode(',', $header) as $piece) {
            $pair = explode('=', trim($piece), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        if (! isset($parts['t'], $parts['v1']) || ! ctype_digit($parts['t'])) {
            return false;
        }

        if (abs(time() - (int) $parts['t']) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $parts['t'].'.'.$body, $secret);

        return hash_equals($expected, $parts['v1']);
    }

    private function pendingDelivery(WebhookEndpoint $endpoint): WebhookDelivery
    {
        return WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => WebhookEvent::ALERT_TRIGGERED,
            'payload' => ['event' => WebhookEvent::ALERT_TRIGGERED, 'occurred_at' => now()->toIso8601String(), 'data' => ['id' => 1]],
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_attempt_at' => now(),
        ]);
    }
}
