<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\MachineAllocation;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * The webhook side of Paystack (refactor R3: extracted from
 * PaystackService): every handler consumes an ALREADY-SIGNATURE-VERIFIED
 * event payload and mutates this app's money records -- subscriptions,
 * payments, allocation grants, notifications. No HTTP leaves this class;
 * talking TO Paystack stays in PaystackService. Idempotency contracts
 * (Payment firstOrCreate on reference; ledger guard per payment) are
 * unchanged from the billing program. Payloads arrive as untyped JSON:
 * every field is guard-extracted, and a missing required field skips the
 * write exactly like the pre-extraction code's caught exceptions did.
 */
final class PaystackWebhookProcessor
{
    /** @param array<string, mixed> $data */
    public function handleSubscriptionCreated(array $data): void
    {
        try {
            $subscriptionData = $this->subArray($data, 'data');
            $metadata = $this->subArray($subscriptionData, 'metadata');
            $teamId = $this->intValue($metadata, 'team_id');
            $subscriptionCode = $this->stringValue($subscriptionData, 'subscription_code');

            $planCode = $this->stringValue($this->subArray($subscriptionData, 'plan'), 'plan_code');
            $planId = $this->intValue($metadata, 'plan_id');

            if (($planId === null || $planId === 0) && $planCode !== null) {
                $planId = (int) SubscriptionPlan::query()
                    ->where('paystack_plan_code', $planCode)
                    ->orWhere('paystack_yearly_plan_code', $planCode)
                    ->value('id');
            }

            if ($teamId === null || $teamId === 0 || $subscriptionCode === null) {
                Log::warning('Missing team_id in subscription.create webhook', [
                    'subscription_code' => $subscriptionCode ?? 'unknown',
                ]);

                return;
            }

            $nextPaymentRaw = $this->stringValue($subscriptionData, 'next_payment_date');
            $nextPaymentDate = $nextPaymentRaw !== null
                ? date('Y-m-d H:i:s', (int) strtotime($nextPaymentRaw))
                : null;

            Subscription::query()->updateOrCreate(
                ['paystack_subscription_code' => $subscriptionCode],
                [
                    'team_id' => $teamId,
                    'subscription_plan_id' => ($planId !== null && $planId !== 0) ? $planId : null,
                    'paystack_customer_code' => $this->stringValue($this->subArray($subscriptionData, 'customer'), 'customer_code'),
                    'paystack_email_token' => $this->stringValue($subscriptionData, 'email_token'),
                    'status' => $this->mapPaystackStatus($this->stringValue($subscriptionData, 'status') ?? 'active'),
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

    /** @param array<string, mixed> $data */
    public function handleSubscriptionDisabled(array $data): void
    {
        try {
            $subscriptionData = $this->subArray($data, 'data');
            $code = $this->stringValue($subscriptionData, 'subscription_code');

            $subscription = Subscription::query()
                ->where('paystack_subscription_code', $code ?? '')
                ->first();

            if (! $subscription) {
                Log::warning('Subscription not found for disable event', [
                    'code' => $code ?? 'unknown',
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

    /** @param array<string, mixed> $data */
    public function handleChargeSuccess(array $data): void
    {
        try {
            $charge = $this->subArray($data, 'data');
            $metadata = $this->subArray($charge, 'metadata');
            $teamId = $this->intValue($metadata, 'team_id');
            $reference = $this->stringValue($charge, 'reference');

            if ($teamId === null || $teamId === 0 || $reference === null) {
                return;
            }

            $subscription = Subscription::query()->where('team_id', $teamId)->first();

            // Idempotent on the Paystack reference: webhook retries and
            // duplicate deliveries must not double-record the payment --
            // and must NEVER double-grant allocations below.
            $payment = Payment::query()->firstOrCreate(
                ['paystack_reference' => $reference],
                [
                    'team_id' => $teamId,
                    'subscription_id' => $subscription?->id,
                    'paystack_invoice_id' => null,
                    'amount' => ($this->floatValue($charge, 'amount') ?? 0.0) / 100.0,
                    'currency' => strtoupper($this->stringValue($charge, 'currency') ?? 'ZAR'),
                    'status' => 'succeeded',
                    'payment_method' => $this->stringValue($charge, 'channel') ?? 'card',
                    'paid_at' => now(),
                    'metadata' => $metadata,
                ]
            );

            $this->grantAllocationsForPayment($payment, $metadata);
        } catch (\Exception $e) {
            Log::error('Failed to handle charge.success webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * The ONLY place purchased machine allocations are granted: from a
     * signature-verified, successfully charged payment (brief §8). Doubly
     * idempotent -- firstOrCreate above dedupes the payment, and the
     * payment_id guard here dedupes the grant even if two workers race
     * the same first delivery.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function grantAllocationsForPayment(Payment $payment, array $metadata): void
    {
        $adt = $this->intValue($metadata, 'allocation_adt') ?? 0;
        $heavy = $this->intValue($metadata, 'allocation_heavy') ?? 0;

        if ($adt <= 0 && $heavy <= 0) {
            return; // Not an allocation purchase (e.g. a legacy tier charge).
        }

        if (MachineAllocation::query()->withoutGlobalScopes()->where('payment_id', $payment->id)->exists()) {
            return;
        }

        foreach (['adt' => $adt, 'heavy' => $heavy] as $class => $quantity) {
            if ($quantity > 0) {
                MachineAllocation::query()->create([
                    'team_id' => $payment->team_id,
                    'class' => $class,
                    'delta' => $quantity,
                    'source' => 'purchase',
                    'payment_id' => $payment->id,
                    'subscription_id' => $payment->subscription_id,
                ]);
            }
        }

        Log::info('Machine allocations granted from verified payment', [
            'team_id' => $payment->team_id,
            'payment_id' => $payment->id,
            'adt' => $adt,
            'heavy' => $heavy,
        ]);

        NotificationService::dispatch([
            'team_id' => $payment->team_id,
            'type' => 'billing.allocation_granted',
            'title' => 'Machine allocations added',
            'message' => sprintf(
                'Payment confirmed — %s now available. You can add your machine%s right away.',
                collect(['ADT' => $adt, 'heavy machine' => $heavy])
                    ->filter()
                    ->map(fn (int $qty, string $label): string => "{$qty} {$label} allocation".($qty === 1 ? '' : 's'))
                    ->implode(' and '),
                ($adt + $heavy) === 1 ? '' : 's',
            ),
            'action_url' => route('fleet'),
        ]);
    }

    /**
     * A failed charge/renewal grants nothing (brief §9) -- but the
     * customer must hear about it in-app, not discover it when a machine
     * refuses to activate.
     *
     * @param  array<string, mixed>  $data
     */
    public function handlePaymentFailed(array $data): void
    {
        $inner = $this->subArray($data, 'data');
        $metadata = $this->subArray($inner, 'metadata');
        $teamId = $this->intValue($metadata, 'team_id') ?? 0;

        if ($teamId === 0) {
            $code = $this->stringValue($this->subArray($inner, 'subscription'), 'subscription_code');
            $teamId = $code !== null
                ? (int) (Subscription::query()->where('paystack_subscription_code', $code)->value('team_id') ?? 0)
                : 0;
        }

        if ($teamId === 0) {
            return;
        }

        NotificationService::dispatch([
            'team_id' => $teamId,
            'type' => 'billing.payment_failed',
            'title' => 'Payment unsuccessful',
            'message' => 'Your payment could not be processed, so no machine allocation was added. Please try again or use another payment method.',
            'alert_level' => NotificationService::LEVEL_WARNING,
            'action_url' => route('billing.index'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public function handleInvoiceUpdate(array $data): void
    {
        try {
            $invoiceData = $this->subArray($data, 'data');
            $subscriptionCode = $this->stringValue($this->subArray($invoiceData, 'subscription'), 'subscription_code');
            $invoiceCode = $this->stringValue($invoiceData, 'invoice_code');

            $subscription = Subscription::query()
                ->where('paystack_subscription_code', $subscriptionCode)
                ->first();

            if (! $subscription || $invoiceCode === null) {
                return;
            }

            $txReference = $this->stringValue($this->subArray($invoiceData, 'transaction'), 'reference');

            $payment = $txReference !== null
                ? Payment::query()->where('paystack_reference', $txReference)->first()
                : null;

            $amount = ($this->floatValue($invoiceData, 'amount') ?? 0.0) / 100.0;
            $paid = (bool) ($invoiceData['paid'] ?? false);
            $createdAtRaw = $this->stringValue($invoiceData, 'created_at');

            Invoice::query()->updateOrCreate(
                ['paystack_invoice_code' => $invoiceCode],
                [
                    'team_id' => $subscription->team_id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment?->id,
                    'invoice_number' => $invoiceCode,
                    'subtotal' => $amount,
                    'tax' => 0,
                    'total' => $amount,
                    'currency' => strtoupper($this->stringValue($invoiceData, 'currency') ?? 'ZAR'),
                    'status' => $paid ? 'paid' : 'open',
                    'issued_at' => $createdAtRaw !== null
                        ? date('Y-m-d H:i:s', (int) strtotime($createdAtRaw))
                        : now()->toDateTimeString(),
                    'paid_at' => $paid ? now()->toDateTimeString() : null,
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

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function subArray(array $source, string $key): array
    {
        /** @var mixed $value */
        $value = $source[$key] ?? null;

        /** @var array<string, mixed> */
        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $source */
    private function stringValue(array $source, string $key): ?string
    {
        /** @var mixed $value */
        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $source */
    private function intValue(array $source, string $key): ?int
    {
        /** @var mixed $value */
        $value = $source[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param array<string, mixed> $source */
    private function floatValue(array $source, string $key): ?float
    {
        /** @var mixed $value */
        $value = $source[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
