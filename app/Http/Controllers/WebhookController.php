<?php

namespace App\Http\Controllers;

use App\Services\PaystackService;
use App\Support\ApiPayload;
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

        // ApiPayload::str keeps the type opaque: psalm's Laravel plugin
        // otherwise folds this config key to its analysis-env value (null)
        // and marks the whole verified path below as dead code.
        $secret = ApiPayload::str(config('services.paystack.secret'));

        if ($secret === '') {
            Log::critical('Paystack secret is not configured. Set PAYSTACK_SECRET_KEY in the environment.');

            return response()->json(['error' => 'Webhook endpoint misconfigured'], 500);
        }

        $expected = hash_hmac('sha512', $payload, $secret);

        if (! hash_equals($expected, ApiPayload::str($signature))) {
            Log::error('Paystack webhook signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($payload, true);

        /** @var array<string, mixed> $event */
        $event = is_array($decoded) ? $decoded : [];

        if ($event === [] || empty($event['event'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Replay protection: reject events older than 5 minutes
        /** @psalm-suppress MixedAssignment */
        $eventTime = data_get($event, 'data.createdAt') ?? data_get($event, 'data.created_at');
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
            $data = ApiPayload::assoc($event['data'] ?? []);
            $subscriptionCode = ApiPayload::str($data['subscription_code'] ?? data_get($data, 'subscription.subscription_code'), 'unknown');
            $reference = ApiPayload::str($data['reference'] ?? null, 'unknown');

            switch ($event['event']) {
                case 'subscription.create':
                    $paystackService->handleSubscriptionCreated($event);
                    Log::info('Paystack subscription created', ['subscription_code' => $subscriptionCode]);
                    break;

                case 'subscription.disable':
                case 'subscription.not_renew':
                    $paystackService->handleSubscriptionDisabled($event);
                    Log::info('Paystack subscription disabled', ['subscription_code' => $subscriptionCode]);
                    break;

                case 'charge.success':
                    $paystackService->handleChargeSuccess($event);
                    break;

                case 'invoice.update':
                    $paystackService->handleInvoiceUpdate($event);
                    break;

                case 'invoice.payment_failed':
                    Log::warning('Paystack invoice payment failed', [
                        'reference' => $reference,
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
