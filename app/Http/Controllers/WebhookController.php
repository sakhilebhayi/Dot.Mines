<?php

namespace App\Http\Controllers;

use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Paystack webhook
     */
    public function handlePaystack(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Paystack-Signature', '');

        $secret = config('services.paystack.secret');

        if (empty($secret)) {
            Log::critical('Paystack secret is not configured. Set PAYSTACK_SECRET_KEY in the environment.');

            return response()->json(['error' => 'Webhook endpoint misconfigured'], 500);
        }

        $expected = hash_hmac('sha512', $payload, $secret);

        if (! hash_equals($expected, is_string($signature) ? $signature : '')) {
            Log::error('Paystack webhook signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || empty($event['event'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Replay protection: reject events older than 5 minutes
        $eventTime = $event['data']['createdAt'] ?? ($event['data']['created_at'] ?? null);
        if ($eventTime !== null) {
            $parsedTime = strtotime((string) $eventTime);
            if ($parsedTime !== false && (time() - $parsedTime) > 300) {
                Log::warning('Paystack webhook replay attempt detected', [
                    'event' => $event['event'],
                    'event_time' => $eventTime,
                ]);

                return response()->json(['error' => 'Stale webhook event'], 400);
            }
        }

        Log::info('Paystack webhook received', ['event' => $event['event']]);

        $paystackService = new PaystackService;

        try {
            $data = $event['data'] ?? [];
            $subscriptionCode = $data['subscription_code'] ?? ($data['subscription']['subscription_code'] ?? null);
            $reference = $data['reference'] ?? null;

            switch ($event['event']) {
                case 'subscription.create':
                    $paystackService->handleSubscriptionCreated($event);
                    Log::info('Paystack subscription created', ['subscription_code' => $subscriptionCode ?? 'unknown']);
                    break;

                case 'subscription.disable':
                case 'subscription.not_renew':
                    $paystackService->handleSubscriptionDisabled($event);
                    Log::info('Paystack subscription disabled', ['subscription_code' => $subscriptionCode ?? 'unknown']);
                    break;

                case 'charge.success':
                    $paystackService->handleChargeSuccess($event);
                    break;

                case 'invoice.update':
                    $paystackService->handleInvoiceUpdate($event);
                    break;

                case 'invoice.payment_failed':
                    Log::warning('Paystack invoice payment failed', [
                        'reference' => $reference ?? 'unknown',
                    ]);
                    $paystackService->handlePaymentFailed($event);
                    break;

                default:
                    Log::info('Unhandled Paystack webhook event', ['event' => $event['event']]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Paystack webhook processing failed', [
                'event' => $event['event'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
