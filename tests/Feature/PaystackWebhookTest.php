<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test_paystack_secret_key';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paystack.secret', $this->secret);
    }

    private function makeSignature(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->secret);
    }

    private function postWebhook(array $event, ?string $signature = null): TestResponse
    {
        $payload = (string) json_encode($event);
        $sig = $signature ?? $this->makeSignature($payload);

        return $this->postJson('/webhooks/paystack', $event, [
            'X-Paystack-Signature' => $sig,
        ]);
    }

    #[Test]
    public function test_valid_signature_is_accepted(): void
    {
        $this->postWebhook(['event' => 'invoice.payment_failed', 'data' => []])
            ->assertStatus(200);
    }

    #[Test]
    public function test_invalid_signature_returns_400(): void
    {
        $this->postWebhook(['event' => 'charge.success', 'data' => []], 'totally_wrong_signature')
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function test_missing_signature_returns_400(): void
    {
        $this->postJson('/webhooks/paystack', ['event' => 'charge.success', 'data' => []])
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid signature']);
    }

    #[Test]
    public function test_unconfigured_secret_returns_500(): void
    {
        Config::set('services.paystack.secret', null);

        $this->postWebhook(['event' => 'charge.success', 'data' => []])
            ->assertStatus(500)
            ->assertJson(['error' => 'Webhook endpoint misconfigured']);
    }

    #[Test]
    public function test_missing_event_key_returns_400(): void
    {
        $this->postWebhook(['data' => []])
            ->assertStatus(400)
            ->assertJson(['error' => 'Invalid payload']);
    }

    #[Test]
    public function test_unhandled_event_type_returns_success(): void
    {
        $this->postWebhook(['event' => 'some.unknown.event', 'data' => []])
            ->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    #[Test]
    public function test_subscription_disable_event_is_accepted(): void
    {
        $this->postWebhook([
            'event' => 'subscription.disable',
            'data' => ['subscription_code' => 'SUB_test123', 'status' => 'complete'],
        ])->assertStatus(200);
    }

    #[Test]
    public function test_tampered_payload_with_original_signature_returns_400(): void
    {
        $originalPayload = (string) json_encode(['event' => 'charge.success', 'data' => ['amount' => 100]]);
        $validSig = $this->makeSignature($originalPayload);

        // Attacker modifies amount but reuses the signature
        $this->postJson('/webhooks/paystack', ['event' => 'charge.success', 'data' => ['amount' => 999999]], [
            'X-Paystack-Signature' => $validSig,
        ])->assertStatus(400)
            ->assertJson(['error' => 'Invalid signature']);
    }
}
