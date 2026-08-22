<?php

namespace Tests\Feature;

use App\Livewire\BillingPortal;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coverage for the Stripe -> Paystack billing port: webhook signature and
 * replay hardening, subscription lifecycle provisioning driven by webhook
 * events, and the Livewire checkout flow redirecting to Paystack's hosted
 * authorization page. The mega-branch shipped PaystackService without any
 * tests, and its BillingPortal still called the deleted StripeService.
 */
class PaystackBillingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_paystack_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret' => self::SECRET]);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postWebhook(array $event, ?string $signature = null): TestResponse
    {
        $payload = json_encode($event);
        assert(is_string($payload));

        return $this->call(
            'POST',
            '/webhooks/paystack',
            [],
            [],
            [],
            [
                'HTTP_X-Paystack-Signature' => $signature ?? hash_hmac('sha512', $payload, self::SECRET),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }

    private function makePlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 4999,
            'yearly_price' => 49990,
            'paystack_plan_code' => 'PLN_pro_monthly',
            'paystack_yearly_plan_code' => 'PLN_pro_yearly',
            'features' => ['telemetry'],
            'is_active' => true,
        ]);
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        $response = $this->postWebhook(['event' => 'charge.success', 'data' => []], 'not-a-real-signature');

        $response->assertStatus(400);
    }

    public function test_webhook_rejects_a_stale_replayed_event(): void
    {
        $response = $this->postWebhook([
            'event' => 'charge.success',
            'data' => ['createdAt' => now()->subMinutes(10)->toIso8601String()],
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_returns_500_when_no_secret_is_configured(): void
    {
        config(['services.paystack.secret' => null]);

        $response = $this->postWebhook(['event' => 'charge.success', 'data' => []], 'anything');

        $response->assertStatus(500);
    }

    public function test_subscription_create_webhook_provisions_the_team_subscription(): void
    {
        $team = Team::factory()->create();
        $plan = $this->makePlan();

        $response = $this->postWebhook([
            'event' => 'subscription.create',
            'data' => [
                'subscription_code' => 'SUB_abc123',
                'email_token' => 'tok_xyz',
                'status' => 'active',
                'createdAt' => now()->toIso8601String(),
                'next_payment_date' => now()->addMonth()->toIso8601String(),
                'customer' => ['customer_code' => 'CUS_def456'],
                'plan' => ['plan_code' => 'PLN_pro_monthly'],
                'metadata' => ['team_id' => (string) $team->id, 'plan_id' => (string) $plan->id],
            ],
        ]);

        $response->assertOk();

        $subscription = Subscription::where('paystack_subscription_code', 'SUB_abc123')->first();
        $this->assertNotNull($subscription, 'The webhook should have provisioned a subscription row.');
        $this->assertSame($team->id, $subscription->team_id);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertSame('CUS_def456', $subscription->paystack_customer_code);
        $this->assertSame('active', $subscription->status);
    }

    public function test_subscription_disable_webhook_cancels_the_subscription(): void
    {
        $team = Team::factory()->create();
        $plan = $this->makePlan();
        Subscription::create([
            'team_id' => $team->id,
            'subscription_plan_id' => $plan->id,
            'paystack_subscription_code' => 'SUB_abc123',
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->postWebhook([
            'event' => 'subscription.disable',
            'data' => [
                'subscription_code' => 'SUB_abc123',
                'createdAt' => now()->toIso8601String(),
            ],
        ]);

        $response->assertOk();
        $subscription = Subscription::where('paystack_subscription_code', 'SUB_abc123')->firstOrFail();
        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_allocation_purchase_redirects_to_the_paystack_hosted_checkout(): void
    {
        // The tier-select subscribe() flow was replaced by the per-machine
        // allocation purchase (billing brief, 2026-08-22); checkout still
        // rides the same PaystackService transaction/initialize pipeline.
        Http::fake([
            'https://api.paystack.co/customer' => Http::response([
                'status' => true,
                'data' => ['customer_code' => 'CUS_def456'],
            ]),
            'https://api.paystack.co/plan' => Http::response([
                'status' => true,
                'data' => ['plan_code' => 'PLN_dyn'],
            ]),
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'reference' => 'ref_123',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        Livewire::actingAs($user)
            ->test(BillingPortal::class)
            ->set('qtyAdt', 2)
            ->set('billingCycle', 'monthly')
            ->call('purchase')
            ->assertRedirect('https://checkout.paystack.com/abc123');
    }
}
