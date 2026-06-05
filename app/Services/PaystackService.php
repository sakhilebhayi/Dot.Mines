<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
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

        if ($subscription?->paystack_customer_code) {
            return $subscription->paystack_customer_code;
        }

        $response = $this->post('/customer', [
            'email' => $team->owner->email,
            'first_name' => $team->owner->name,
            'metadata' => ['team_id' => $team->id],
        ]);

        if (empty($response['status']) || ! $response['status']) {
            Log::error('Paystack customer creation failed', ['team_id' => $team->id]);

            return null;
        }

        return $response['data']['customer_code'] ?? null;
    }

    /**
     * Initialize a Paystack transaction for a subscription plan.
     * Returns ['authorization_url' => ..., 'reference' => ...].
     */
    public function initializeTransaction(
        Team $team,
        SubscriptionPlan $plan,
        string $billingCycle = 'monthly'
    ): ?array {
        $customerCode = $this->createOrGetCustomer($team);

        if (! $customerCode) {
            return null;
        }

        $planCode = $billingCycle === 'yearly'
            ? $plan->paystack_yearly_plan_code
            : $plan->paystack_plan_code;

        if (! $planCode) {
            Log::warning('No Paystack plan code configured for plan', [
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
            ]);

            return null;
        }

        // Paystack amounts are in smallest currency unit (cents for ZAR)
        $amount = (int) (($billingCycle === 'yearly' ? $plan->yearly_price : $plan->price) * 100);

        $response = $this->post('/transaction/initialize', [
            'email' => $team->owner->email,
            'amount' => $amount,
            'plan' => $planCode,
            'callback_url' => config('services.paystack.callback_url', route('billing.success')),
            'metadata' => [
                'team_id' => (string) $team->id,
                'plan_id' => (string) $plan->id,
                'billing_cycle' => $billingCycle,
                'customer_code' => $customerCode,
            ],
        ]);

        if (empty($response['status']) || ! $response['status']) {
            Log::error('Paystack transaction initialization failed', [
                'team_id' => $team->id,
                'plan_id' => $plan->id,
            ]);

            return null;
        }

        return [
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $response['data']['reference'],
        ];
    }

    /**
     * Verify a Paystack transaction by reference.
     */
    public function verifyTransaction(string $reference): ?array
    {
        $response = $this->get('/transaction/verify/'.urlencode($reference));

        if (empty($response['status']) || ! $response['status']) {
            Log::error('Paystack transaction verification failed', ['reference' => $reference]);

            return null;
        }

        return $response['data'] ?? null;
    }

    /**
     * Handle subscription.create webhook event.
     */
    public function handleSubscriptionCreated(array $data): void
    {
        try {
            $subscriptionData = $data['data'];
            $metadata = $subscriptionData['metadata'] ?? [];
            $teamId = $metadata['team_id'] ?? null;

            $planCode = $subscriptionData['plan']['plan_code'] ?? null;
            $planId = $metadata['plan_id'] ?? null;

            if (! $planId && $planCode) {
                $planId = SubscriptionPlan::where('paystack_plan_code', $planCode)
                    ->orWhere('paystack_yearly_plan_code', $planCode)
                    ->value('id');
            }

            if (! $teamId) {
                Log::warning('Missing team_id in subscription.create webhook', [
                    'subscription_code' => $subscriptionData['subscription_code'] ?? 'unknown',
                ]);

                return;
            }

            $nextPaymentDate = isset($subscriptionData['next_payment_date'])
                ? date('Y-m-d H:i:s', (int) strtotime((string) $subscriptionData['next_payment_date']))
                : null;

            Subscription::updateOrCreate(
                ['paystack_subscription_code' => $subscriptionData['subscription_code']],
                [
                    'team_id' => $teamId,
                    'subscription_plan_id' => $planId,
                    'paystack_customer_code' => $subscriptionData['customer']['customer_code'] ?? null,
                    'paystack_email_token' => $subscriptionData['email_token'] ?? null,
                    'status' => $this->mapPaystackStatus($subscriptionData['status'] ?? 'active'),
                    'current_period_start' => now()->toDateTimeString(),
                    'current_period_end' => $nextPaymentDate,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription.create webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Handle subscription.disable / subscription.not_renew webhook event.
     */
    public function handleSubscriptionDisabled(array $data): void
    {
        try {
            $subscriptionData = $data['data'];
            $subscription = Subscription::where(
                'paystack_subscription_code',
                $subscriptionData['subscription_code'] ?? ''
            )->first();

            if (! $subscription) {
                Log::warning('Subscription not found for disable event', [
                    'code' => $subscriptionData['subscription_code'] ?? 'unknown',
                ]);

                return;
            }

            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'ends_at' => $subscription->current_period_end,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription.disable webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Handle charge.success webhook event.
     */
    public function handleChargeSuccess(array $data): void
    {
        try {
            $charge = $data['data'];
            $metadata = $charge['metadata'] ?? [];
            $teamId = $metadata['team_id'] ?? null;

            if (! $teamId) {
                return;
            }

            $subscription = Subscription::where('team_id', $teamId)->first();

            Payment::create([
                'team_id' => $teamId,
                'subscription_id' => $subscription?->id,
                'paystack_reference' => $charge['reference'],
                'paystack_invoice_id' => null,
                'amount' => ($charge['amount'] ?? 0) / 100,
                'currency' => strtoupper($charge['currency'] ?? 'ZAR'),
                'status' => 'succeeded',
                'payment_method' => $charge['channel'] ?? 'card',
                'paid_at' => now(),
                'metadata' => $metadata,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle charge.success webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Handle invoice.update webhook event (invoice paid).
     */
    public function handleInvoiceUpdate(array $data): void
    {
        try {
            $invoiceData = $data['data'];
            $subscriptionCode = $invoiceData['subscription']['subscription_code'] ?? null;

            $subscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();

            if (! $subscription) {
                return;
            }

            $txReference = $invoiceData['transaction']['reference'] ?? null;
            $payment = $txReference
                ? Payment::where('paystack_reference', $txReference)->first()
                : null;

            Invoice::updateOrCreate(
                ['paystack_invoice_code' => $invoiceData['invoice_code']],
                [
                    'team_id' => $subscription->team_id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment?->id,
                    'invoice_number' => $invoiceData['invoice_code'],
                    'subtotal' => ($invoiceData['amount'] ?? 0) / 100,
                    'tax' => 0,
                    'total' => ($invoiceData['amount'] ?? 0) / 100,
                    'currency' => strtoupper($invoiceData['currency'] ?? 'ZAR'),
                    'status' => ($invoiceData['paid'] ?? false) ? 'paid' : 'open',
                    'issued_at' => isset($invoiceData['created_at'])
                        ? date('Y-m-d H:i:s', (int) strtotime((string) $invoiceData['created_at']))
                        : now()->toDateTimeString(),
                    'paid_at' => ($invoiceData['paid'] ?? false) ? now()->toDateTimeString() : null,
                    'pdf_url' => null,
                    'line_items' => [$invoiceData],
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to handle invoice.update webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Cancel (disable) a Paystack subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): bool
    {
        if (! $subscription->paystack_subscription_code || ! $subscription->paystack_email_token) {
            Log::warning('Cannot cancel subscription: missing code or token', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $response = $this->post('/subscription/disable', [
            'code' => $subscription->paystack_subscription_code,
            'token' => $subscription->paystack_email_token,
        ]);

        if (empty($response['status']) || ! $response['status']) {
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
        if (! $subscription->paystack_subscription_code || ! $subscription->paystack_email_token) {
            Log::warning('Cannot resume subscription: missing code or token', [
                'subscription_id' => $subscription->id,
            ]);

            return false;
        }

        $response = $this->post('/subscription/enable', [
            'code' => $subscription->paystack_subscription_code,
            'token' => $subscription->paystack_email_token,
        ]);

        if (empty($response['status']) || ! $response['status']) {
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

    /**
     * Verify the HMAC-SHA512 webhook signature from Paystack.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $payload, config('services.paystack.secret', ''));

        return hash_equals($expected, $signature);
    }

    /**
     * Map Paystack subscription status to our internal status.
     */
    protected function mapPaystackStatus(string $status): string
    {
        return match ($status) {
            'active' => 'active',
            'non-renewing' => 'canceled',
            'attention' => 'past_due',
            'cancelled', 'canceled', 'disabled' => 'canceled',
            'completed' => 'expired',
            default => 'expired',
        };
    }
}
