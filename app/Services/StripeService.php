<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe Integration Service
 *
 * Handles all Stripe API interactions for subscriptions and payments
 * Note: Requires STRIPE_SECRET environment variable
 */
class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create or retrieve Stripe customer for team
     */
    public function createOrGetCustomer(Team $team): ?string
    {
        try {
            // Check if team already has a Stripe customer ID
            $subscription = Subscription::where('team_id', $team->id)->first();

            if ($subscription && $subscription->stripe_customer_id) {
                return $subscription->stripe_customer_id;
            }

            // Create new Stripe customer
            $customer = $this->stripe->customers->create([
                'name' => $team->name,
                'email' => $team->owner->email,
                'metadata' => [
                    'team_id' => $team->id,
                ],
            ]);

            return $customer->id;

        } catch (ApiErrorException $e) {
            Log::error('Stripe customer creation failed', [
                'team_id' => $team->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create subscription checkout session
     */
    public function createCheckoutSession(
        Team $team,
        SubscriptionPlan $plan,
        string $billingCycle = 'monthly'
    ): ?array {
        try {
            $customerId = $this->createOrGetCustomer($team);

            if (! $customerId) {
                return null;
            }

            $priceId = $billingCycle === 'yearly'
                ? $plan->stripe_yearly_price_id
                : $plan->stripe_price_id;

            if (! $priceId) {
                Log::warning('No Stripe price ID configured for plan', [
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                ]);

                return null;
            }

            $session = $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.index'),
                'metadata' => [
                    'team_id' => $team->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'team_id' => $team->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create billing portal session
     */
    public function createBillingPortalSession(Team $team): ?string
    {
        try {
            $subscription = Subscription::where('team_id', $team->id)->first();

            if (! $subscription || ! $subscription->stripe_customer_id) {
                return null;
            }

            $session = $this->stripe->billingPortal->sessions->create([
                'customer' => $subscription->stripe_customer_id,
                'return_url' => route('billing.index'),
            ]);

            return $session->url;

        } catch (ApiErrorException $e) {
            Log::error('Stripe billing portal session creation failed', [
                'team_id' => $team->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Handle subscription created webhook
     */
    public function handleSubscriptionCreated(array $data): void
    {
        try {
            $stripeSubscription = $data['data']['object'];
            $teamId = $stripeSubscription['metadata']['team_id'] ?? null;
            $planId = $stripeSubscription['metadata']['plan_id'] ?? null;

            if (! $teamId || ! $planId) {
                Log::warning('Missing metadata in subscription webhook', $data);

                return;
            }

            Subscription::updateOrCreate(
                ['stripe_subscription_id' => $stripeSubscription['id']],
                [
                    'team_id' => $teamId,
                    'subscription_plan_id' => $planId,
                    'stripe_customer_id' => $stripeSubscription['customer'],
                    'status' => $this->mapStripeStatus($stripeSubscription['status']),
                    'current_period_start' => $stripeSubscription['current_period_start']
                        ? date('Y-m-d H:i:s', $stripeSubscription['current_period_start'])
                        : null,
                    'current_period_end' => $stripeSubscription['current_period_end']
                        ? date('Y-m-d H:i:s', $stripeSubscription['current_period_end'])
                        : null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription created webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Handle subscription updated webhook
     */
    public function handleSubscriptionUpdated(array $data): void
    {
        try {
            $stripeSubscription = $data['data']['object'];

            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();

            if (! $subscription) {
                Log::warning('Subscription not found for update', [
                    'stripe_subscription_id' => $stripeSubscription['id'],
                ]);

                return;
            }

            $subscription->update([
                'status' => $this->mapStripeStatus($stripeSubscription['status']),
                'current_period_start' => $stripeSubscription['current_period_start']
                    ? date('Y-m-d H:i:s', $stripeSubscription['current_period_start'])
                    : null,
                'current_period_end' => $stripeSubscription['current_period_end']
                    ? date('Y-m-d H:i:s', $stripeSubscription['current_period_end'])
                    : null,
                'canceled_at' => $stripeSubscription['canceled_at']
                    ? date('Y-m-d H:i:s', $stripeSubscription['canceled_at'])
                    : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle subscription updated webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Handle payment succeeded webhook
     */
    public function handlePaymentSucceeded(array $data): void
    {
        try {
            $paymentIntent = $data['data']['object'];

            $subscription = Subscription::where('stripe_customer_id', $paymentIntent['customer'])->first();

            if (! $subscription) {
                Log::warning('Subscription not found for payment', [
                    'customer_id' => $paymentIntent['customer'],
                ]);

                return;
            }

            Payment::create([
                'team_id' => $subscription->team_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $paymentIntent['id'],
                'amount' => $paymentIntent['amount'] / 100, // Convert from cents
                'currency' => strtoupper($paymentIntent['currency']),
                'status' => 'succeeded',
                'payment_method' => $paymentIntent['payment_method_types'][0] ?? 'card',
                'paid_at' => now(),
                'metadata' => $paymentIntent['metadata'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle payment succeeded webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Handle invoice paid webhook
     */
    public function handleInvoicePaid(array $data): void
    {
        try {
            $invoice = $data['data']['object'];

            $subscription = Subscription::where('stripe_subscription_id', $invoice['subscription'])->first();

            if (! $subscription) {
                return;
            }

            $payment = Payment::where('stripe_payment_intent_id', $invoice['payment_intent'])->first();

            Invoice::updateOrCreate(
                ['stripe_invoice_id' => $invoice['id']],
                [
                    'team_id' => $subscription->team_id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment?->id,
                    'invoice_number' => $invoice['number'],
                    'subtotal' => $invoice['subtotal'] / 100,
                    'tax' => $invoice['tax'] / 100,
                    'total' => $invoice['total'] / 100,
                    'currency' => strtoupper($invoice['currency']),
                    'status' => 'paid',
                    'issued_at' => $invoice['created'] ? date('Y-m-d H:i:s', $invoice['created']) : null,
                    'paid_at' => now(),
                    'pdf_url' => $invoice['invoice_pdf'],
                    'line_items' => $invoice['lines']['data'] ?? [],
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to handle invoice paid webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): bool
    {
        try {
            if (! $subscription->stripe_subscription_id) {
                return false;
            }

            if ($immediately) {
                $this->stripe->subscriptions->cancel($subscription->stripe_subscription_id);
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => now(),
                ]);
            } else {
                $this->stripe->subscriptions->update(
                    $subscription->stripe_subscription_id,
                    ['cancel_at_period_end' => true]
                );
                $subscription->update([
                    'canceled_at' => now(),
                    'ends_at' => $subscription->current_period_end,
                ]);
            }

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription cancellation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resume canceled subscription
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        try {
            if (! $subscription->stripe_subscription_id) {
                return false;
            }

            $this->stripe->subscriptions->update(
                $subscription->stripe_subscription_id,
                ['cancel_at_period_end' => false]
            );

            $subscription->update([
                'canceled_at' => null,
                'ends_at' => null,
                'status' => 'active',
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription resume failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Map Stripe status to our status
     */
    protected function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'trialing' => 'trial',
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid', 'incomplete', 'incomplete_expired' => 'expired',
            default => 'expired',
        };
    }
}
