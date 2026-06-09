---
name: billing-subscription-patterns
description: >
  Mines platform billing and subscription patterns. Use when: processing Paystack payments,
  managing Subscription or SubscriptionPlan records, debugging webhook verification, testing
  payment flows, working with Invoice or Payment models, building BillingPortal Livewire
  component, predicting churn, or detecting revenue leakage.
argument-hint: 'Describe the billing or subscription task you need help with'
esm-layer: governance
esm-feeds-to:
  - financial-intelligence-agent
  - revenue-operations-agent
  - audit-logging-patterns
  - compliance-agent
esm-consumes-from:
  - audit-logging-patterns
  - rbac-patterns
---

# Billing & Subscription Patterns

## When to Use

- Recording or querying Payment / Invoice records
- Handling Paystack webhook events (payment success, failed, subscription renewal)
- Managing Subscription and SubscriptionPlan lifecycle
- Writing tests for billing flows or webhook security
- Debugging the BillingPortal Livewire component
- Calculating MRR, ARR, or churn risk metrics

---

## Core Models

```
Payment          — individual payment transaction
Invoice          — invoice linked to a subscription period
Subscription     — a team's active subscription
SubscriptionPlan — available plans (basic / professional / enterprise)
```

---

## Subscription States

```
trialing → active → past_due → cancelled
                ↑         ↓
                └── renewed ←┘ (on successful renewal payment)
```

---

## Paystack Webhook Verification

**CRITICAL — always verify Paystack signature before processing:**

```php
// app/Http/Controllers/WebhookController.php
use Illuminate\Http\Request;

private function verifyPaystackSignature(Request $request): bool
{
    $secret = config('services.paystack.secret_key');
    $hash   = hash_hmac('sha512', $request->getContent(), $secret);
    return hash_equals($hash, $request->header('x-paystack-signature') ?? '');
}
```

If signature fails → return `403` immediately. Never process unsigned webhooks.

---

## Webhook Events Handled

```
charge.success          → Payment::created, Subscription status updated
invoice.payment_failed  → Subscription status → past_due, team notified
subscription.disable    → Subscription status → cancelled
subscription.create     → Subscription provisioned for team
```

---

## Pattern — Processing a Successful Payment

```php
use App\Services\PaystackService;

$service = app(PaystackService::class);
$result  = $service->verifyTransaction($paystackReference);

if ($result['status'] === 'success') {
    Payment::create([
        'team_id'    => $team->id,
        'reference'  => $result['reference'],
        'amount'     => $result['amount'] / 100, // Paystack returns kobo/cents
        'currency'   => $result['currency'],
        'status'     => 'completed',
        'paid_at'    => now(),
        'metadata'   => $result['metadata'],
    ]);
    $subscription->update(['status' => 'active', 'renewed_at' => now()]);
}
```

---

## Pattern — Billing Test Setup

```php
#[Test]
public function webhook_rejects_invalid_signature(): void
{
    $this->postJson('/webhooks/paystack', ['event' => 'charge.success'], [
        'x-paystack-signature' => 'invalid_signature',
    ])->assertForbidden();
}

#[Test]
public function successful_payment_activates_subscription(): void
{
    Http::fake(['https://api.paystack.co/*' => Http::response([
        'status' => true,
        'data'   => [
            'status'    => 'success',
            'reference' => 'ref_123',
            'amount'    => 50000,
            'currency'  => 'ZAR',
            'metadata'  => ['team_id' => $this->team->id],
        ],
    ])]);

    $this->postJson('/webhooks/paystack',
        ['event' => 'charge.success', 'data' => ['reference' => 'ref_123']],
        ['x-paystack-signature' => $this->generatePaystackSignature(['event' => 'charge.success', 'data' => ['reference' => 'ref_123']])],
    )->assertOk();

    $this->assertDatabaseHas('payments', ['reference' => 'ref_123', 'status' => 'completed']);
}
```

---

## Revenue KPI Calculations

```php
// MRR — sum of active monthly subscriptions
$mrr = Subscription::where('status', 'active')
    ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
    ->sum('subscription_plans.monthly_price');

// ARR
$arr = $mrr * 12;

// Churn rate (monthly)
$cancelled   = Subscription::where('status', 'cancelled')->whereMonth('cancelled_at', now()->month)->count();
$totalActive = Subscription::where('status', 'active')->count();
$churnRate   = $totalActive > 0 ? round(($cancelled / $totalActive) * 100, 2) : 0;
```

---

## BillingPortal Livewire Component

```
app/Livewire/BillingPortal.php

Displays:
  - Current plan + status
  - Payment history (paginated)
  - Upcoming renewal date
  - Upgrade / downgrade plan actions

Security: gate-checked for 'manage_billing' permission
```

---

## ESM Intelligence Handoff

- **financial-intelligence-agent**: MRR, ARR, cost-per-team calculations
- **revenue-operations-agent**: churn detection, renewal forecasting
- **audit-logging-patterns**: all payment events logged to audit_logs
- **compliance-agent**: invoice retention for SARS/POPIA requirements

---

## Commands Reference

```bash
# Run billing tests
php artisan test --compact tests/Feature/BillingTest.php

# Check subscription statuses
php artisan tinker --execute '
App\Models\Subscription::with("plan","team")
    ->get(["id","team_id","plan_id","status","renews_at"]);
'

# Find past-due subscriptions
php artisan tinker --execute '
App\Models\Subscription::where("status","past_due")
    ->where("updated_at","<",now()->subDays(3))
    ->get(["id","team_id","status","updated_at"]);
'
```
