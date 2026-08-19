<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhook
     */
    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Verify webhook signature if secret is configured
        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret) {
            try {
                $event = Webhook::constructEvent(
                    $payload,
                    $signature,
                    $webhookSecret
                );
            } catch (\Exception $e) {
                Log::error('Stripe webhook signature verification failed', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }
        } else {
            $event = json_decode($payload, true);
        }

        Log::info('Stripe webhook received', [
            'type' => $event['type'] ?? 'unknown',
        ]);

        $stripeService = new StripeService;

        // Handle different event types
        try {
            switch ($event['type']) {
                case 'customer.subscription.created':
                    $stripeService->handleSubscriptionCreated($event);
                    break;

                case 'customer.subscription.updated':
                    $stripeService->handleSubscriptionUpdated($event);
                    break;

                case 'customer.subscription.deleted':
                    $stripeService->handleSubscriptionUpdated($event);
                    break;

                case 'payment_intent.succeeded':
                    $stripeService->handlePaymentSucceeded($event);
                    break;

                case 'payment_intent.payment_failed':
                    Log::warning('Payment failed', [
                        'payment_intent' => $event['data']['object']['id'] ?? 'unknown',
                    ]);
                    break;

                case 'invoice.paid':
                    $stripeService->handleInvoicePaid($event);
                    break;

                case 'invoice.payment_failed':
                    Log::warning('Invoice payment failed', [
                        'invoice' => $event['data']['object']['id'] ?? 'unknown',
                    ]);
                    break;

                default:
                    Log::info('Unhandled webhook event', [
                        'type' => $event['type'],
                    ]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'type' => $event['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
