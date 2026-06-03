<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditService;
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

        if (! hash_equals($expected, $signature)) {
            Log::error('Paystack webhook signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || empty($event['event'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
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
                    AuditService::log(
                        AuditLog::SUBSCRIPTION_CREATED,
                        'Paystack subscription created: '.($subscriptionCode ?? 'unknown'),
                        null,
                        ['subscription_code' => $subscriptionCode]
                    );
                    break;

                case 'subscription.disable':
                case 'subscription.not_renew':
                    $paystackService->handleSubscriptionDisabled($event);
                    AuditService::log(
                        AuditLog::SUBSCRIPTION_CANCELLED,
                        'Paystack subscription disabled: '.($subscriptionCode ?? 'unknown'),
                        null,
                        ['subscription_code' => $subscriptionCode]
                    );
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
