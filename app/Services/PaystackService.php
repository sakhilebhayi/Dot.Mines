<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Services\Billing\PaystackWebhookProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    /**
     * Webhook handling lives in PaystackWebhookProcessor; these delegates
     * keep WebhookController's single entry point unchanged.
     */
    /** @param array<string, mixed> $data */
    public function handleSubscriptionCreated(array $data): void
    {
        app(PaystackWebhookProcessor::class)->handleSubscriptionCreated($data);
    }

    /** @param array<string, mixed> $data */
    public function handleSubscriptionDisabled(array $data): void
    {
        app(PaystackWebhookProcessor::class)->handleSubscriptionDisabled($data);
    }

    /** @param array<string, mixed> $data */
    public function handleChargeSuccess(array $data): void
    {
        app(PaystackWebhookProcessor::class)->handleChargeSuccess($data);
    }

    /** @param array<string, mixed> $data */
    public function handlePaymentFailed(array $data): void
    {
        app(PaystackWebhookProcessor::class)->handlePaymentFailed($data);
    }

    /** @param array<string, mixed> $data */
    public function handleInvoiceUpdate(array $data): void
    {
        app(PaystackWebhookProcessor::class)->handleInvoiceUpdate($data);
    }

    protected string $secretKey;

    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret', '');
    }

    /**
     * @return array<mixed>
     */
    protected function get(string $endpoint): array
    {
        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(3, 500, fn (\Throwable $e) => ! ($e instanceof ConnectionException))
            ->acceptJson()
            ->get($this->baseUrl.$endpoint);

        if ($response->failed()) {
            Log::error('Paystack API GET failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function post(string $endpoint, array $data = []): array
    {
        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->connectTimeout(5)
            ->retry(2, 500, fn (\Throwable $e) => ! ($e instanceof ConnectionException))
            ->acceptJson()
            ->post($this->baseUrl.$endpoint, $data);

        if ($response->failed()) {
            Log::error('Paystack API POST failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Create or retrieve a Paystack customer for the team.
     * Returns the customer_code.
     */
    public function createOrGetCustomer(Team $team): ?string
    {
        $subscription = Subscription::where('team_id', $team->id)->first();

        if (($subscription?->paystack_customer_code !== null && $subscription?->paystack_customer_code !== '' && $subscription?->paystack_customer_code !== '0')) {
            return $subscription?->paystack_customer_code;
        }

        $response = $this->post('/customer', [
            'email' => $team->owner->email,
            'first_name' => $team->owner->name,
            'metadata' => ['team_id' => $team->id],
        ]);

        if (empty($response['status'])) {
            Log::error('Paystack customer creation failed', ['team_id' => $team->id]);

            return null;
        }

        return $response['data']['customer_code'] ?? null;
    }

    /**
     * Checkout for machine-allocation purchases (the per-machine pricing
     * model). The total is computed from the allocation plan rows -- the
     * single pricing source of truth -- and billed through a dynamically
     * created Paystack plan for that exact amount (Paystack plan codes are
     * fixed-amount). The requested quantities ride in transaction metadata;
     * NOTHING is granted here -- the signature-verified charge.success
     * webhook is the only grant path (brief §8).
     *
     * @param  array{adt: int, heavy: int}  $quantities
     * @return array{authorization_url: string, reference: string}|null
     */
    public function initializeAllocationPurchase(Team $team, array $quantities, string $billingCycle = 'monthly'): ?array
    {
        $customerCode = $this->createOrGetCustomer($team);

        if ($customerCode === null || $customerCode === '') {
            return null;
        }

        $adtPlan = SubscriptionPlan::query()->where('slug', 'adt-allocation')->where('is_active', true)->first();
        $heavyPlan = SubscriptionPlan::query()->where('slug', 'heavy-allocation')->where('is_active', true)->first();

        if (! $adtPlan || ! $heavyPlan) {
            Log::error('Allocation plans are not seeded; cannot start allocation checkout');

            return null;
        }

        $priceFor = fn (SubscriptionPlan $plan): float => $billingCycle === 'yearly'
            ? (float) $plan->yearly_price
            : $plan->price;

        $total = ((float) $quantities['adt'] * $priceFor($adtPlan)) + ((float) $quantities['heavy'] * $priceFor($heavyPlan));

        if ($total <= 0) {
            return null;
        }

        $amount = (int) round($total * 100.0);
        $interval = $billingCycle === 'yearly' ? 'annually' : 'monthly';

        $planCode = $this->createOrGetPlanForAmount($amount, $interval);

        if ($planCode === null) {
            return null;
        }

        $response = $this->post('/transaction/initialize', [
            'email' => $team->owner->email,
            'amount' => $amount,
            'plan' => $planCode,
            'callback_url' => config('services.paystack.callback_url', route('billing.success')),
            'metadata' => [
                'team_id' => (string) $team->id,
                'billing_cycle' => $billingCycle,
                'customer_code' => $customerCode,
                'allocation_adt' => (string) $quantities['adt'],
                'allocation_heavy' => (string) $quantities['heavy'],
            ],
        ]);

        if (empty($response['status'])) {
            Log::error('Paystack allocation checkout initialization failed', ['team_id' => $team->id]);

            return null;
        }

        return [
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $response['data']['reference'],
        ];
    }

    /**
     * Create (or reuse) a Paystack plan for an exact amount + interval.
     * Codes persist in paystack_dynamic_plans so every checkout for the
     * same total reuses one plan instead of littering the Paystack account.
     */
    public function createOrGetPlanForAmount(int $amount, string $interval): ?string
    {
        /** @var string|null $existing */
        $existing = DB::table('paystack_dynamic_plans')
            ->where('amount', $amount)
            ->where('interval', $interval)
            ->value('plan_code');

        if ($existing !== null) {
            return $existing;
        }

        $response = $this->post('/plan', [
            'name' => sprintf('Dot.Mines Machine Allocations R%s/%s', number_format($amount / 100, 2), $interval),
            'amount' => $amount,
            'interval' => $interval,
            'currency' => 'ZAR',
        ]);

        $planCode = $response['data']['plan_code'] ?? null;

        if (! is_string($planCode) || $planCode === '') {
            Log::error('Paystack dynamic plan creation failed', ['amount' => $amount, 'interval' => $interval]);

            return null;
        }

        DB::table('paystack_dynamic_plans')->insert([
            'amount' => $amount,
            'interval' => $interval,
            'plan_code' => $planCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $planCode;
    }

    /**
     * Generate Paystack's hosted subscription-management link -- the
     * closest equivalent to a billing portal (card updates, cancellation)
     * that Paystack offers.
     */
    public function generateManageLink(Subscription $subscription): ?string
    {
        if (($subscription->paystack_subscription_code === null || $subscription->paystack_subscription_code === '' || $subscription->paystack_subscription_code === '0')) {
            return null;
        }

        $response = $this->get('/subscription/'.urlencode($subscription->paystack_subscription_code).'/manage/link');

        if (empty($response['status']) || empty($response['data']['link'])) {
            Log::error('Paystack manage-link generation failed', [
                'subscription_id' => $subscription->id,
            ]);

            return null;
        }

        return $response['data']['link'];
    }

    /**
     * Handle subscription.create webhook event.
     */
    /** @param array<string, mixed> $data */

    /**
     * Handle subscription.disable / subscription.not_renew webhook event.
     */
    /** @param array<string, mixed> $data */

    /**
     * Handle charge.success webhook event.
     */
    /** @param array<string, mixed> $data */

    /**
     * Handle invoice.update webhook event (invoice paid).
     */
    /** @param array<string, mixed> $data */

    /**
     * Cancel (disable) a Paystack subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): bool
    {
        if (($subscription->paystack_subscription_code === null || $subscription->paystack_subscription_code === '' || $subscription->paystack_subscription_code === '0') || ($subscription->paystack_email_token === null || $subscription->paystack_email_token === '' || $subscription->paystack_email_token === '0')) {
            Log::warning('Cannot cancel subscription: missing code or token', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $response = $this->post('/subscription/disable', [
            'code' => $subscription->paystack_subscription_code,
            'token' => $subscription->paystack_email_token,
        ]);

        if (empty($response['status'])) {
            Log::error('Paystack subscription cancellation failed', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'ends_at' => $immediately ? now() : $subscription->current_period_end,
        ]);

        return true;
    }

    /**
     * Resume (re-enable) a Paystack subscription.
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        if (($subscription->paystack_subscription_code === null || $subscription->paystack_subscription_code === '' || $subscription->paystack_subscription_code === '0') || ($subscription->paystack_email_token === null || $subscription->paystack_email_token === '' || $subscription->paystack_email_token === '0')) {
            Log::warning('Cannot resume subscription: missing code or token', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $response = $this->post('/subscription/enable', [
            'code' => $subscription->paystack_subscription_code,
            'token' => $subscription->paystack_email_token,
        ]);

        if (empty($response['status'])) {
            Log::error('Paystack subscription resume failed', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $subscription->update([
            'canceled_at' => null,
            'ends_at' => null,
            'status' => 'active',
        ]);

        return true;
    }
}
